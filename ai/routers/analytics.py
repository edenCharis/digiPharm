"""
Analytics API — serves digipharmai_db data.
Protected by API key (X-API-Key header or ?api_key= query param).
"""
from fastapi import APIRouter, HTTPException, Request
from sqlalchemy import text
from datetime import datetime
import math
import os


def _clean(records: list[dict]) -> list[dict]:
    """Replace float NaN with None and date objects with ISO strings for JSON."""
    import datetime
    def _conv(v):
        if isinstance(v, float) and math.isnan(v):
            return None
        if isinstance(v, (datetime.date, datetime.datetime)):
            return v.isoformat()
        return v
    return [{k: _conv(v) for k, v in r.items()} for r in records]

from models.analytics import (
    dashboard_summary, revenue_trends, generate_alerts, get_inventory,
    generate_brief, supplier_reliability, aquery,
)
from core.schemas import ChatRequest
from core.llm import ask_llm_with_tools
from core.tools import TOOLS_SCHEMA, make_tool_executor

router = APIRouter(prefix="/analytics", tags=["analytics"])

_ANALYTICS_URL = (
    "mysql+pymysql://{user}:{pwd}@{host}:{port}/{db}?charset=utf8mb4".format(
        user=os.getenv("DB_USER", "root"),
        pwd=os.getenv("DB_PASSWORD", ""),
        host=os.getenv("DB_HOST", "localhost"),
        port=os.getenv("DB_PORT", "3306"),
        db=os.getenv("ANALYTICS_DB_NAME", "digipharmai_db"),
    )
)


def _resolve_pharmacy(request: Request) -> int:
    """Validate API key and return pharmacy_id."""
    from sqlalchemy import create_engine
    from models.analytics import _engine

    api_key = (
        request.headers.get("X-API-Key")
        or request.query_params.get("api_key")
    )
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")

    try:
        with _engine().connect() as conn:
            row = conn.execute(
                text("SELECT id FROM ai_pharmacies WHERE api_key = :k AND is_active = 1"),
                {"k": api_key},
            ).fetchone()
    except Exception as e:
        raise HTTPException(status_code=503, detail=f"Analytics DB unavailable: {e}")

    if not row:
        raise HTTPException(status_code=403, detail="Invalid or inactive API key")

    return int(row[0])


@router.get("/dashboard")
def analytics_dashboard(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        return dashboard_summary(pid)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/trends")
def analytics_trends(request: Request, days: int = 30):
    pid = _resolve_pharmacy(request)
    try:
        return revenue_trends(pid, days=days)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/alerts")
def analytics_alerts(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        return {"pharmacy_id": pid, "alerts": generate_alerts(pid)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/brief")
def analytics_brief(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        return generate_brief(pid)
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/inventory")
def analytics_inventory(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        inv = get_inventory(pid)
        items = _clean(inv.to_dict("records")) if not inv.empty else []
        return {"pharmacy_id": pid, "items": items}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.get("/suppliers")
def analytics_suppliers(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        return {"pharmacy_id": pid, "suppliers": supplier_reliability(pid)}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/chat")
def analytics_chat(body: ChatRequest, request: Request):
    pid = _resolve_pharmacy(request)

    try:
        name_df = aquery("SELECT name FROM ai_pharmacies WHERE id = :pid", {"pid": pid})
        pharmacy_name = name_df["name"].iloc[0] if not name_df.empty else "votre pharmacie"
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Impossible de charger les données: {e}")

    today = datetime.today().date().isoformat()
    system_prompt = (
        f"Tu es digiMind, l'assistant IA analytique de la pharmacie {pharmacy_name}. Nous sommes le {today}. "
        "Tu as accès à des outils pour interroger les données réelles de la pharmacie (ventes, stock, "
        "fournisseurs, livraisons, prévisions de demande). Utilise-les systématiquement pour toute question "
        "factuelle — ne réponds jamais avec des chiffres inventés ou approximatifs sans les avoir vérifiés "
        "via un outil, et ne te limite pas à un seul appel : creuse autant que nécessaire pour répondre "
        "complètement (ex: comparer plusieurs produits ou périodes veut dire plusieurs appels). "
        "Sois proactif — pour une question ouverte comme 'comment va ma pharmacie', appelle plusieurs outils "
        "pertinents (résumé des ventes, alertes, stock faible, fournisseurs) et croise-les pour repérer des "
        "signaux ou tendances que le pharmacien n'aurait pas remarqués lui-même, pas juste réciter des chiffres. "
        "Si une donnée demandée n'existe vraiment pas, dis-le clairement plutôt que d'inventer. "
        "Réponds en français, de façon concise et actionnable. Tu peux orienter vers une page du tableau de "
        "bord, mais UNIQUEMENT parmi celles-ci (n'invente aucun autre nom) : Vue d'ensemble, Tendances, "
        "Inventaire, Alertes, Fournisseurs, Commandes, Synchronisation, Paramètres, Mon compte."
    )

    executor = make_tool_executor(pid)
    try:
        reply = ask_llm_with_tools(
            system_prompt, body.question, [h.dict() for h in body.history],
            TOOLS_SCHEMA, executor,
        )
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Service IA conversationnel indisponible: {e}")

    return {"reply": reply, "available": True}

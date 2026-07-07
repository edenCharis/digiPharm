"""
Analytics API — serves digipharmai_db data.
Protected by API key (X-API-Key header or ?api_key= query param).
"""
from fastapi import APIRouter, HTTPException, Request
from sqlalchemy import text
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
from core.llm import ask_llm

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


def _build_chat_context(pid: int) -> str:
    """Compact, human-readable snapshot of the pharmacy's data for the LLM prompt.

    Deliberately pre-aggregated (not raw SQL handed to the model) to keep
    token usage low and avoid the LLM inventing figures from partial rows.
    """
    summary = dashboard_summary(pid)
    alerts  = generate_alerts(pid)[:8]
    inv     = get_inventory(pid)

    lines = [
        f"CA (30 derniers jours): {summary['total_revenue']:.0f} XAF, tendance {summary['revenue_trend']}%",
        f"Transactions (30j): {summary['total_tx']}, panier moyen: {summary['avg_basket']:.0f} XAF",
        f"Alertes critiques: {summary['alerts_critical']}, avertissements: {summary['alerts_warning']}",
        f"Produits à réapprovisionner: {summary['reorder_needed']}",
        f"Qualité des données: {summary['model_quality']} ({summary['data_rows']} lignes analysées)",
    ]

    if summary.get("top_products"):
        top = ", ".join(str(p.get("product_name", "?")) for p in summary["top_products"][:5])
        lines.append(f"Top produits (30j): {top}")

    if alerts:
        lines.append("Alertes actives:")
        for a in alerts:
            lines.append(f"- [{a['severity']}] {a['product_name']}: {a['message']}")

    if not inv.empty and "dos" in inv.columns:
        low = inv[inv["dos"].notna() & (inv["dos"] < 14)].sort_values("dos").head(8)
        if not low.empty:
            lines.append("Stock faible (jours de stock restants):")
            for _, r in low.iterrows():
                lines.append(f"- {r['product_name']}: {r['dos']} jours ({int(r['stock_quantity'])} unités)")

    return "\n".join(lines)


@router.post("/chat")
def analytics_chat(body: ChatRequest, request: Request):
    pid = _resolve_pharmacy(request)

    try:
        name_df = aquery("SELECT name FROM ai_pharmacies WHERE id = :pid", {"pid": pid})
        pharmacy_name = name_df["name"].iloc[0] if not name_df.empty else "votre pharmacie"
        context = _build_chat_context(pid)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Impossible de charger les données: {e}")

    system_prompt = (
        f"Tu es digiMind, l'assistant IA analytique de la pharmacie {pharmacy_name}. "
        "Réponds en français, de façon concise et actionnable (quelques phrases, pas d'essai). "
        "Base-toi UNIQUEMENT sur les données ci-dessous — n'invente jamais de chiffres. "
        "Si l'information demandée n'y figure pas, dis-le clairement et oriente vers la page "
        "correspondante du tableau de bord plutôt que de deviner.\n\n"
        f"Données actuelles de la pharmacie:\n{context}"
    )

    try:
        reply = ask_llm(system_prompt, body.question, [h.dict() for h in body.history])
    except Exception as e:
        raise HTTPException(status_code=502, detail=f"Service IA conversationnel indisponible: {e}")

    return {"reply": reply, "available": True}

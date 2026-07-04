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
    dashboard_summary, revenue_trends, generate_alerts, get_inventory
)

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


@router.get("/inventory")
def analytics_inventory(request: Request):
    pid = _resolve_pharmacy(request)
    try:
        inv = get_inventory(pid)
        items = _clean(inv.to_dict("records")) if not inv.empty else []
        return {"pharmacy_id": pid, "items": items}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

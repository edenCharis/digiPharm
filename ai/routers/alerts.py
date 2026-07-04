from fastapi import APIRouter
from datetime import datetime
from models.heuristic import generate_alerts

router = APIRouter(prefix="/alerts", tags=["alerts"])

@router.get("/{pharmacy_id}")
def get_alerts(pharmacy_id: int):
    alerts = generate_alerts(pharmacy_id)
    return {
        "pharmacy_id":   pharmacy_id,
        "alerts":        alerts,
        "critical_count": sum(1 for a in alerts if a["severity"] == "critical"),
        "warning_count":  sum(1 for a in alerts if a["severity"] == "warning"),
        "generated_at":  datetime.now().isoformat(),
    }

from fastapi import APIRouter
from datetime import datetime
from models.heuristic import inventory_recommendations

router = APIRouter(prefix="/inventory", tags=["inventory"])

@router.get("/recommendations/{pharmacy_id}")
def get_recommendations(pharmacy_id: int):
    recs = inventory_recommendations(pharmacy_id)
    return {
        "pharmacy_id":     pharmacy_id,
        "recommendations": recs,
        "generated_at":    datetime.now().isoformat(),
    }

from fastapi import APIRouter
from models.heuristic import revenue_trends

router = APIRouter(prefix="/trends", tags=["trends"])

@router.get("/{pharmacy_id}")
def get_trends(pharmacy_id: int, days: int = 30):
    return revenue_trends(pharmacy_id, days)

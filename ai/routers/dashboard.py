from fastapi import APIRouter
from models.heuristic import dashboard_summary

router = APIRouter(prefix="/dashboard", tags=["dashboard"])

@router.get("/{pharmacy_id}")
def get_dashboard(pharmacy_id: int):
    return dashboard_summary(pharmacy_id)

from fastapi import APIRouter
from models.heuristic import forecast_product, revenue_trends

router = APIRouter(prefix="/forecast", tags=["forecast"])

@router.get("/{pharmacy_id}/{product_id}")
def product_forecast(pharmacy_id: int, product_id: int, days: int = 30):
    return forecast_product(pharmacy_id, product_id, days)

@router.get("/top/{pharmacy_id}")
def top_products_forecast(pharmacy_id: int, limit: int = 5):
    trends = revenue_trends(pharmacy_id, days=30)
    return {"pharmacy_id": pharmacy_id, "top_products": trends["top_products"][:limit]}

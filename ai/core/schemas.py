from pydantic import BaseModel
from typing import Optional, List, Any
from datetime import datetime

class ForecastPoint(BaseModel):
    date: str
    predicted_qty: float
    lower: float
    upper: float

class ForecastResponse(BaseModel):
    pharmacy_id: int
    product_id: int
    product_name: str
    days: int
    forecast: List[ForecastPoint]
    avg_daily_demand: float
    recommended_reorder_qty: int
    model: str = "heuristic"
    available: bool = True

class InventoryItem(BaseModel):
    product_id: int
    product_name: str
    current_stock: int
    avg_daily_sales: float
    days_of_stock: float
    action: str          # "reorder_now" | "watch" | "overstock" | "slow_mover"
    recommended_qty: int
    urgency: str         # "high" | "medium" | "low"

class InventoryResponse(BaseModel):
    pharmacy_id: int
    recommendations: List[InventoryItem]
    generated_at: str
    available: bool = True

class Alert(BaseModel):
    id: str
    type: str            # "stockout_risk" | "expiry_risk" | "anomaly" | "slow_mover"
    severity: str        # "critical" | "warning" | "info"
    product_id: Optional[int]
    product_name: Optional[str]
    message: str
    value: Optional[float]
    unit: Optional[str]

class AlertsResponse(BaseModel):
    pharmacy_id: int
    alerts: List[Alert]
    critical_count: int
    warning_count: int
    generated_at: str
    available: bool = True

class TrendPoint(BaseModel):
    date: str
    revenue: float
    transactions: int

class TrendsResponse(BaseModel):
    pharmacy_id: int
    days: int
    series: List[TrendPoint]
    total_revenue: float
    total_transactions: int
    avg_basket: float
    growth_rate: float       # % vs previous period
    top_products: List[dict]
    available: bool = True

class DashboardSummary(BaseModel):
    pharmacy_id: int
    alerts_critical: int
    alerts_warning: int
    reorder_needed: int
    revenue_trend: float     # % growth
    top_forecast: List[dict]
    insight_text: str        # Human-readable AI insight sentence
    model_quality: str       # "insufficient_data" | "learning" | "good" | "excellent"
    available: bool = True

class ChatMessage(BaseModel):
    role: str                # "user" | "assistant"
    content: str

class ChatRequest(BaseModel):
    question: str
    history: List[ChatMessage] = []

class ChatResponse(BaseModel):
    reply: str
    available: bool = True

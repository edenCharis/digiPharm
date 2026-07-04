"""
digiMind Service — FastAPI
Runs on localhost:8000 alongside the PHP ERP.

ERP endpoints (pharmacy_id in URL, no auth — internal only):
  GET /health
  GET /dashboard/{pharmacy_id}
  GET /forecast/{pharmacy_id}/{product_id}?days=30
  GET /forecast/top/{pharmacy_id}?limit=5
  GET /inventory/recommendations/{pharmacy_id}
  GET /alerts/{pharmacy_id}
  GET /trends/{pharmacy_id}?days=30

Analytics endpoints (API key auth, multi-tenant digipharmai_db):
  GET /analytics/dashboard     X-API-Key: <key>
  GET /analytics/trends        X-API-Key: <key>
  GET /analytics/alerts        X-API-Key: <key>
  GET /analytics/inventory     X-API-Key: <key>
"""
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from routers import forecast, inventory, alerts, trends, dashboard
from routers import analytics, etl_control
from core.config import AI_SERVICE_HOST, AI_SERVICE_PORT

app = FastAPI(
    title="digiMind",
    description="AI analytics service for the digiMind pharmacy ERP",
    version="1.0.0",
    docs_url="/docs",
    redoc_url="/redoc",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost", "http://127.0.0.1"],
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)

# ── Routers ───────────────────────────────────────────────────────────────
app.include_router(forecast.router)
app.include_router(inventory.router)
app.include_router(alerts.router)
app.include_router(trends.router)
app.include_router(dashboard.router)
app.include_router(analytics.router)
app.include_router(etl_control.router)


# ── Health check ──────────────────────────────────────────────────────────
@app.get("/health", tags=["system"])
def health():
    return {"status": "ok", "service": "digipharm-ai", "version": "1.0.0"}


# ── Entry point ───────────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host=AI_SERVICE_HOST, port=AI_SERVICE_PORT, reload=False)

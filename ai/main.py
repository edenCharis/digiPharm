"""
DigiPharm AI Service — FastAPI
Runs on localhost:8000 alongside the PHP ERP.

Endpoints:
  GET /health
  GET /dashboard/{pharmacy_id}
  GET /forecast/{pharmacy_id}/{product_id}?days=30
  GET /forecast/top/{pharmacy_id}?limit=5
  GET /inventory/recommendations/{pharmacy_id}
  GET /alerts/{pharmacy_id}
  GET /trends/{pharmacy_id}?days=30
"""
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from routers import forecast, inventory, alerts, trends, dashboard
from core.config import AI_SERVICE_HOST, AI_SERVICE_PORT

app = FastAPI(
    title="DigiPharm AI",
    description="AI analytics service for the DigiPharm pharmacy ERP",
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


# ── Health check ──────────────────────────────────────────────────────────
@app.get("/health", tags=["system"])
def health():
    return {"status": "ok", "service": "digipharm-ai", "version": "1.0.0"}


# ── Entry point ───────────────────────────────────────────────────────────
if __name__ == "__main__":
    import uvicorn
    uvicorn.run("main:app", host=AI_SERVICE_HOST, port=AI_SERVICE_PORT, reload=False)

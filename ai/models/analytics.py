"""
Analytics models — query digipharmai_db (normalized multi-tenant schema).
Used by the standalone analytics dashboard endpoints.
"""
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from sqlalchemy import create_engine, text
import os

# Analytics DB engine (separate from the ERP DB)
_ANALYTICS_URL = (
    "mysql+pymysql://{user}:{pwd}@{host}:{port}/{db}?charset=utf8mb4".format(
        user=os.getenv("DB_USER", "root"),
        pwd=os.getenv("DB_PASSWORD", ""),
        host=os.getenv("DB_HOST", "localhost"),
        port=os.getenv("DB_PORT", "3306"),
        db=os.getenv("ANALYTICS_DB_NAME", "digipharmai_db"),
    )
)

_analytics_engine = None


def _engine():
    global _analytics_engine
    if _analytics_engine is None:
        _analytics_engine = create_engine(
            _ANALYTICS_URL, pool_pre_ping=True, pool_recycle=3600
        )
    return _analytics_engine


def aquery(sql: str, params: dict = None) -> pd.DataFrame:
    with _engine().connect() as conn:
        return pd.read_sql(text(sql), conn, params=params or {})


# ── Sales history ─────────────────────────────────────────────────────────

def get_sales(pharmacy_id: int, days: int = 90) -> pd.DataFrame:
    return aquery("""
        SELECT sale_date, product_id, product_name, category,
               quantity, unit_price, revenue, cost
        FROM ai_sales
        WHERE pharmacy_id = :pid
          AND sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        ORDER BY sale_date
    """, {"pid": pharmacy_id, "days": days})


def get_inventory(pharmacy_id: int) -> pd.DataFrame:
    """Latest inventory snapshot with days-of-stock estimate."""
    return aquery("""
        SELECT i.product_id, i.product_name, i.category,
               i.stock_quantity, i.unit_cost, i.unit_price,
               DATE_FORMAT(i.expiry_date, '%%Y-%%m-%%d') AS expiry_date,
               CASE
                 WHEN COALESCE(s.avg_daily_qty, 0) > 0
                 THEN ROUND(i.stock_quantity / s.avg_daily_qty, 1)
                 ELSE NULL
               END AS dos
        FROM ai_inventory i
        INNER JOIN (
            SELECT product_id, MAX(snapshot_date) AS latest
            FROM ai_inventory
            WHERE pharmacy_id = :pid
            GROUP BY product_id
        ) latest ON latest.product_id = i.product_id
                AND latest.latest = i.snapshot_date
        LEFT JOIN (
            SELECT product_id,
                   SUM(quantity) / GREATEST(DATEDIFF(MAX(sale_date), MIN(sale_date)), 1) AS avg_daily_qty
            FROM ai_sales
            WHERE pharmacy_id = :pid
              AND sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY product_id
        ) s ON s.product_id = i.product_id
        WHERE i.pharmacy_id = :pid
        ORDER BY i.stock_quantity DESC, i.product_name ASC
    """, {"pid": pharmacy_id})


# ── Revenue trends ────────────────────────────────────────────────────────

def revenue_trends(pharmacy_id: int, days: int = 30) -> dict:
    df = aquery("""
        SELECT sale_date AS date,
               SUM(revenue)  AS revenue,
               COUNT(DISTINCT source_sale_id) AS transactions
        FROM ai_sales
        WHERE pharmacy_id = :pid
          AND sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY sale_date ORDER BY sale_date
    """, {"pid": pharmacy_id, "days": days})

    prev = aquery("""
        SELECT SUM(revenue) AS revenue
        FROM ai_sales
        WHERE pharmacy_id = :pid
          AND sale_date >= DATE_SUB(CURDATE(), INTERVAL :double DAY)
          AND sale_date <  DATE_SUB(CURDATE(), INTERVAL :days DAY)
    """, {"pid": pharmacy_id, "days": days, "double": days * 2})

    total_rev = float(df["revenue"].sum()) if not df.empty else 0.0
    total_tx  = int(df["transactions"].sum()) if not df.empty else 0
    prev_rev  = float(prev["revenue"].iloc[0]) if not prev.empty and prev["revenue"].iloc[0] else 0.0
    growth    = round(((total_rev - prev_rev) / prev_rev) * 100, 1) if prev_rev > 0 else 0.0
    avg_basket = round(total_rev / total_tx, 0) if total_tx > 0 else 0.0

    series = []
    if not df.empty:
        for _, r in df.iterrows():
            series.append({
                "date":         str(r["date"]),
                "revenue":      round(float(r["revenue"]), 0),
                "transactions": int(r["transactions"]),
            })

    top_df = aquery("""
        SELECT product_name AS name,
               SUM(quantity) AS qty, SUM(revenue) AS revenue
        FROM ai_sales
        WHERE pharmacy_id = :pid
          AND sale_date >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY product_name ORDER BY revenue DESC LIMIT 5
    """, {"pid": pharmacy_id, "days": days})

    return {
        "pharmacy_id":        pharmacy_id,
        "days":               days,
        "series":             series,
        "total_revenue":      round(total_rev, 0),
        "total_transactions": total_tx,
        "avg_basket":         avg_basket,
        "growth_rate":        growth,
        "top_products":       top_df.to_dict("records") if not top_df.empty else [],
    }


# ── Inventory alerts ──────────────────────────────────────────────────────

def generate_alerts(pharmacy_id: int) -> list[dict]:
    inv    = get_inventory(pharmacy_id)
    sales  = get_sales(pharmacy_id, days=30)
    alerts = []

    if inv.empty:
        return alerts

    avg_sales = pd.DataFrame(columns=["product_id", "avg_daily"])
    if not sales.empty:
        ag = sales.groupby("product_id")["quantity"].sum() / 30
        avg_sales = ag.reset_index()
        avg_sales.columns = ["product_id", "avg_daily"]

    df = inv.merge(avg_sales, on="product_id", how="left")
    df["avg_daily"] = df["avg_daily"].fillna(0)
    today = datetime.today().date()

    for _, r in df.iterrows():
        pid   = str(r["product_id"])
        name  = r["product_name"]
        stock = float(r["stock_quantity"])
        avg   = float(r["avg_daily"])
        dos   = (stock / avg) if avg > 0 else 999

        if dos <= 3 and avg > 0:
            alerts.append({"id": f"stockout_{pid}", "type": "stockout_risk",
                           "severity": "critical", "product_id": pid,
                           "product_name": name,
                           "message": f"Rupture dans ~{int(dos)} jour(s)",
                           "value": round(dos, 1), "unit": "jours"})
        elif dos <= 7 and avg > 0:
            alerts.append({"id": f"stocklow_{pid}", "type": "stockout_risk",
                           "severity": "warning", "product_id": pid,
                           "product_name": name,
                           "message": f"Stock faible — ~{int(dos)} jours",
                           "value": round(dos, 1), "unit": "jours"})

        exp = r.get("expiry_date")
        if pd.notna(exp) and exp:
            try:
                exp_d = pd.to_datetime(exp).date()
                d = (exp_d - today).days
                if 0 < d <= 30:
                    alerts.append({"id": f"expiry_{pid}", "type": "expiry_risk",
                                   "severity": "critical" if d <= 7 else "warning",
                                   "product_id": pid, "product_name": name,
                                   "message": f"Expire dans {d} jour(s)",
                                   "value": d, "unit": "jours"})
            except Exception:
                pass

        if avg == 0 and stock > 10:
            alerts.append({"id": f"slow_{pid}", "type": "slow_mover",
                           "severity": "info", "product_id": pid,
                           "product_name": name,
                           "message": f"Aucune vente — {int(stock)} unités immobilisées",
                           "value": stock, "unit": "unités"})

    return alerts


# ── Dashboard summary ─────────────────────────────────────────────────────

def dashboard_summary(pharmacy_id: int) -> dict:
    trends  = revenue_trends(pharmacy_id, days=30)
    alerts  = generate_alerts(pharmacy_id)
    inv     = get_inventory(pharmacy_id)

    critical = sum(1 for a in alerts if a["severity"] == "critical")
    warning  = sum(1 for a in alerts if a["severity"] == "warning")

    # Days-of-stock per product → count needing reorder (dos < 14)
    sales30 = get_sales(pharmacy_id, days=30)
    reorder = 0
    if not inv.empty and not sales30.empty:
        ag = sales30.groupby("product_id")["quantity"].sum() / 30
        df = inv.merge(ag.reset_index().rename(columns={"quantity": "avg"}),
                       on="product_id", how="left")
        df["avg"] = df["avg"].fillna(0)
        df["dos"] = df.apply(
            lambda r: r["stock_quantity"] / r["avg"] if r["avg"] > 0 else 999, axis=1
        )
        reorder = int((df["dos"] < 14).sum())

    # Data quality
    count_df = aquery(
        "SELECT COUNT(*) AS n FROM ai_sales WHERE pharmacy_id = :pid",
        {"pid": pharmacy_id}
    )
    n = int(count_df["n"].iloc[0]) if not count_df.empty else 0
    quality = "insufficient_data" if n < 50 else "learning" if n < 500 else "good" if n < 5000 else "excellent"

    if critical > 0:
        insight = f"{critical} produit(s) en rupture imminente — commandez maintenant."
    elif reorder > 0:
        insight = f"{reorder} réapprovisionnement(s) recommandé(s) cette semaine."
    elif trends["growth_rate"] > 5:
        insight = f"CA en hausse de {trends['growth_rate']}% vs période précédente."
    elif trends["growth_rate"] < -5:
        insight = f"Baisse de {abs(trends['growth_rate'])}% du CA — analysez les tendances."
    else:
        insight = "Stocks et ventes dans les normes."

    return {
        "pharmacy_id":     pharmacy_id,
        "alerts_critical": critical,
        "alerts_warning":  warning,
        "reorder_needed":  reorder,
        "revenue_trend":   trends["growth_rate"],
        "total_revenue":   trends["total_revenue"],
        "total_tx":        trends["total_transactions"],
        "avg_basket":      trends["avg_basket"],
        "top_products":    trends["top_products"][:5],
        "insight_text":    insight,
        "model_quality":   quality,
        "data_rows":       n,
    }

"""
Heuristic models — used when there's insufficient event data for ML.
These use simple statistics directly on the MySQL operational tables.
Replace each function with the real ML model as data accumulates.
"""
import pandas as pd
import numpy as np
from datetime import datetime, timedelta
from core.database import query_df


# ── Sales history ─────────────────────────────────────────────────────────

def get_sales_history(pharmacy_id: int, days: int = 90) -> pd.DataFrame:
    """Return daily sales per product for the last N days."""
    sql = """
        SELECT
            DATE(s.saleDate)         AS sale_date,
            si.productId             AS product_id,
            p.name                   AS product_name,
            SUM(si.quantity)         AS qty_sold,
            SUM(si.quantity * si.unitPrice) AS revenue
        FROM saleitem si
        JOIN sale     s ON s.id = si.saleId
        JOIN product  p ON p.id = si.productId
        WHERE p.pharmacy_id = :pharmacy_id
          AND s.saleDate    >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(s.saleDate), si.productId, p.name
        ORDER BY sale_date
    """
    return query_df(sql, {"pharmacy_id": pharmacy_id, "days": days})


def get_product_stock(pharmacy_id: int) -> pd.DataFrame:
    """Current stock levels for all active products."""
    sql = """
        SELECT id AS product_id, name AS product_name,
               stock, expiryDate, purchasePrice, sellingPrice
        FROM product
        WHERE pharmacy_id = :pharmacy_id AND statut = 1
        ORDER BY name
    """
    return query_df(sql, {"pharmacy_id": pharmacy_id})


# ── Demand forecast (heuristic) ───────────────────────────────────────────

def forecast_product(pharmacy_id: int, product_id: int, days: int = 30) -> dict:
    hist = get_sales_history(pharmacy_id, days=90)
    prod = hist[hist["product_id"] == product_id] if not hist.empty else pd.DataFrame()

    product_name = "Produit"
    avg_daily = 0.0

    if not prod.empty:
        product_name = prod["product_name"].iloc[0]
        avg_daily = prod["qty_sold"].sum() / 90.0

    # Simple linear projection with ±15% confidence interval
    forecast_points = []
    base_date = datetime.today()
    for i in range(1, days + 1):
        date_str = (base_date + timedelta(days=i)).strftime("%Y-%m-%d")
        noise = np.random.normal(0, avg_daily * 0.1) if avg_daily > 0 else 0
        predicted = max(0.0, round(avg_daily + noise, 2))
        forecast_points.append({
            "date": date_str,
            "predicted_qty": predicted,
            "lower": max(0.0, round(predicted * 0.85, 2)),
            "upper": round(predicted * 1.15, 2),
        })

    reorder_qty = max(0, int(avg_daily * days * 1.2))  # 20% safety stock

    return {
        "pharmacy_id":            pharmacy_id,
        "product_id":             product_id,
        "product_name":           product_name,
        "days":                   days,
        "forecast":               forecast_points,
        "avg_daily_demand":       round(avg_daily, 3),
        "recommended_reorder_qty": reorder_qty,
        "model":                  "heuristic_avg",
    }


# ── Inventory recommendations ─────────────────────────────────────────────

def inventory_recommendations(pharmacy_id: int) -> list[dict]:
    stock_df = get_product_stock(pharmacy_id)
    hist     = get_sales_history(pharmacy_id, days=30)

    if stock_df.empty:
        return []

    # Average daily sales per product over 30 days
    if not hist.empty:
        avg_sales = (
            hist.groupby("product_id")["qty_sold"].sum() / 30
        ).reset_index()
        avg_sales.columns = ["product_id", "avg_daily_sales"]
    else:
        avg_sales = pd.DataFrame(columns=["product_id", "avg_daily_sales"])

    df = stock_df.merge(avg_sales, on="product_id", how="left")
    df["avg_daily_sales"] = df["avg_daily_sales"].fillna(0)
    df["days_of_stock"] = df.apply(
        lambda r: (r["stock"] / r["avg_daily_sales"]) if r["avg_daily_sales"] > 0 else 999,
        axis=1,
    )

    recommendations = []
    for _, row in df.iterrows():
        dos   = row["days_of_stock"]
        avg   = row["avg_daily_sales"]
        stock = row["stock"]

        if dos <= 3 and avg > 0:
            action, urgency = "reorder_now", "high"
            rec_qty = int(avg * 30 * 1.3)
        elif dos <= 7 and avg > 0:
            action, urgency = "reorder_now", "medium"
            rec_qty = int(avg * 30)
        elif dos <= 14 and avg > 0:
            action, urgency = "watch", "low"
            rec_qty = int(avg * 14)
        elif avg == 0 and stock > 0:
            action, urgency = "slow_mover", "low"
            rec_qty = 0
        elif dos > 90:
            action, urgency = "overstock", "low"
            rec_qty = 0
        else:
            continue  # healthy stock

        recommendations.append({
            "product_id":       int(row["product_id"]),
            "product_name":     row["product_name"],
            "current_stock":    int(stock),
            "avg_daily_sales":  round(float(avg), 2),
            "days_of_stock":    round(float(dos), 1) if dos < 999 else None,
            "action":           action,
            "recommended_qty":  rec_qty,
            "urgency":          urgency,
        })

    # Sort: critical first
    urgency_order = {"high": 0, "medium": 1, "low": 2}
    recommendations.sort(key=lambda x: urgency_order.get(x["urgency"], 9))
    return recommendations


# ── Alerts ────────────────────────────────────────────────────────────────

def generate_alerts(pharmacy_id: int) -> list[dict]:
    alerts = []
    stock_df = get_product_stock(pharmacy_id)
    hist     = get_sales_history(pharmacy_id, days=30)

    if stock_df.empty:
        return alerts

    avg_sales = pd.DataFrame(columns=["product_id", "avg_daily_sales"])
    if not hist.empty:
        avg_sales = (
            hist.groupby("product_id")["qty_sold"].sum() / 30
        ).reset_index()
        avg_sales.columns = ["product_id", "avg_daily_sales"]

    df = stock_df.merge(avg_sales, on="product_id", how="left")
    df["avg_daily_sales"] = df["avg_daily_sales"].fillna(0)
    today = datetime.today().date()

    for _, row in df.iterrows():
        pid   = int(row["product_id"])
        name  = row["product_name"]
        stock = int(row["stock"])
        avg   = float(row["avg_daily_sales"])
        dos   = (stock / avg) if avg > 0 else 999

        # Stockout risk
        if dos <= 3 and avg > 0:
            alerts.append({
                "id":           f"stockout_{pid}",
                "type":         "stockout_risk",
                "severity":     "critical",
                "product_id":   pid,
                "product_name": name,
                "message":      f"Rupture dans ~{int(dos)} jour(s) au rythme actuel",
                "value":        round(dos, 1),
                "unit":         "jours",
            })
        elif dos <= 7 and avg > 0:
            alerts.append({
                "id":           f"stocklow_{pid}",
                "type":         "stockout_risk",
                "severity":     "warning",
                "product_id":   pid,
                "product_name": name,
                "message":      f"Stock faible — ~{int(dos)} jours restants",
                "value":        round(dos, 1),
                "unit":         "jours",
            })

        # Expiry risk
        if pd.notna(row.get("expiryDate")) and row["expiryDate"]:
            try:
                exp = pd.to_datetime(row["expiryDate"]).date()
                days_to_exp = (exp - today).days
                if 0 < days_to_exp <= 30:
                    alerts.append({
                        "id":           f"expiry_{pid}",
                        "type":         "expiry_risk",
                        "severity":     "critical" if days_to_exp <= 7 else "warning",
                        "product_id":   pid,
                        "product_name": name,
                        "message":      f"Expire dans {days_to_exp} jour(s)",
                        "value":        days_to_exp,
                        "unit":         "jours",
                    })
            except Exception:
                pass

        # Slow mover
        if avg == 0 and stock > 10:
            alerts.append({
                "id":           f"slow_{pid}",
                "type":         "slow_mover",
                "severity":     "info",
                "product_id":   pid,
                "product_name": name,
                "message":      f"Aucune vente ce mois — stock immobilisé ({stock} unités)",
                "value":        float(stock),
                "unit":         "unités",
            })

    return alerts


# ── Revenue trends ────────────────────────────────────────────────────────

def revenue_trends(pharmacy_id: int, days: int = 30) -> dict:
    sql = """
        SELECT DATE(s.saleDate) AS date,
               SUM(s.totalAmount)  AS revenue,
               COUNT(s.id)         AS transactions
        FROM sale s
        JOIN saleitem si ON si.saleId = s.id
        JOIN product p   ON p.id = si.productId
        WHERE p.pharmacy_id = :pharmacy_id
          AND s.saleDate >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY DATE(s.saleDate)
        ORDER BY date
    """
    df = query_df(sql, {"pharmacy_id": pharmacy_id, "days": days})

    # Previous period for growth rate
    sql_prev = sql.replace(":days DAY)", ":days DAY)\n          AND s.saleDate < DATE_SUB(CURDATE(), INTERVAL :prev_start DAY)")
    prev_rev = 0.0
    try:
        prev_sql = """
            SELECT SUM(s.totalAmount) AS revenue
            FROM sale s
            JOIN saleitem si ON si.saleId = s.id
            JOIN product p   ON p.id = si.productId
            WHERE p.pharmacy_id = :pharmacy_id
              AND s.saleDate >= DATE_SUB(CURDATE(), INTERVAL :double_days DAY)
              AND s.saleDate <  DATE_SUB(CURDATE(), INTERVAL :days DAY)
        """
        prev_df = query_df(prev_sql, {"pharmacy_id": pharmacy_id, "days": days, "double_days": days * 2})
        if not prev_df.empty and prev_df["revenue"].iloc[0]:
            prev_rev = float(prev_df["revenue"].iloc[0])
    except Exception:
        pass

    total_rev  = float(df["revenue"].sum()) if not df.empty else 0.0
    total_tx   = int(df["transactions"].sum()) if not df.empty else 0
    avg_basket = round(total_rev / total_tx, 0) if total_tx > 0 else 0.0
    growth     = round(((total_rev - prev_rev) / prev_rev) * 100, 1) if prev_rev > 0 else 0.0

    series = []
    if not df.empty:
        for _, row in df.iterrows():
            series.append({
                "date":         str(row["date"]),
                "revenue":      round(float(row["revenue"]), 0),
                "transactions": int(row["transactions"]),
            })

    # Top products this period
    top_sql = """
        SELECT p.name, SUM(si.quantity) AS qty, SUM(si.quantity * si.unitPrice) AS revenue
        FROM saleitem si
        JOIN sale s ON s.id = si.saleId
        JOIN product p ON p.id = si.productId
        WHERE p.pharmacy_id = :pharmacy_id
          AND s.saleDate >= DATE_SUB(CURDATE(), INTERVAL :days DAY)
        GROUP BY p.name ORDER BY revenue DESC LIMIT 5
    """
    top_df = query_df(top_sql, {"pharmacy_id": pharmacy_id, "days": days})
    top_products = []
    if not top_df.empty:
        for _, row in top_df.iterrows():
            top_products.append({
                "name":    row["name"],
                "qty":     int(row["qty"]),
                "revenue": round(float(row["revenue"]), 0),
            })

    return {
        "pharmacy_id":       pharmacy_id,
        "days":              days,
        "series":            series,
        "total_revenue":     round(total_rev, 0),
        "total_transactions": total_tx,
        "avg_basket":        avg_basket,
        "growth_rate":       growth,
        "top_products":      top_products,
    }


# ── Dashboard summary ─────────────────────────────────────────────────────

def dashboard_summary(pharmacy_id: int) -> dict:
    alerts = generate_alerts(pharmacy_id)
    recs   = inventory_recommendations(pharmacy_id)
    trends = revenue_trends(pharmacy_id, days=30)

    critical = sum(1 for a in alerts if a["severity"] == "critical")
    warning  = sum(1 for a in alerts if a["severity"] == "warning")
    reorder  = sum(1 for r in recs if r["action"] == "reorder_now")

    # Determine data quality
    event_sql = "SELECT COUNT(*) AS cnt FROM events WHERE pharmacy_id = :pid"
    try:
        cnt_df = query_df(event_sql, {"pid": pharmacy_id})
        event_count = int(cnt_df["cnt"].iloc[0]) if not cnt_df.empty else 0
    except Exception:
        event_count = 0

    if event_count < 10:
        quality = "insufficient_data"
    elif event_count < 100:
        quality = "learning"
    elif event_count < 1000:
        quality = "good"
    else:
        quality = "excellent"

    # AI insight sentence
    if critical > 0:
        insight = f"⚠️ {critical} produit(s) en rupture imminente — commandez maintenant."
    elif reorder > 0:
        insight = f"📦 {reorder} réapprovisionnement(s) recommandé(s) cette semaine."
    elif trends["growth_rate"] > 5:
        insight = f"📈 Chiffre d'affaires en hausse de {trends['growth_rate']}% vs période précédente."
    elif trends["growth_rate"] < -5:
        insight = f"📉 Baisse de {abs(trends['growth_rate'])}% du CA — analysez les tendances."
    else:
        insight = "✅ Stocks et ventes dans les normes. Continuez à surveiller les prévisions."

    top_forecast = trends.get("top_products", [])[:3]

    return {
        "pharmacy_id":     pharmacy_id,
        "alerts_critical": critical,
        "alerts_warning":  warning,
        "reorder_needed":  reorder,
        "revenue_trend":   trends["growth_rate"],
        "top_forecast":    top_forecast,
        "insight_text":    insight,
        "model_quality":   quality,
    }

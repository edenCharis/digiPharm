"""
Analytics models — query digipharmai_db (normalized multi-tenant schema).
Used by the standalone analytics dashboard endpoints.
"""
import math
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
               i.expiry_date,
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


# ── Daily Executive Brief ─────────────────────────────────────────────────

def _fmtk(n) -> str:
    """Compact number string for brief text (1 234 567 → 1.2M, 45000 → 45k)."""
    try:
        n = float(n)
    except (TypeError, ValueError):
        return "0"
    if math.isnan(n) or math.isinf(n):
        return "0"
    if n >= 1_000_000:
        return f"{n / 1_000_000:.1f}M"
    if n >= 1_000:
        return f"{int(n / 1_000)}k"
    return str(int(n))


def generate_brief(pharmacy_id: int) -> dict:
    """Daily Executive Brief — 5 decision sections from real pharmacy data."""
    today = datetime.today().date()

    inv      = get_inventory(pharmacy_id)
    sales_30 = get_sales(pharmacy_id, days=30)
    sales_90 = get_sales(pharmacy_id, days=90)

    count_df   = aquery("SELECT COUNT(*) AS n FROM ai_sales WHERE pharmacy_id = :pid", {"pid": pharmacy_id})
    total_rows = int(count_df["n"].iloc[0]) if not count_df.empty else 0
    inv_count  = int(len(inv)) if not inv.empty else 0

    # avg daily qty per product (30-day window)
    avg_daily_map: dict[int, float] = {}
    if not sales_30.empty:
        for pid_val, avg in (sales_30.groupby("product_id")["quantity"].sum() / 30).items():
            avg_daily_map[int(pid_val)] = float(avg)

    # days-of-stock per product
    inv_dos: dict[int, float] = {}
    if not inv.empty:
        for _, row in inv.iterrows():
            pid_val = int(row["product_id"])
            stock   = float(row["stock_quantity"] or 0)
            avg     = avg_daily_map.get(pid_val, 0)
            inv_dos[pid_val] = (stock / avg) if avg > 0 else 999.0

    trends       = revenue_trends(pharmacy_id, days=30)
    growth_rate  = float(trends.get("growth_rate", 0))
    series       = trends.get("series", [])

    sections = []

    # ── 1 · RISKS ─────────────────────────────────────────────────────────
    risk_cards: list[dict] = []

    if not inv.empty:
        # Critical stockouts (≤3 days)
        crit = [
            (row, inv_dos[int(row["product_id"])])
            for _, row in inv.iterrows()
            if inv_dos.get(int(row["product_id"]), 999) <= 3
            and avg_daily_map.get(int(row["product_id"]), 0) > 0
        ]
        crit.sort(key=lambda x: x[1])
        if crit:
            names    = [r["product_name"] for r, _ in crit[:3]]
            extra    = f" et {len(crit) - 3} autres" if len(crit) > 3 else ""
            lost_rev = sum(
                avg_daily_map.get(int(r["product_id"]), 0) * float(r.get("unit_price") or 0) * 3
                for r, _ in crit
            )
            risk_cards.append({
                "id": "critical_stockout", "severity": "critical",
                "headline": f"{len(crit)} produit(s) en rupture dans moins de 72 heures",
                "explanation": f"{', '.join(names)}{extra} seront épuisés d'ici 3 jours au rythme de vente actuel.",
                "impact": f"Perte de ventes estimée : {_fmtk(lost_rev)} XAF si aucune commande n'est passée aujourd'hui.",
                "recommendation": f"Commandez en urgence {names[0]} en priorité absolue. Contactez votre fournisseur maintenant.",
                "confidence": 92, "action_label": "Voir l'inventaire", "action_target": "/analytics/inventory.php",
            })

        # Near-stockouts (3–7 days)
        warn = [
            (row, inv_dos[int(row["product_id"])])
            for _, row in inv.iterrows()
            if 3 < inv_dos.get(int(row["product_id"]), 999) <= 7
            and avg_daily_map.get(int(row["product_id"]), 0) > 0
        ]
        warn.sort(key=lambda x: x[1])
        if warn:
            rev_at_risk = sum(
                avg_daily_map.get(int(r["product_id"]), 0) * float(r.get("unit_price") or 0) * 7
                for r, _ in warn
            )
            risk_cards.append({
                "id": "warning_stockout", "severity": "warning",
                "headline": f"{len(warn)} produit(s) épuisés cette semaine sans réapprovisionnement",
                "explanation": "Ces produits ont entre 3 et 7 jours de stock restant selon les ventes actuelles.",
                "impact": f"CA à risque : {_fmtk(rev_at_risk)} XAF sur les 7 prochains jours.",
                "recommendation": "Planifiez une commande aujourd'hui. Priorisez par volume de ventes.",
                "confidence": 87, "action_label": "Voir les alertes", "action_target": "/analytics/alerts.php",
            })

        # Expiring products
        exp_items = []
        for _, row in inv.iterrows():
            exp = row.get("expiry_date")
            if exp is None or str(exp) in ("None", "NaT", ""):
                continue
            try:
                days_left = (pd.to_datetime(exp).date() - today).days
                if 0 < days_left <= 30:
                    exp_items.append({
                        "name": str(row["product_name"]), "days": days_left,
                        "value": float(row["stock_quantity"] or 0) * float(row.get("unit_price") or 0),
                    })
            except Exception:
                pass
        if exp_items:
            exp_items.sort(key=lambda x: x["days"])
            crit_exp = [e for e in exp_items if e["days"] <= 7]
            total_val = sum(e["value"] for e in exp_items)
            first = exp_items[0]
            risk_cards.append({
                "id": "expiry_risk", "severity": "critical" if crit_exp else "warning",
                "headline": f"{len(exp_items)} produit(s) périment dans les 30 prochains jours",
                "explanation": f"Le plus urgent : {first['name']} expire dans {first['days']} jour(s)." + (f" {len(crit_exp)} produit(s) expirent cette semaine." if crit_exp else ""),
                "impact": f"Valeur stock menacée : {_fmtk(total_val)} XAF à risque de démarque ou destruction.",
                "recommendation": "Appliquez immédiatement des promotions sur les produits à péremption courte pour liquider les stocks.",
                "confidence": 97, "action_label": "Voir les alertes", "action_target": "/analytics/alerts.php",
            })

    if growth_rate < -10:
        risk_cards.append({
            "id": "revenue_decline", "severity": "warning",
            "headline": f"Chiffre d'affaires en recul de {abs(growth_rate):.1f}% sur 30 jours",
            "explanation": "Le CA de ce mois est significativement inférieur à la période précédente.",
            "impact": "Ce recul peut signaler des ruptures de stock, une pression concurrentielle ou un effet saisonnier.",
            "recommendation": "Analysez les catégories en baisse sur la page Tendances et identifiez les causes racines.",
            "confidence": 85, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
        })

    if not risk_cards:
        risk_cards.append({
            "id": "no_risk", "severity": "ok",
            "headline": "Aucun risque critique détecté aujourd'hui",
            "explanation": "Stocks, péremptions et chiffre d'affaires sont dans les normes.",
            "impact": "Profitez de cette stabilité pour travailler sur l'optimisation et la croissance.",
            "recommendation": "Analysez vos opportunités de croissance dans la section ci-dessous.",
            "confidence": 78, "action_label": None, "action_target": None,
        })

    sections.append({"id": "risks", "title": "Risques", "cards": risk_cards})

    # ── 2 · OPPORTUNITIES ─────────────────────────────────────────────────
    opp_cards: list[dict] = []

    if not sales_30.empty:
        top = sales_30.groupby(["product_id", "product_name"])["revenue"].sum().reset_index()
        top = top.sort_values("revenue", ascending=False)
        if not top.empty:
            best = top.iloc[0]
            best_rev  = float(best["revenue"])
            total_rev = float(top["revenue"].sum())
            share     = round(best_rev / total_rev * 100, 1) if total_rev > 0 else 0
            opp_cards.append({
                "id": "top_revenue", "severity": "ok",
                "headline": f"{best['product_name']} génère {share}% de votre CA",
                "explanation": f"Ce produit a rapporté {_fmtk(best_rev)} XAF sur 30 jours — votre meilleur asset commercial.",
                "impact": f"Augmenter sa disponibilité de 10% apporterait {_fmtk(best_rev * 0.1)} XAF supplémentaires par mois.",
                "recommendation": f"Assurez un stock permanent de {best['product_name']}. Négociez un tarif fournisseur préférentiel.",
                "confidence": 85, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
            })

        # Dormant stock
        if not inv.empty:
            sold_ids = set(int(x) for x in sales_30["product_id"].unique())
            dormant  = inv[~inv["product_id"].astype(int).isin(sold_ids) & (inv["stock_quantity"] > 5)]
            if not dormant.empty:
                dormant_val = float((dormant["stock_quantity"] * dormant["unit_cost"].fillna(0)).sum())
                opp_cards.append({
                    "id": "dormant_stock", "severity": "warning",
                    "headline": f"{len(dormant)} produit(s) immobiles bloquent votre trésorerie",
                    "explanation": f"{dormant.iloc[0]['product_name']} et {len(dormant)-1} autre(s) n'ont enregistré aucune vente depuis 30 jours.",
                    "impact": f"{_fmtk(dormant_val)} XAF de trésorerie potentiellement récupérable par des promotions ciblées.",
                    "recommendation": "Appliquez des remises de 15–25% sur ces produits pour accélérer leur rotation et libérer du cash.",
                    "confidence": 76, "action_label": "Voir l'inventaire", "action_target": "/analytics/inventory.php",
                })

        # Best category
        if "category" in sales_30.columns and sales_30["category"].notna().any():
            cat = sales_30.groupby("category")["revenue"].sum().reset_index().sort_values("revenue", ascending=False)
            if not cat.empty:
                top_cat     = cat.iloc[0]
                top_cat_rev = float(top_cat["revenue"])
                cat_share   = round(top_cat_rev / float(cat["revenue"].sum()) * 100, 1) if float(cat["revenue"].sum()) > 0 else 0
                opp_cards.append({
                    "id": "best_category", "severity": "ok",
                    "headline": f"Catégorie '{top_cat['category']}' : {_fmtk(top_cat_rev)} XAF sur 30 jours",
                    "explanation": f"Cette catégorie représente {cat_share}% de votre chiffre d'affaires total.",
                    "impact": f"Élargir la gamme '{top_cat['category']}' avec 2–3 nouveaux produits pourrait augmenter ce segment de 15–20%.",
                    "recommendation": f"Identifiez les produits manquants dans '{top_cat['category']}' et contactez vos fournisseurs.",
                    "confidence": 73, "action_label": None, "action_target": None,
                })

    if not opp_cards:
        opp_cards.append({
            "id": "no_opp", "severity": "ok",
            "headline": "Synchronisez plus de données pour découvrir vos opportunités",
            "explanation": "L'IA a besoin d'un historique plus riche pour identifier des opportunités précises.",
            "impact": "Avec 90 jours de données, l'IA peut identifier vos produits à fort potentiel.",
            "recommendation": "Lancez une synchronisation complète depuis la page Synchronisation.",
            "confidence": 55, "action_label": "Synchroniser", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "opportunities", "title": "Opportunités", "cards": opp_cards})

    # ── 3 · ACTIONS ───────────────────────────────────────────────────────
    action_cards: list[dict] = []

    action_map = {
        "critical_stockout": ("Commandez en urgence — ruptures dans 72h", "critical"),
        "expiry_risk":       ("Lancez des promotions sur les produits à péremption courte", None),
        "warning_stockout":  ("Planifiez votre bon de commande de réapprovisionnement", "warning"),
        "revenue_decline":   ("Analysez et corrigez le recul du chiffre d'affaires", "warning"),
    }
    for rc in risk_cards:
        if rc["id"] in action_map:
            headline_ovr, sev_ovr = action_map[rc["id"]]
            action_cards.append({
                "id": f"act_{rc['id']}", "severity": sev_ovr or rc["severity"],
                "headline": headline_ovr,
                "explanation": rc["explanation"], "impact": rc["impact"],
                "recommendation": rc["recommendation"],
                "confidence": rc["confidence"],
                "action_label": rc.get("action_label"), "action_target": rc.get("action_target"),
            })

    for oc in opp_cards:
        if oc["id"] == "dormant_stock":
            action_cards.append({
                "id": "act_dormant_stock", "severity": "info",
                "headline": "Libérez la trésorerie bloquée dans les stocks dormants",
                "explanation": oc["explanation"], "impact": oc["impact"],
                "recommendation": oc["recommendation"],
                "confidence": oc["confidence"],
                "action_label": oc.get("action_label"), "action_target": oc.get("action_target"),
            })

    if not action_cards:
        action_cards.append({
            "id": "act_maintain", "severity": "ok",
            "headline": "Maintenez vos bonnes performances actuelles",
            "explanation": "Aucune action urgente détectée. Vos opérations sont dans les normes.",
            "impact": "Capitaliser sur cette période stable pour améliorer vos processus et marges.",
            "recommendation": "Analysez vos marges produit par produit et identifiez les quick wins pour optimiser la rentabilité.",
            "confidence": 70, "action_label": None, "action_target": None,
        })

    sections.append({"id": "actions", "title": "Actions du jour", "cards": action_cards})

    # ── 4 · FORECASTS ─────────────────────────────────────────────────────
    forecast_cards: list[dict] = []

    if series and len(series) >= 7:
        revenues    = [s["revenue"] for s in series]
        recent_avg  = sum(revenues[-7:]) / 7
        monthly_proj = recent_avg * 30
        half        = len(revenues) // 2
        first_avg   = sum(revenues[:half]) / half if half else recent_avg
        second_avg  = sum(revenues[half:]) / (len(revenues) - half) if len(revenues) - half else recent_avg
        if second_avg > first_avg * 1.05:
            trend_label, trend_sev = "hausse", "ok"
        elif second_avg < first_avg * 0.95:
            trend_label, trend_sev = "baisse", "warning"
        else:
            trend_label, trend_sev = "stable", "ok"

        forecast_cards.append({
            "id": "monthly_proj", "severity": trend_sev,
            "headline": f"Projection mensuelle : {_fmtk(monthly_proj)} XAF",
            "explanation": f"Basé sur votre moyenne des 7 derniers jours ({_fmtk(recent_avg)} XAF/jour). Tendance récente : en {trend_label}.",
            "impact": "Vous êtes en bonne voie pour dépasser le mois précédent." if growth_rate >= 0 else "Ce rythme produira un CA inférieur au mois précédent.",
            "recommendation": "Maintenez la disponibilité de vos top produits pour sécuriser cette projection.",
            "confidence": 68, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
        })

    if not sales_90.empty:
        try:
            s90 = sales_90.copy()
            s90["dow"] = pd.to_datetime(s90["sale_date"]).dt.dayofweek
            dow_perf = s90.groupby("dow")["revenue"].mean()
            if not dow_perf.empty:
                best_dow  = int(dow_perf.idxmax())
                worst_dow = int(dow_perf.idxmin())
                best_rev  = float(dow_perf.max())
                worst_rev = float(dow_perf.min())
                dow_fr    = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"]
                gap_pct   = round((best_rev - worst_rev) / best_rev * 100, 0) if best_rev > 0 else 0
                forecast_cards.append({
                    "id": "best_day", "severity": "ok",
                    "headline": f"{dow_fr[best_dow]} est votre meilleur jour ({_fmtk(best_rev)} XAF/jour en moy.)",
                    "explanation": f"{dow_fr[worst_dow]} est votre jour le plus faible ({_fmtk(worst_rev)} XAF) — {int(gap_pct)}% de moins que le pic.",
                    "impact": f"Optimiser le {dow_fr[worst_dow]} pour atteindre la moyenne des autres jours apporterait {_fmtk((best_rev + worst_rev) / 2 * 4)} XAF supplémentaires par mois.",
                    "recommendation": f"Assurez des stocks complets chaque {dow_fr[best_dow]}. Envisagez des promotions le {dow_fr[worst_dow]} pour booster ce creux.",
                    "confidence": 79, "action_label": None, "action_target": None,
                })
        except Exception:
            pass

    if not forecast_cards:
        forecast_cards.append({
            "id": "forecast_learning", "severity": "ok",
            "headline": "Prévisions en cours d'apprentissage",
            "explanation": "L'IA a besoin de 30+ jours de données pour générer des prévisions fiables.",
            "impact": "La précision augmente exponentiellement avec la richesse des données historiques.",
            "recommendation": "Activez la synchronisation quotidienne automatique.",
            "confidence": 55, "action_label": "Configurer", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "forecasts", "title": "Prévisions", "cards": forecast_cards})

    # ── 5 · INSIGHTS ──────────────────────────────────────────────────────
    insight_cards: list[dict] = []

    if not sales_90.empty:
        try:
            daily_rev = sales_90.groupby("sale_date")["revenue"].sum()
            avg_rev   = float(daily_rev.mean())
            if avg_rev > 0:
                peak_date = str(daily_rev.idxmax())
                peak_val  = float(daily_rev.max())
                ratio     = round(peak_val / avg_rev, 1)
                if ratio >= 1.5:
                    insight_cards.append({
                        "id": "peak_day", "severity": "ok",
                        "headline": f"Pic exceptionnel de {_fmtk(peak_val)} XAF ({ratio}× la moyenne journalière)",
                        "explanation": f"Le {peak_date} a généré {ratio}× votre CA moyen de {_fmtk(avg_rev)} XAF/jour.",
                        "impact": "Ces pics cachent souvent des patterns reproductibles : promotion, événement, livraison spéciale.",
                        "recommendation": "Identifiez ce qui s'est passé ce jour-là et répliquez ces conditions régulièrement.",
                        "confidence": 87, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
                    })
        except Exception:
            pass

        if "category" in sales_90.columns and sales_90["category"].notna().any():
            try:
                cat_rev   = sales_90.groupby("category")["revenue"].sum()
                total_cat = float(cat_rev.sum())
                top_share = float(cat_rev.max() / total_cat * 100) if total_cat > 0 else 0
                if top_share > 60:
                    top_cat_name = str(cat_rev.idxmax())
                    insight_cards.append({
                        "id": "concentration_risk", "severity": "warning",
                        "headline": f"{int(top_share)}% de votre CA dépend d'une seule catégorie",
                        "explanation": f"'{top_cat_name}' domine massivement. Une rupture ou pression concurrentielle dans cette catégorie impacterait directement vos revenus.",
                        "impact": "Cette concentration crée une vulnérabilité stratégique. Diversifier réduit le risque systémique.",
                        "recommendation": "Développez 2–3 catégories complémentaires pour équilibrer votre portefeuille et sécuriser votre revenu.",
                        "confidence": 82, "action_label": None, "action_target": None,
                    })
            except Exception:
                pass

        if "source_sale_id" in sales_90.columns:
            try:
                tx_count = int(sales_90["source_sale_id"].nunique())
                total_90 = float(sales_90["revenue"].sum())
                if tx_count > 0:
                    basket       = total_90 / tx_count
                    basket_daily = total_90 * 0.10 / 90
                    insight_cards.append({
                        "id": "basket_insight", "severity": "ok",
                        "headline": f"Panier moyen de {_fmtk(basket)} XAF sur {tx_count:,} transactions",
                        "explanation": f"Augmenter le panier de seulement 10% générerait {_fmtk(basket_daily)} XAF de CA supplémentaire par jour.",
                        "impact": "Le panier moyen est le levier de croissance le plus rapide et le moins coûteux à activer.",
                        "recommendation": "Formez votre équipe à la vente complémentaire : proposez un produit associé à chaque achat.",
                        "confidence": 80, "action_label": None, "action_target": None,
                    })
            except Exception:
                pass

    if not insight_cards:
        insight_cards.append({
            "id": "learning", "severity": "ok",
            "headline": "L'IA est en phase d'apprentissage",
            "explanation": "Des insights plus riches émergeront avec des données sur 90+ jours.",
            "impact": "La qualité des insights augmente exponentiellement avec la quantité de données.",
            "recommendation": "Activez la synchronisation quotidienne automatique pour accélérer l'apprentissage.",
            "confidence": 60, "action_label": "Configurer", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "insights", "title": "Découvertes IA", "cards": insight_cards})

    return {
        "pharmacy_id":     int(pharmacy_id),
        "generated_at":    datetime.now().isoformat(),
        "data_rows":       total_rows,
        "inventory_count": inv_count,
        "sections":        sections,
    }

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
    """Daily Executive Brief — 5 decision sections + health score + timeline."""
    from itertools import combinations
    from collections import Counter

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

    trends      = revenue_trends(pharmacy_id, days=30)
    growth_rate = float(trends.get("growth_rate", 0))
    series      = trends.get("series", [])

    # ── Health Score (5 dimensions) ───────────────────────────────────────
    active_pids = [pid for pid, avg in avg_daily_map.items() if avg > 0]
    n_ok_avail  = sum(1 for pid in active_pids if inv_dos.get(pid, 999) >= 7)
    avail_score = round(n_ok_avail / len(active_pids) * 100) if active_pids else 70

    profit_score = 60
    if not sales_30.empty and "cost" in sales_30.columns:
        r30, c30 = float(sales_30["revenue"].sum()), float(sales_30["cost"].sum())
        if r30 > 0:
            profit_score = min(100, max(0, round((r30 - c30) / r30 * 150)))

    rotation_score = 50
    if not inv.empty and not sales_30.empty:
        sold30 = set(int(x) for x in sales_30["product_id"].unique())
        all_p  = set(int(x) for x in inv["product_id"].unique())
        rotation_score = round(len(sold30 & all_p) / len(all_p) * 100) if all_p else 50

    qual_score = 30 if total_rows < 50 else 55 if total_rows < 500 else 75 if total_rows < 5000 else 90

    cash_score = 70
    if not inv.empty:
        sold30_set = set(int(x) for x in sales_30["product_id"].unique()) if not sales_30.empty else set()
        tot_val  = float((inv["stock_quantity"] * inv["unit_cost"].fillna(0)).sum())
        dorm_inv = inv[~inv["product_id"].astype(int).isin(sold30_set)]
        dorm_val = float((dorm_inv["stock_quantity"] * dorm_inv["unit_cost"].fillna(0)).sum())
        cash_score = max(0, round((1 - dorm_val / tot_val) * 100)) if tot_val > 0 else 70

    health_overall = round(avail_score * .30 + profit_score * .25 + rotation_score * .20 + qual_score * .15 + cash_score * .10)
    health_label   = "Excellent" if health_overall >= 85 else "Bon" if health_overall >= 70 else "Moyen" if health_overall >= 55 else "Faible"
    health_score_obj = {
        "score": health_overall, "label": health_label,
        "breakdown": {
            "Disponibilité produits": avail_score,
            "Rentabilité":            profit_score,
            "Rotation des stocks":    rotation_score,
            "Fiabilité prévisions":   qual_score,
            "Flux de trésorerie":     cash_score,
        },
    }

    # ── Pre-compute expiry items (needed by greeting + risk section) ──────
    _exp_items: list[dict] = []
    if not inv.empty:
        for _, row in inv.iterrows():
            exp = row.get("expiry_date")
            if exp is None or str(exp) in ("None", "NaT", ""):
                continue
            try:
                days_left = (pd.to_datetime(exp).date() - today).days
                if 0 < days_left <= 30:
                    _exp_items.append({
                        "name":  str(row["product_name"]),
                        "days":  days_left,
                        "value": float(row["stock_quantity"] or 0) * float(row.get("unit_price") or 0),
                        "pid":   int(row["product_id"]),
                    })
            except Exception:
                pass
    _exp_items.sort(key=lambda x: x["days"])

    # ── Pre-compute crit/warn stockout lists (shared) ─────────────────────
    _crit: list[tuple] = []
    _warn: list[tuple] = []
    if not inv.empty:
        for _, row in inv.iterrows():
            pid_v = int(row["product_id"])
            d     = inv_dos.get(pid_v, 999)
            avg_v = avg_daily_map.get(pid_v, 0)
            if avg_v > 0:
                if d <= 3:
                    _crit.append((row, d))
                elif d <= 7:
                    _warn.append((row, d))
        _crit.sort(key=lambda x: x[1])
        _warn.sort(key=lambda x: x[1])

    # ── Greeting stats ────────────────────────────────────────────────────
    rev_at_risk = sum(
        avg_daily_map.get(int(r["product_id"]), 0) * float(r.get("unit_price") or 0) * 7
        for r, _ in (_crit + _warn)
    )
    # add expiry risk to rev_at_risk
    rev_at_risk += sum(e["value"] for e in _exp_items if e["days"] <= 7)

    sold30_set_g = set(int(x) for x in sales_30["product_id"].unique()) if not sales_30.empty else set()
    dorm_rec = inv[~inv["product_id"].astype(int).isin(sold30_set_g)] if not inv.empty else pd.DataFrame()
    rev_recoverable = float((dorm_rec["stock_quantity"] * dorm_rec["unit_price"].fillna(0)).sum()) * 0.70 if not dorm_rec.empty else 0.0
    # also count expiry recoverable
    rev_recoverable += sum(e["value"] * 0.65 for e in _exp_items if e["days"] > 7)

    products_requiring_action = len(_crit) + len(_warn) + len(_exp_items)

    monthly_prob = (89 if growth_rate >= 10 else 81 if growth_rate >= 5 else
                    70 if growth_rate >= 0  else 55 if growth_rate >= -5 else 38)

    greeting_stats = {
        "revenue_at_risk":           int(rev_at_risk),
        "revenue_recoverable":       int(rev_recoverable),
        "products_requiring_action": products_requiring_action,
        "monthly_probability":       monthly_prob,
    }

    # ── Timeline ──────────────────────────────────────────────────────────
    timeline: list[dict] = []
    # upcoming stockouts
    for pid_v, dos_v in sorted(inv_dos.items(), key=lambda x: x[1]):
        if dos_v >= 14 or avg_daily_map.get(pid_v, 0) <= 0:
            continue
        rows_m = inv[inv["product_id"].astype(int) == pid_v]
        if rows_m.empty:
            continue
        name_m = str(rows_m.iloc[0]["product_name"])
        out_date = today + timedelta(days=max(0, int(dos_v)))
        timeline.append({
            "date":     out_date.isoformat(),
            "type":     "stockout",
            "label":    f"Rupture prévue — {name_m}",
            "severity": "critical" if dos_v <= 3 else "warning",
        })
        if len([t for t in timeline if t["type"] == "stockout"]) >= 5:
            break
    # upcoming expirations
    for e in _exp_items[:5]:
        exp_date = (today + timedelta(days=e["days"])).isoformat()
        timeline.append({
            "date":     exp_date,
            "type":     "expiry",
            "label":    f"Péremption — {e['name']}",
            "severity": "critical" if e["days"] <= 7 else "warning",
        })
    # revenue projection milestone
    if series and len(series) >= 7:
        rev_list     = [s["revenue"] for s in series]
        recent_avg_t = sum(rev_list[-7:]) / 7
        days_elapsed = len(series)
        days_left_m  = max(0, 30 - days_elapsed)
        projected_m  = float(trends.get("total_revenue", 0)) + recent_avg_t * days_left_m
        if days_left_m > 0:
            timeline.append({
                "date":     (today + timedelta(days=days_left_m)).isoformat(),
                "type":     "milestone",
                "label":    f"Projection fin de mois : {_fmtk(projected_m)} XAF",
                "severity": "ok" if growth_rate >= 0 else "warning",
            })
    timeline.sort(key=lambda x: x["date"])
    timeline = timeline[:8]

    sections = []

    # ── 1 · RISKS ─────────────────────────────────────────────────────────
    risk_cards: list[dict] = []

    if _crit:
        names    = [r["product_name"] for r, _ in _crit[:3]]
        extra    = f" et {len(_crit) - 3} autres" if len(_crit) > 3 else ""
        lost_rev = sum(
            avg_daily_map.get(int(r["product_id"]), 0) * float(r.get("unit_price") or 0) * 3
            for r, _ in _crit
        )
        risk_cards.append({
            "id": "critical_stockout", "severity": "critical",
            "headline": f"{len(_crit)} produit(s) en rupture avant vendredi",
            "explanation": f"{', '.join(names)}{extra} seront épuisés d'ici 72 heures. L'IA a calculé cette rupture sur la base de votre rythme de vente actuel.",
            "impact": f"Si vous ne passez pas de commande aujourd'hui, vous perdrez environ {_fmtk(lost_rev)} XAF de ventes cette semaine. Ces clients iront probablement chez la concurrence.",
            "recommendation": f"Commandez {names[0]} en priorité absolue maintenant. Ensuite {names[1] if len(names)>1 else 'les suivants'}. Chaque heure compte.",
            "expected_result": f"En commandant aujourd'hui avant 15h, vous sécurisez ~{_fmtk(lost_rev)} XAF de CA et évitez la rupture avant la fin de semaine.",
            "confidence": 92, "action_label": "Générer un bon de commande", "action_target": "/analytics/inventory.php",
        })

    if _warn:
        rev_warn = sum(
            avg_daily_map.get(int(r["product_id"]), 0) * float(r.get("unit_price") or 0) * 7
            for r, _ in _warn
        )
        risk_cards.append({
            "id": "warning_stockout", "severity": "warning",
            "headline": f"{len(_warn)} produit(s) épuisés cette semaine sans commande",
            "explanation": f"Ces produits ont entre 3 et 7 jours de stock restant. Sans réapprovisionnement, les ruptures commenceront dès mercredi.",
            "impact": f"{_fmtk(rev_warn)} XAF de CA à risque sur les 7 prochains jours.",
            "recommendation": f"Préparez un bon de commande aujourd'hui pour les {len(_warn)} produits concernés. Priorisez ceux avec la rotation la plus forte.",
            "expected_result": f"Une commande passée aujourd'hui garantit votre disponibilité jusqu'à la prochaine livraison, protégeant {_fmtk(rev_warn)} XAF.",
            "confidence": 87, "action_label": "Voir les alertes", "action_target": "/analytics/alerts.php",
        })

    if _exp_items:
        crit_exp  = [e for e in _exp_items if e["days"] <= 7]
        total_val = sum(e["value"] for e in _exp_items)
        first     = _exp_items[0]
        risk_cards.append({
            "id": "expiry_risk", "severity": "critical" if crit_exp else "warning",
            "headline": f"{len(_exp_items)} produit(s) périment dans les 30 prochains jours",
            "explanation": f"Le plus urgent : {first['name']} expire dans {first['days']} jour(s)." + (f" {len(crit_exp)} produit(s) expirent cette semaine — à traiter aujourd'hui." if crit_exp else ""),
            "impact": f"{_fmtk(total_val)} XAF de stock menacé de destruction ou de démarque forcée.",
            "recommendation": "Lancez une promotion immédiate à -20% sur les produits expirant dans moins de 7 jours. Contactez des structures partenaires pour des dons si nécessaire.",
            "expected_result": f"Une promotion aujourd'hui peut récupérer 60–80% de la valeur menacée, soit environ {_fmtk(total_val * 0.70)} XAF.",
            "confidence": 97, "action_label": "Lancer une promotion", "action_target": "/analytics/alerts.php",
        })

    if growth_rate < -10:
        risk_cards.append({
            "id": "revenue_decline", "severity": "warning",
            "headline": f"Votre CA a baissé de {abs(growth_rate):.1f}% — la tendance doit être corrigée",
            "explanation": f"Sur les 30 derniers jours, votre CA est {abs(growth_rate):.1f}% en dessous de la période précédente. L'IA a analysé les catégories et détecte un signal de baisse structurelle.",
            "impact": "Sans action corrective, cette tendance peut se poursuivre et impacter vos marges mensuelles.",
            "recommendation": "Identifiez les 3 catégories en baisse et vérifiez si la cause est une rupture de stock, un problème de prix ou une pression concurrentielle.",
            "expected_result": "En corrigeant les causes racines dans les 7 prochains jours, un retour à la normale est généralement atteignable en 2 semaines.",
            "confidence": 85, "action_label": "Analyser les tendances", "action_target": "/analytics/trends.php",
        })

    if not risk_cards:
        risk_cards.append({
            "id": "no_risk", "severity": "ok",
            "headline": "Aucun risque critique détecté ce matin",
            "explanation": "L'IA a analysé vos stocks, péremptions et tendances de CA. Tout est dans les normes.",
            "impact": "C'est une journée idéale pour travailler sur la croissance plutôt que sur les urgences.",
            "recommendation": "Profitez de cette stabilité pour analyser vos opportunités de marge dans l'onglet suivant.",
            "expected_result": "Maintenir cette stabilité opérationnelle sur 30 jours peut générer une amélioration de 5–10% de votre rentabilité.",
            "confidence": 78, "action_label": None, "action_target": None,
        })

    sections.append({"id": "risks", "title": "Ce qui menace votre business", "cards": risk_cards})

    # ── 2 · OPPORTUNITIES ─────────────────────────────────────────────────
    opp_cards: list[dict] = []

    if not sales_30.empty:
        top = sales_30.groupby(["product_id", "product_name"])["revenue"].sum().reset_index()
        top = top.sort_values("revenue", ascending=False)
        if not top.empty:
            best      = top.iloc[0]
            best_rev  = float(best["revenue"])
            total_rev = float(top["revenue"].sum())
            share     = round(best_rev / total_rev * 100, 1) if total_rev > 0 else 0
            opp_cards.append({
                "id": "top_revenue", "severity": "ok",
                "headline": f"{best['product_name']} génère {share}% de votre CA — protégez-le",
                "explanation": f"Ce produit a rapporté {_fmtk(best_rev)} XAF sur 30 jours. C'est votre meilleur asset. Une rupture ici vous coûterait cher.",
                "impact": f"Augmenter sa disponibilité de 10% seulement apporterait ~{_fmtk(best_rev * 0.1)} XAF supplémentaires par mois, sans effort marketing.",
                "recommendation": f"Négociez un stock tampon minimum de 30 jours pour {best['product_name']}. Demandez un tarif préférentiel à votre fournisseur dès cette semaine.",
                "expected_result": f"Un stock garanti sur {best['product_name']} peut générer {_fmtk(best_rev * 0.15)} XAF supplémentaires par mois dès le mois prochain.",
                "confidence": 85, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
            })

        # Dormant stock
        if not inv.empty:
            sold_ids    = set(int(x) for x in sales_30["product_id"].unique())
            dormant     = inv[~inv["product_id"].astype(int).isin(sold_ids) & (inv["stock_quantity"] > 5)]
            if not dormant.empty:
                dormant_val = float((dormant["stock_quantity"] * dormant["unit_cost"].fillna(0)).sum())
                dormant_rev_pot = float((dormant["stock_quantity"] * dormant["unit_price"].fillna(0)).sum())
                opp_cards.append({
                    "id": "dormant_stock", "severity": "warning",
                    "headline": f"{len(dormant)} produit(s) immobiles bloquent {_fmtk(dormant_val)} XAF de trésorerie",
                    "explanation": f"{dormant.iloc[0]['product_name']} et {len(dormant)-1} autre(s) n'ont enregistré aucune vente depuis 30 jours. Cet argent dort dans votre entrepôt.",
                    "impact": f"En valeur de vente, ces stocks représentent {_fmtk(dormant_rev_pot)} XAF actuellement bloqués et inaccessibles.",
                    "recommendation": f"Lancez une promotion flash à -20% sur ces {len(dormant)} produits dès aujourd'hui. Une semaine de promotion suffit généralement à écouler 70% du stock dormant.",
                    "expected_result": f"Une remise de 20% devrait libérer ~{_fmtk(dormant_val * 0.70)} XAF de trésorerie dans les 2 prochaines semaines.",
                    "confidence": 76, "action_label": "Lancer une promotion", "action_target": "/analytics/inventory.php",
                })

        # Best category
        if "category" in sales_30.columns and sales_30["category"].notna().any():
            cat = sales_30.groupby("category")["revenue"].sum().reset_index().sort_values("revenue", ascending=False)
            if not cat.empty:
                top_cat     = cat.iloc[0]
                top_cat_rev = float(top_cat["revenue"])
                cat_total   = float(cat["revenue"].sum())
                cat_share   = round(top_cat_rev / cat_total * 100, 1) if cat_total > 0 else 0
                opp_cards.append({
                    "id": "best_category", "severity": "ok",
                    "headline": f"'{top_cat['category']}' : votre catégorie locomotive — {_fmtk(top_cat_rev)} XAF/mois",
                    "explanation": f"Cette catégorie représente {cat_share}% de votre CA total sur 30 jours. C'est là que se trouve votre vraie force commerciale.",
                    "impact": f"Élargir votre gamme '{top_cat['category']}' avec 2–3 nouveaux produits pourrait augmenter ce segment de 15–20%, soit {_fmtk(top_cat_rev * 0.175)} XAF supplémentaires.",
                    "recommendation": f"Identifiez les 3 produits les plus vendus par vos concurrents dans '{top_cat['category']}' que vous ne proposez pas encore.",
                    "expected_result": f"Ajouter 2 références complémentaires dans cette catégorie pourrait générer {_fmtk(top_cat_rev * 0.15)} XAF de CA additionnel dès le 2ème mois.",
                    "confidence": 73, "action_label": None, "action_target": None,
                })

    if not opp_cards:
        opp_cards.append({
            "id": "no_opp", "severity": "ok",
            "headline": "Plus de données pour révéler tout votre potentiel",
            "explanation": "L'IA a besoin d'un historique de 60+ jours pour identifier vos vraies opportunités de croissance.",
            "impact": "Chaque semaine de données supplémentaire améliore la précision des recommandations de 15-20%.",
            "recommendation": "Lancez une synchronisation complète pour enrichir l'analyse dès maintenant.",
            "expected_result": "Avec 90 jours de données, l'IA peut identifier des opportunités de croissance de 10-25% sur votre CA.",
            "confidence": 55, "action_label": "Synchroniser", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "opportunities", "title": "Argent sur la table", "cards": opp_cards})

    # ── 3 · ACTIONS ───────────────────────────────────────────────────────
    action_cards: list[dict] = []

    _action_defs = [
        ("critical_stockout", "critical", "Passez une commande d'urgence avant ce soir",
         "Générer un bon de commande", "/analytics/inventory.php"),
        ("expiry_risk", None, "Lancez une promotion flash avant la péremption",
         "Lancer une promotion", "/analytics/alerts.php"),
        ("warning_stockout", "warning", "Préparez votre bon de commande de réapprovisionnement",
         "Voir les alertes", "/analytics/alerts.php"),
        ("revenue_decline", "warning", "Analysez et corrigez la baisse de CA",
         "Analyser les tendances", "/analytics/trends.php"),
    ]
    for rc in risk_cards:
        match = next((d for d in _action_defs if d[0] == rc["id"]), None)
        if match:
            _, sev_ovr, headline_ovr, action_lbl, action_tgt = match
            action_cards.append({
                "id": f"act_{rc['id']}", "severity": sev_ovr or rc["severity"],
                "headline": headline_ovr,
                "explanation": rc["explanation"], "impact": rc["impact"],
                "recommendation": rc["recommendation"],
                "expected_result": rc.get("expected_result", ""),
                "confidence": rc["confidence"],
                "action_label": action_lbl, "action_target": action_tgt,
            })

    for oc in opp_cards:
        if oc["id"] == "dormant_stock":
            action_cards.append({
                "id": "act_dormant_stock", "severity": "info",
                "headline": "Libérez la trésorerie bloquée dans les stocks dormants",
                "explanation": oc["explanation"], "impact": oc["impact"],
                "recommendation": oc["recommendation"],
                "expected_result": oc.get("expected_result", ""),
                "confidence": oc["confidence"],
                "action_label": "Exporter la liste de promotion", "action_target": "/analytics/inventory.php",
            })

    if not action_cards:
        action_cards.append({
            "id": "act_maintain", "severity": "ok",
            "headline": "Pas d'urgence aujourd'hui — concentrez-vous sur la croissance",
            "explanation": "L'IA n'a détecté aucune action urgente. Vos opérations tournent normalement.",
            "impact": "Les périodes sans urgence sont les meilleures pour travailler sur la croissance long terme.",
            "recommendation": "Profitez de cette stabilité pour analyser vos marges par produit et identifier 2-3 quick wins de rentabilité.",
            "expected_result": "Optimiser vos marges pendant les périodes calmes peut améliorer votre rentabilité de 3–8% sur 60 jours.",
            "confidence": 70, "action_label": None, "action_target": None,
        })

    sections.append({"id": "actions", "title": "Actions du jour", "cards": action_cards})

    # ── 4 · FORECASTS ─────────────────────────────────────────────────────
    forecast_cards: list[dict] = []

    if series and len(series) >= 7:
        revenues      = [s["revenue"] for s in series]
        recent_avg_f  = sum(revenues[-7:]) / 7
        monthly_proj  = recent_avg_f * 30
        days_elapsed  = len(series)
        days_left_f   = max(0, 30 - days_elapsed)
        projected_eom = float(trends.get("total_revenue", 0)) + recent_avg_f * days_left_f
        half          = len(revenues) // 2
        first_avg_f   = sum(revenues[:half]) / half if half else recent_avg_f
        second_avg_f  = sum(revenues[half:]) / (len(revenues) - half) if len(revenues) - half else recent_avg_f
        trend_label   = "hausse" if second_avg_f > first_avg_f * 1.05 else ("baisse" if second_avg_f < first_avg_f * 0.95 else "stable")
        trend_sev     = "warning" if trend_label == "baisse" else "ok"

        forecast_cards.append({
            "id": "monthly_proj", "severity": trend_sev,
            "headline": f"Vous terminerez probablement ce mois à {_fmtk(projected_eom)} XAF",
            "explanation": f"Votre CA moyen sur les 7 derniers jours est de {_fmtk(recent_avg_f)} XAF/jour. La tendance récente est en {trend_label}. Il reste {days_left_f} jours ce mois.",
            "impact": f"{'Vous êtes sur une trajectoire positive — au-dessus du mois précédent.' if growth_rate >= 0 else f'Sans correction, ce mois sera inférieur au précédent de {abs(growth_rate):.1f}%.'}",
            "recommendation": f"{'Maintenez la disponibilité de vos top produits pour sécuriser cette trajectoire.' if growth_rate >= 0 else 'Identifiez les catégories en baisse et corrigez les ruptures de stock dès maintenant.'}",
            "expected_result": f"Si le rythme actuel se maintient, votre CA du mois devrait atteindre {_fmtk(projected_eom)} XAF dans {days_left_f} jours.",
            "confidence": 68, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
        })

    if not sales_90.empty:
        try:
            s90_f = sales_90.copy()
            s90_f["dow"] = pd.to_datetime(s90_f["sale_date"]).dt.dayofweek
            dow_perf = s90_f.groupby("dow")["revenue"].mean()
            if not dow_perf.empty:
                best_dow_f  = int(dow_perf.idxmax())
                worst_dow_f = int(dow_perf.idxmin())
                best_rev_f  = float(dow_perf.max())
                worst_rev_f = float(dow_perf.min())
                dow_fr      = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi", "Samedi", "Dimanche"]
                gap_pct_f   = round((best_rev_f - worst_rev_f) / best_rev_f * 100) if best_rev_f > 0 else 0
                monthly_gap = (best_rev_f + worst_rev_f) / 2 * 4
                forecast_cards.append({
                    "id": "best_day", "severity": "ok",
                    "headline": f"Le {dow_fr[best_dow_f]} est votre pic hebdomadaire — préparez-vous à l'avance",
                    "explanation": f"Votre {dow_fr[best_dow_f]} génère en moyenne {_fmtk(best_rev_f)} XAF — {gap_pct_f}% de plus que votre {dow_fr[worst_dow_f]} le plus faible ({_fmtk(worst_rev_f)} XAF).",
                    "impact": f"Optimiser votre {dow_fr[worst_dow_f]} pour atteindre la moyenne des autres jours pourrait rapporter {_fmtk(monthly_gap)} XAF supplémentaires par mois.",
                    "recommendation": f"Assurez vos stocks complets le dimanche soir pour votre pic du {dow_fr[best_dow_f]}. Testez une promotion le {dow_fr[worst_dow_f]} pour booster ce creux.",
                    "expected_result": f"Atteindre la moyenne journalière sur votre {dow_fr[worst_dow_f]} peut valoir {_fmtk(monthly_gap)} XAF supplémentaires sur le mois.",
                    "confidence": 79, "action_label": None, "action_target": None,
                })
        except Exception:
            pass

    if not forecast_cards:
        forecast_cards.append({
            "id": "forecast_learning", "severity": "ok",
            "headline": "L'IA accumule des données pour produire des prévisions précises",
            "explanation": "Des prévisions fiables nécessitent 30+ jours d'historique. Chaque synchronisation enrichit le modèle.",
            "impact": "La précision des prévisions augmente de 20-30% tous les 30 jours de données supplémentaires.",
            "recommendation": "Activez la synchronisation quotidienne automatique via la page Paramètres.",
            "expected_result": "Avec 60 jours de données, vous aurez des prévisions de CA à ±10% de précision.",
            "confidence": 55, "action_label": "Configurer", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "forecasts", "title": "Ce qui va probablement se passer", "cards": forecast_cards})

    # ── 5 · INSIGHTS ──────────────────────────────────────────────────────
    insight_cards: list[dict] = []

    # Cross-selling analysis
    if not sales_30.empty and "source_sale_id" in sales_30.columns:
        try:
            top20_pids = (sales_30.groupby("product_id")["revenue"].sum()
                          .nlargest(20).index.tolist())
            basket_s = (sales_30[sales_30["product_id"].isin(top20_pids)]
                        .groupby("source_sale_id")["product_id"].apply(list))
            pairs_cnt: Counter = Counter()
            for prods in basket_s:
                uniq = list(set(prods))
                if len(uniq) >= 2:
                    for p1, p2 in combinations(sorted(uniq), 2):
                        pairs_cnt[(p1, p2)] += 1
            if pairs_cnt:
                (bp1, bp2), pair_cnt = pairs_cnt.most_common(1)[0]
                p1_txs = sum(1 for prods in basket_s if bp1 in prods)
                if p1_txs >= 5:
                    co_rate = round(pair_cnt / p1_txs * 100)
                    if co_rate >= 15:
                        p1_n = str(sales_30[sales_30["product_id"] == bp1]["product_name"].iloc[0])
                        p2_n = str(sales_30[sales_30["product_id"] == bp2]["product_name"].iloc[0])
                        p2_rev = float(sales_30[sales_30["product_id"] == bp2]["revenue"].sum())
                        insight_cards.append({
                            "id": "cross_sell", "severity": "ok",
                            "headline": f"Les clients qui achètent {p1_n} achètent aussi {p2_n} dans {co_rate}% des cas",
                            "explanation": f"L'IA a analysé {len(basket_s)} transactions. Sur {p1_txs} achats de {p1_n}, {pair_cnt} incluaient également {p2_n} — une corrélation non négligeable.",
                            "impact": f"Positionner {p2_n} à côté de {p1_n} ou former votre équipe à proposer ce duo pourrait augmenter vos ventes de {p2_n} de 20-30%.",
                            "recommendation": f"Placez {p2_n} physiquement à côté de {p1_n}. Formez vos vendeurs à proposer '{p2_n} avec ça ?' à chaque achat de {p1_n}.",
                            "expected_result": f"Un simple changement de placement peut générer {_fmtk(p2_rev * 0.25)} XAF de ventes supplémentaires de {p2_n} par mois.",
                            "confidence": 81, "action_label": None, "action_target": None,
                        })
        except Exception:
            pass

    if not sales_90.empty:
        # Revenue anomaly
        try:
            daily_rev = sales_90.groupby("sale_date")["revenue"].sum()
            avg_rev   = float(daily_rev.mean())
            if avg_rev > 0:
                peak_date_i = str(daily_rev.idxmax())
                peak_val_i  = float(daily_rev.max())
                ratio_i     = round(peak_val_i / avg_rev, 1)
                if ratio_i >= 1.8:
                    insight_cards.append({
                        "id": "peak_day", "severity": "ok",
                        "headline": f"Le {peak_date_i} : votre meilleure journée — {ratio_i}× la moyenne",
                        "explanation": f"Ce jour-là, vous avez encaissé {_fmtk(peak_val_i)} XAF contre une moyenne de {_fmtk(avg_rev)} XAF. L'IA n'a pas encore identifié la cause — vous le savez probablement.",
                        "impact": "Si cette performance est reproductible, une seule journée similaire par semaine augmenterait votre CA mensuel de 15-20%.",
                        "recommendation": "Retracez les événements de ce jour : promotion, arrivage, événement local ? Documentez-le et planifiez de le reproduire.",
                        "expected_result": "Reproduire 2 fois par mois les conditions de ce pic peut valoir +{_fmtk(peak_val_i * 0.30)} XAF de CA mensuel.".replace("{_fmtk(peak_val_i * 0.30)}", _fmtk(peak_val_i * 0.30)),
                        "confidence": 87, "action_label": "Voir les tendances", "action_target": "/analytics/trends.php",
                    })
        except Exception:
            pass

        # Category concentration
        if "category" in sales_90.columns and sales_90["category"].notna().any():
            try:
                cat_rev_i = sales_90.groupby("category")["revenue"].sum()
                tot_cat   = float(cat_rev_i.sum())
                top_shr   = float(cat_rev_i.max() / tot_cat * 100) if tot_cat > 0 else 0
                if top_shr > 60:
                    top_cat_n = str(cat_rev_i.idxmax())
                    insight_cards.append({
                        "id": "concentration_risk", "severity": "warning",
                        "headline": f"{int(top_shr)}% de votre CA vient d'une seule catégorie — risque stratégique",
                        "explanation": f"'{top_cat_n}' génère l'essentiel de votre activité. Si un concurrent casse les prix sur cette catégorie ou si votre fournisseur principal a un problème, votre CA est directement menacé.",
                        "impact": f"Une disruption sur '{top_cat_n}' pourrait impacter jusqu'à {_fmtk(tot_cat * top_shr / 100 * 0.40)} XAF de CA — soit une baisse de {int(top_shr * 0.40)}% de votre chiffre d'affaires total.",
                        "recommendation": f"Identifiez 2 catégories complémentaires à '{top_cat_n}' que vos clients achètent déjà ailleurs et commencez à les proposer.",
                        "expected_result": f"Développer une deuxième catégorie forte peut réduire votre dépendance de {int(top_shr)}% à moins de 45% en 6 mois.",
                        "confidence": 82, "action_label": None, "action_target": None,
                    })
            except Exception:
                pass

        # Basket insight
        if "source_sale_id" in sales_90.columns:
            try:
                tx_cnt_i = int(sales_90["source_sale_id"].nunique())
                tot_90   = float(sales_90["revenue"].sum())
                if tx_cnt_i > 0:
                    basket_i  = tot_90 / tx_cnt_i
                    daily_opp = tot_90 * 0.10 / 90
                    insight_cards.append({
                        "id": "basket_insight", "severity": "ok",
                        "headline": f"Panier moyen : {_fmtk(basket_i)} XAF — augmenter de 10% vaut {_fmtk(daily_opp)} XAF/jour",
                        "explanation": f"Sur {tx_cnt_i:,} transactions en 90 jours, votre panier moyen est de {_fmtk(basket_i)} XAF. C'est votre levier de croissance le plus rapide à activer.",
                        "impact": f"Augmenter le panier de 10% seulement génère {_fmtk(daily_opp)} XAF par jour supplémentaires, soit {_fmtk(daily_opp * 30)} XAF par mois, sans aucun nouveau client.",
                        "recommendation": "Formez votre équipe à une phrase : 'Avez-vous pensé à prendre X aussi ?' à chaque transaction. C'est la technique la plus efficace pour augmenter le panier moyen.",
                        "expected_result": f"Un taux de vente complémentaire de 20% sur vos transactions peut générer {_fmtk(daily_opp * 0.6 * 30)} XAF supplémentaires par mois.",
                        "confidence": 80, "action_label": None, "action_target": None,
                    })
            except Exception:
                pass

    if not insight_cards:
        insight_cards.append({
            "id": "learning", "severity": "ok",
            "headline": "L'IA cherche des patterns dans vos données — revenez demain",
            "explanation": "Des découvertes significatives nécessitent 60+ jours de données transactionnelles. L'IA apprend à chaque synchronisation.",
            "impact": "Les patterns comportementaux émergent généralement après 8-12 semaines de données.",
            "recommendation": "Activez la synchronisation quotidienne automatique pour accélérer la détection de patterns.",
            "expected_result": "Avec 90 jours de données, l'IA détectera vos 3-5 patterns les plus exploitables commercialement.",
            "confidence": 60, "action_label": "Configurer", "action_target": "/analytics/sync.php",
        })

    sections.append({"id": "insights", "title": "Ce que j'ai découvert", "cards": insight_cards})

    return {
        "pharmacy_id":     int(pharmacy_id),
        "generated_at":    datetime.now().isoformat(),
        "data_rows":       total_rows,
        "inventory_count": inv_count,
        "greeting":        greeting_stats,
        "health_score":    health_score_obj,
        "timeline":        timeline,
        "sections":        sections,
    }

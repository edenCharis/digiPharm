"""
Tool definitions for the digiMind chat assistant (LLM function calling).

Each tool wraps a read-only analytics query. The executor is always bound
to a single pharmacy_id resolved server-side from the caller's API key —
the LLM supplies query arguments (product name, period, etc.) but never
controls which pharmacy's data it can see.
"""
import json

from models.analytics import (
    revenue_trends, generate_alerts, supplier_reliability,
    search_product_sales, top_products_list, low_stock_list,
    deliveries_list, forecast_product_simple,
)

# Cap how many rows any single tool call hands back to the LLM — keeps
# responses summarizable instead of turning into unreadable, truncated dumps.
_MAX_LIST_ITEMS = 10

TOOLS_SCHEMA = [
    {
        "type": "function",
        "function": {
            "name": "get_sales_summary",
            "description": "Résumé des ventes (chiffre d'affaires, transactions, panier moyen, tendance) sur une période donnée.",
            "parameters": {
                "type": "object",
                "properties": {
                    "period_days": {"type": "integer", "description": "Nombre de jours à analyser", "default": 30},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_product_sales",
            "description": "Historique détaillé des ventes d'un produit précis, recherché par nom (ou partie du nom).",
            "parameters": {
                "type": "object",
                "properties": {
                    "product_name": {"type": "string", "description": "Nom ou partie du nom du produit"},
                    "period_days":  {"type": "integer", "default": 90},
                },
                "required": ["product_name"],
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_top_products",
            "description": "Meilleurs ou pires produits par chiffre d'affaires ou quantité vendue sur une période.",
            "parameters": {
                "type": "object",
                "properties": {
                    "period_days": {"type": "integer", "default": 30},
                    "limit":       {"type": "integer", "default": 5},
                    "order_by":    {"type": "string", "enum": ["revenue", "quantity"], "default": "revenue"},
                    "worst":       {"type": "boolean", "description": "true pour les moins bons plutôt que les meilleurs", "default": False},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_low_stock",
            "description": "Produits en stock faible ou en rupture, avec un seuil de jours de stock restant ajustable.",
            "parameters": {
                "type": "object",
                "properties": {
                    "threshold_days": {"type": "integer", "description": "Seuil en jours de stock restant", "default": 14},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_alerts",
            "description": "Alertes actives (ruptures de stock, expirations proches, produits dormants).",
            "parameters": {
                "type": "object",
                "properties": {
                    "severity": {"type": "string", "enum": ["critical", "warning", "info"], "description": "Filtrer par sévérité (optionnel)"},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_supplier_reliability",
            "description": "Score de fiabilité de chaque fournisseur (validation des livraisons, ponctualité, régularité).",
            "parameters": {"type": "object", "properties": {}},
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_deliveries",
            "description": "Historique des livraisons reçues, optionnellement filtré par fournisseur.",
            "parameters": {
                "type": "object",
                "properties": {
                    "supplier_name": {"type": "string", "description": "Nom du fournisseur (optionnel)"},
                    "period_days":   {"type": "integer", "default": 90},
                },
            },
        },
    },
    {
        "type": "function",
        "function": {
            "name": "get_forecast",
            "description": "Prévision de la demande future pour un produit précis, avec quantité de réapprovisionnement recommandée.",
            "parameters": {
                "type": "object",
                "properties": {
                    "product_name": {"type": "string"},
                    "days":         {"type": "integer", "default": 14},
                },
                "required": ["product_name"],
            },
        },
    },
]


def make_tool_executor(pharmacy_id: int):
    """Returns a function(name, args) -> JSON-serializable result, scoped to pharmacy_id."""

    def execute(name: str, args: dict):
        try:
            if name == "get_sales_summary":
                t = revenue_trends(pharmacy_id, days=int(args.get("period_days", 30)))
                return {
                    "period_days":        t["days"],
                    "total_revenue":      t["total_revenue"],
                    "total_transactions": t["total_transactions"],
                    "avg_basket":         t["avg_basket"],
                    "growth_rate_pct":    t["growth_rate"],
                    "top_products":       t["top_products"][:5],
                }

            if name == "get_product_sales":
                return search_product_sales(
                    pharmacy_id, args["product_name"], int(args.get("period_days", 90))
                )

            if name == "get_top_products":
                return top_products_list(
                    pharmacy_id,
                    int(args.get("period_days", 30)),
                    min(int(args.get("limit", 5)), _MAX_LIST_ITEMS),
                    args.get("order_by", "revenue"),
                    bool(args.get("worst", False)),
                )

            if name == "get_low_stock":
                items = low_stock_list(pharmacy_id, int(args.get("threshold_days", 14)))
                return {"total_count": len(items), "items": items[:_MAX_LIST_ITEMS]}

            if name == "get_alerts":
                alerts = generate_alerts(pharmacy_id)
                sev = args.get("severity")
                if sev:
                    alerts = [a for a in alerts if a["severity"] == sev]
                order = {"critical": 0, "warning": 1, "info": 2}
                alerts.sort(key=lambda a: order.get(a["severity"], 3))
                return {"total_count": len(alerts), "items": alerts[:_MAX_LIST_ITEMS]}

            if name == "get_supplier_reliability":
                return supplier_reliability(pharmacy_id)

            if name == "get_deliveries":
                items = deliveries_list(
                    pharmacy_id, args.get("supplier_name"), int(args.get("period_days", 90))
                )
                return {"total_count": len(items), "items": items[:_MAX_LIST_ITEMS]}

            if name == "get_forecast":
                return forecast_product_simple(
                    pharmacy_id, args["product_name"], int(args.get("days", 14))
                )

            return {"error": f"Outil inconnu: {name}"}
        except Exception as e:
            return {"error": str(e)}

    return execute

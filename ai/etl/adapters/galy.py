"""
ETL adapter for Pharmacie Galy.

SETUP: Before first run, fill in the SCHEMA section below with Galy's
actual table/column names. Run `python -m etl.discover galy` first to
inspect the source DB and identify the right names.
"""
from typing import Optional
from datetime import date
import logging

from etl.base import ETLAdapter

logger = logging.getLogger(__name__)


# ═══════════════════════════════════════════════════════════════════════════
#  SCHEMA MAPPING — update these to match Galy's actual DB schema
#  Run: python -m etl.discover --help  to inspect the source DB
# ═══════════════════════════════════════════════════════════════════════════

SCHEMA = {
    # Table that contains individual sales transactions (header/master)
    "sales_table": "sale",
    "sales_id_col": "id",
    "sales_date_col": "saleDate",          # DATE or DATETIME

    # Table for sale line items (one row per product per sale)
    "items_table": "saleitem",
    "items_sale_fk": "saleId",            # FK to sales_table
    "items_product_fk": "productId",      # FK to products_table
    "items_quantity_col": "quantity",
    "items_unit_price_col": "unitPrice",

    # Products table
    "products_table": "product",
    "products_id_col": "id",
    "products_name_col": "name",
    "products_category_col": None,        # set to column name if exists, else None
    "products_stock_col": "stock",
    "products_cost_col": "purchasePrice",
    "products_price_col": "sellingPrice",
    "products_expiry_col": "expiryDate",
    "products_active_col": "statut",      # 1 = active, None if no such column
    "products_active_val": 1,

    # If pharmacy_id doesn't exist in Galy's DB, set to None (single-tenant)
    "products_pharmacy_col": None,
}
# ═══════════════════════════════════════════════════════════════════════════


class GalyAdapter(ETLAdapter):
    adapter_name = "galy"

    def sync_sales(self, since_date: Optional[str] = None) -> int:
        s = SCHEMA
        since = since_date or "2000-01-01"
        date_cast = f"DATE({s['sales_date_col']})"

        # Build category join/select
        cat_select = f"NULL AS category"
        if s["products_category_col"]:
            cat_select = f"p.{s['products_category_col']} AS category"

        sql = f"""
            SELECT
                {s['items_sale_fk']} AS sale_id,
                {date_cast}          AS sale_date,
                i.{s['items_product_fk']}    AS product_id,
                p.{s['products_name_col']}   AS product_name,
                {cat_select},
                i.{s['items_quantity_col']}   AS quantity,
                i.{s['items_unit_price_col']} AS unit_price,
                i.{s['items_quantity_col']} * i.{s['items_unit_price_col']} AS revenue,
                p.{s['products_cost_col']}   AS cost
            FROM {s['items_table']} i
            JOIN {s['sales_table']}   sl ON sl.{s['sales_id_col']} = i.{s['items_sale_fk']}
            JOIN {s['products_table']} p ON p.{s['products_id_col']} = i.{s['items_product_fk']}
            WHERE {date_cast} >= %s
            ORDER BY sale_date
        """

        with self.source.cursor() as cur:
            cur.execute(sql, (since,))
            rows = cur.fetchall()
            columns = [d[0] for d in cur.description]

        count = 0
        for raw in rows:
            row = dict(zip(columns, raw))
            self.upsert_sale({
                "sale_date":      str(row["sale_date"]),
                "product_id":     row["product_id"],
                "product_name":   row.get("product_name"),
                "category":       row.get("category"),
                "quantity":       float(row.get("quantity") or 0),
                "unit_price":     float(row.get("unit_price") or 0),
                "revenue":        float(row.get("revenue") or 0),
                "cost":           float(row["cost"]) if row.get("cost") else None,
                "source_sale_id": f"{row['sale_id']}",
            })
            count += 1
            if count % 500 == 0:
                self.analytics.commit()
                logger.info(f"[galy] {count} sales rows synced…")

        return count

    def sync_inventory(self) -> int:
        s = SCHEMA
        today = str(date.today())

        # Build WHERE clause for active filter
        active_where = ""
        if s["products_active_col"]:
            active_where = f"WHERE p.{s['products_active_col']} = {s['products_active_val']}"

        cat_select = "NULL AS category"
        if s["products_category_col"]:
            cat_select = f"p.{s['products_category_col']} AS category"

        sql = f"""
            SELECT
                p.{s['products_id_col']}   AS product_id,
                p.{s['products_name_col']} AS product_name,
                {cat_select},
                p.{s['products_stock_col']}  AS stock_quantity,
                p.{s['products_cost_col']}   AS unit_cost,
                p.{s['products_price_col']}  AS unit_price,
                p.{s['products_expiry_col']} AS expiry_date
            FROM {s['products_table']} p
            {active_where}
        """

        with self.source.cursor() as cur:
            cur.execute(sql)
            rows = cur.fetchall()
            columns = [d[0] for d in cur.description]

        count = 0
        for raw in rows:
            row = dict(zip(columns, raw))
            self.upsert_inventory({
                "product_id":     row["product_id"],
                "product_name":   row.get("product_name"),
                "category":       row.get("category"),
                "stock_quantity": float(row.get("stock_quantity") or 0),
                "unit_cost":      float(row["unit_cost"]) if row.get("unit_cost") else None,
                "unit_price":     float(row["unit_price"]) if row.get("unit_price") else None,
                "expiry_date":    str(row["expiry_date"]) if row.get("expiry_date") else None,
            }, snapshot_date=today)
            count += 1

        return count

"""
Base ETL adapter. All pharmacy adapters extend this class.
"""
from abc import ABC, abstractmethod
from datetime import datetime
from typing import Optional
import logging

logger = logging.getLogger(__name__)


class ETLAdapter(ABC):
    """
    Abstract base for pharmacy→analytics ETL.

    Subclasses implement sync_sales() and sync_inventory() using whatever
    schema their source system has. The base class handles run orchestration
    and logging to ai_etl_runs.
    """

    adapter_name: str = "base"

    def __init__(self, pharmacy_id: int, analytics_conn, source_conn):
        self.pharmacy_id = pharmacy_id
        self.analytics = analytics_conn   # pymysql connection → digipharmai_db
        self.source = source_conn         # pymysql connection → source pharmacy DB

    # ── To implement ──────────────────────────────────────────────────────

    @abstractmethod
    def sync_sales(self, since_date: Optional[str] = None) -> int:
        """
        Pull sales from the source DB into ai_sales.
        since_date: ISO date string — only sync records on or after this date.
                    None means full sync (first run).
        Returns number of rows upserted.
        """
        ...

    @abstractmethod
    def sync_inventory(self) -> int:
        """
        Take a current inventory snapshot into ai_inventory.
        Returns number of rows upserted.
        """
        ...

    # ── Helpers ───────────────────────────────────────────────────────────

    def upsert_sale(self, row: dict) -> None:
        """
        Insert or ignore a single normalized sale row into ai_sales.
        row must have: sale_date, product_id, product_name, category,
                       quantity, unit_price, revenue, cost (opt), source_sale_id
        """
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_sales
                    (pharmacy_id, sale_date, product_id, product_name, category,
                     quantity, unit_price, revenue, cost, source_sale_id)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    quantity   = VALUES(quantity),
                    revenue    = VALUES(revenue),
                    unit_price = VALUES(unit_price)
            """, (
                self.pharmacy_id,
                row["sale_date"],
                str(row["product_id"]),
                row.get("product_name"),
                row.get("category"),
                row.get("quantity", 0),
                row.get("unit_price", 0),
                row.get("revenue", 0),
                row.get("cost"),
                str(row["source_sale_id"]),
            ))

    def upsert_inventory(self, row: dict, snapshot_date: str) -> None:
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_inventory
                    (pharmacy_id, snapshot_date, product_id, product_name, category,
                     stock_quantity, unit_cost, unit_price, expiry_date)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    stock_quantity = VALUES(stock_quantity),
                    unit_cost      = VALUES(unit_cost),
                    unit_price     = VALUES(unit_price),
                    expiry_date    = VALUES(expiry_date)
            """, (
                self.pharmacy_id,
                snapshot_date,
                str(row["product_id"]),
                row.get("product_name"),
                row.get("category"),
                row.get("stock_quantity", 0),
                row.get("unit_cost"),
                row.get("unit_price"),
                row.get("expiry_date"),
            ))

    def get_last_sync_date(self) -> Optional[str]:
        with self.analytics.cursor() as cur:
            cur.execute("""
                SELECT last_synced_date FROM ai_etl_runs
                WHERE pharmacy_id = %s AND status IN ('success','partial')
                ORDER BY run_at DESC LIMIT 1
            """, (self.pharmacy_id,))
            row = cur.fetchone()
            return str(row[0]) if row and row[0] else None

    def _log_run(self, status: str, rows: int, last_date: Optional[str],
                 error: Optional[str], duration: float) -> None:
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_etl_runs
                    (pharmacy_id, adapter, status, rows_synced, last_synced_date,
                     error_message, duration_sec)
                VALUES (%s,%s,%s,%s,%s,%s,%s)
            """, (self.pharmacy_id, self.adapter_name, status, rows,
                  last_date, error, round(duration, 2)))
        self.analytics.commit()

    # ── Run orchestration ─────────────────────────────────────────────────

    def run(self, full_sync: bool = False) -> dict:
        t0 = datetime.now()
        sales_n = inv_n = 0
        status = "success"
        error = None
        last_date = None

        try:
            since = None if full_sync else self.get_last_sync_date()
            logger.info(f"[ETL:{self.adapter_name}] pharmacy_id={self.pharmacy_id} since={since}")

            sales_n = self.sync_sales(since_date=since)
            self.analytics.commit()

            inv_n = self.sync_inventory()
            self.analytics.commit()

            # Record the most recent date we synced
            with self.analytics.cursor() as cur:
                cur.execute(
                    "SELECT MAX(sale_date) FROM ai_sales WHERE pharmacy_id=%s",
                    (self.pharmacy_id,)
                )
                row = cur.fetchone()
                last_date = str(row[0]) if row and row[0] else None

            logger.info(f"[ETL:{self.adapter_name}] Done — sales={sales_n} inventory={inv_n}")

        except Exception as exc:
            self.analytics.rollback()
            status = "failed"
            error = str(exc)
            logger.exception(f"[ETL:{self.adapter_name}] Failed")

        duration = (datetime.now() - t0).total_seconds()
        self._log_run(status, sales_n + inv_n, last_date, error, duration)

        return {
            "pharmacy_id":       self.pharmacy_id,
            "adapter":           self.adapter_name,
            "status":            status,
            "sales_synced":      sales_n,
            "inventory_synced":  inv_n,
            "duration_seconds":  round(duration, 2),
            "error":             error,
        }

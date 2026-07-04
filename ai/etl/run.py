"""
ETL runner — reads connection config from digipharmai_db.ai_data_sources.
No hardcoded credentials — everything is configured via the analytics dashboard.

Usage:
    python -m etl.run --pharmacy galy [--full]
    python -m etl.run --all           # sync all active pharmacies
    python -m etl.run --list

Cron (daily 2am):
    0 2 * * * cd /var/www/digipharma/ai && venv/bin/python -m etl.run --all >> /var/log/digipharmai-etl.log 2>&1
"""
import argparse
import sys
import os
import json
import logging
import hashlib
import base64

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

from dotenv import load_dotenv
load_dotenv()

import pymysql
from core.config import DB_HOST, DB_PORT, DB_USER, DB_PASSWORD

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)

ANALYTICS_DB = os.getenv("ANALYTICS_DB_NAME", "digipharmai_db")


# ── Decryption (mirror of PHP ai_decrypt) ────────────────────────────────

def _decrypt(ciphertext: str) -> str:
    if not ciphertext:
        return ""
    try:
        from Crypto.Cipher import AES
        from Crypto.Util.Padding import unpad
        enc_key = os.getenv("ENCRYPTION_KEY", "digipharmai_fallback_key_change_me")
        key = hashlib.sha256(enc_key.encode()).digest()[:32]
        raw = base64.b64decode(ciphertext)
        iv  = raw[:16]
        ct  = raw[16:]
        cipher = AES.new(key, AES.MODE_CBC, iv)
        return unpad(cipher.decrypt(ct), AES.block_size).decode()
    except Exception as e:
        logger.error(f"Decrypt failed: {e}")
        return ""


# ── DB connections ────────────────────────────────────────────────────────

def analytics_connect() -> pymysql.Connection:
    return pymysql.connect(
        host=DB_HOST, port=DB_PORT,
        user=DB_USER, password=DB_PASSWORD,
        database=ANALYTICS_DB, charset="utf8mb4",
        autocommit=False,
    )


def source_connect_ssh(src: dict) -> tuple:
    from sshtunnel import SSHTunnelForwarder
    ssh_pass = _decrypt(src.get("ssh_password") or "")
    db_pass  = _decrypt(src.get("db_password")  or "")

    auth = {}
    if ssh_pass:
        auth["ssh_password"] = ssh_pass
    else:
        key_path = "/root/.ssh/id_rsa"
        if os.path.exists(key_path):
            auth["ssh_pkey"] = key_path
        else:
            raise RuntimeError("No SSH auth configured (no password, no key file)")

    tunnel = SSHTunnelForwarder(
        (src["ssh_host"], int(src.get("ssh_port") or 22)),
        ssh_username=src.get("ssh_user") or "root",
        remote_bind_address=(src.get("db_host") or "127.0.0.1", int(src.get("db_port") or 3306)),
        **auth,
    )
    tunnel.start()
    logger.info(f"SSH tunnel → {src['ssh_host']} local_port={tunnel.local_bind_port}")

    conn = pymysql.connect(
        host="127.0.0.1",
        port=tunnel.local_bind_port,
        user=src.get("db_user") or "root",
        password=db_pass,
        database=src["db_name"],
        charset="utf8mb4",
        autocommit=False,
    )
    return conn, tunnel


def source_connect_direct(src: dict) -> tuple:
    db_pass = _decrypt(src.get("db_password") or "")
    conn = pymysql.connect(
        host=src.get("db_host") or "127.0.0.1",
        port=int(src.get("db_port") or 3306),
        user=src.get("db_user") or "root",
        password=db_pass,
        database=src["db_name"],
        charset="utf8mb4",
        autocommit=False,
    )
    return conn, None


# ── Load sources from DB ──────────────────────────────────────────────────

def load_sources(slug: str | None = None) -> list[dict]:
    conn = analytics_connect()
    try:
        with conn.cursor(pymysql.cursors.DictCursor) as cur:
            if slug:
                cur.execute("""
                    SELECT ds.*, p.slug, p.name AS pharmacy_name
                    FROM ai_data_sources ds
                    JOIN ai_pharmacies p ON p.id = ds.pharmacy_id
                    WHERE p.slug = %s AND ds.is_active = 1
                    LIMIT 1
                """, (slug,))
            else:
                cur.execute("""
                    SELECT ds.*, p.slug, p.name AS pharmacy_name
                    FROM ai_data_sources ds
                    JOIN ai_pharmacies p ON p.id = ds.pharmacy_id
                    WHERE ds.is_active = 1
                """)
            return cur.fetchall()
    finally:
        conn.close()


def list_pharmacies():
    conn = analytics_connect()
    try:
        with conn.cursor(pymysql.cursors.DictCursor) as cur:
            cur.execute("""
                SELECT p.slug, p.name, ds.conn_type, ds.db_name,
                       ds.last_synced_at, ds.last_test_ok
                FROM ai_pharmacies p
                LEFT JOIN ai_data_sources ds ON ds.pharmacy_id = p.id
                WHERE p.is_active = 1
            """)
            return cur.fetchall()
    finally:
        conn.close()


# ── Dynamic adapter ───────────────────────────────────────────────────────

class DynamicAdapter:
    """
    Adapter built at runtime from ai_data_sources.schema_map.
    No Python code changes needed — schema is configured in the UI.
    """
    adapter_name = "dynamic"

    def __init__(self, pharmacy_id: int, analytics_conn, source_conn, schema: dict):
        self.pharmacy_id = pharmacy_id
        self.analytics   = analytics_conn
        self.source      = source_conn
        self.schema      = schema

    def _upsert_sale(self, row: dict):
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_sales
                    (pharmacy_id, sale_date, product_id, product_name, category,
                     quantity, unit_price, revenue, cost, source_sale_id)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    quantity=VALUES(quantity), revenue=VALUES(revenue), unit_price=VALUES(unit_price)
            """, (
                self.pharmacy_id, row["sale_date"], str(row["product_id"]),
                row.get("product_name"), row.get("category"),
                float(row.get("quantity") or 0), float(row.get("unit_price") or 0),
                float(row.get("revenue") or 0), float(row["cost"]) if row.get("cost") else None,
                str(row["source_sale_id"]),
            ))

    def _upsert_inventory(self, row: dict, snapshot_date: str):
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_inventory
                    (pharmacy_id, snapshot_date, product_id, product_name, category,
                     stock_quantity, unit_cost, unit_price, expiry_date)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    stock_quantity=VALUES(stock_quantity), unit_cost=VALUES(unit_cost),
                    unit_price=VALUES(unit_price), expiry_date=VALUES(expiry_date)
            """, (
                self.pharmacy_id, snapshot_date, str(row["product_id"]),
                row.get("product_name"), row.get("category"),
                float(row.get("stock_quantity") or 0),
                float(row["unit_cost"]) if row.get("unit_cost") else None,
                float(row["unit_price"]) if row.get("unit_price") else None,
                str(row["expiry_date"]) if row.get("expiry_date") else None,
            ))

    def get_last_sync_date(self) -> str | None:
        with self.analytics.cursor() as cur:
            cur.execute("""
                SELECT last_synced_date FROM ai_etl_runs
                WHERE pharmacy_id=%s AND status IN ('success','partial')
                ORDER BY run_at DESC LIMIT 1
            """, (self.pharmacy_id,))
            row = cur.fetchone()
            return str(row[0]) if row and row[0] else None

    def sync_sales(self, since_date: str | None = None) -> int:
        s = self.schema
        since = since_date or "2000-01-01"
        date_cast = f"DATE(sl.{s['sales_date_col']})"

        cat_sel = "NULL AS category"
        if s.get("products_category_col"):
            cat_sel = f"p.{s['products_category_col']} AS category"

        sql = f"""
            SELECT
                i.{s['items_sale_fk']}        AS sale_id,
                {date_cast}                    AS sale_date,
                i.{s['items_product_fk']}      AS product_id,
                p.{s['products_name_col']}     AS product_name,
                {cat_sel},
                i.{s['items_quantity_col']}    AS quantity,
                i.{s['items_unit_price_col']}  AS unit_price,
                i.{s['items_quantity_col']} * i.{s['items_unit_price_col']} AS revenue,
                p.{s['products_cost_col']}     AS cost
            FROM {s['items_table']} i
            JOIN {s['sales_table']}   sl ON sl.{s['sales_id_col']} = i.{s['items_sale_fk']}
            JOIN {s['products_table']} p ON p.{s['products_id_col']} = i.{s['items_product_fk']}
            WHERE {date_cast} >= %s
            ORDER BY sale_date
        """
        with self.source.cursor() as cur:
            cur.execute(sql, (since,))
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()

        count = 0
        for raw in rows:
            row = dict(zip(cols, raw))
            self._upsert_sale({
                "sale_date":      str(row["sale_date"]),
                "product_id":     row["product_id"],
                "product_name":   row.get("product_name"),
                "category":       row.get("category"),
                "quantity":       float(row.get("quantity") or 0),
                "unit_price":     float(row.get("unit_price") or 0),
                "revenue":        float(row.get("revenue") or 0),
                "cost":           float(row["cost"]) if row.get("cost") else None,
                "source_sale_id": str(row["sale_id"]),
            })
            count += 1
            if count % 500 == 0:
                self.analytics.commit()
                logger.info(f"  {count} lignes…")
        return count

    def sync_inventory(self) -> int:
        from datetime import date
        s = self.schema
        today = str(date.today())

        cat_sel = "NULL AS category"
        if s.get("products_category_col"):
            cat_sel = f"p.{s['products_category_col']} AS category"

        sql = f"""
            SELECT
                p.{s['products_id_col']}    AS product_id,
                p.{s['products_name_col']}  AS product_name,
                {cat_sel},
                p.{s['products_stock_col']} AS stock_quantity,
                p.{s['products_cost_col']}  AS unit_cost,
                p.{s['products_price_col']}  AS unit_price,
                p.{s['products_expiry_col']} AS expiry_date
            FROM {s['products_table']} p
        """
        with self.source.cursor() as cur:
            cur.execute(sql)
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()

        count = 0
        for raw in rows:
            row = dict(zip(cols, raw))
            self._upsert_inventory({
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

    def run(self, full_sync: bool = False) -> dict:
        from datetime import datetime
        t0     = datetime.now()
        sales  = inv = 0
        status = "success"
        error  = None
        last_date = None

        try:
            since = None if full_sync else self.get_last_sync_date()
            logger.info(f"[ETL] pharmacy_id={self.pharmacy_id} since={since}")
            sales = self.sync_sales(since_date=since)
            self.analytics.commit()
            inv = self.sync_inventory()
            self.analytics.commit()

            with self.analytics.cursor() as cur:
                cur.execute("SELECT MAX(sale_date) FROM ai_sales WHERE pharmacy_id=%s", (self.pharmacy_id,))
                row = cur.fetchone()
                last_date = str(row[0]) if row and row[0] else None
        except Exception as exc:
            self.analytics.rollback()
            status = "failed"
            error = str(exc)
            logger.exception("[ETL] Failed")

        duration = (datetime.now() - t0).total_seconds()

        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_etl_runs (pharmacy_id, adapter, status, rows_synced, last_synced_date, error_message, duration_sec)
                VALUES (%s,'dynamic',%s,%s,%s,%s,%s)
            """, (self.pharmacy_id, status, sales + inv, last_date, error, round(duration, 2)))
        self.analytics.commit()

        if status == "success":
            with self.analytics.cursor() as cur:
                cur.execute("""
                    UPDATE ai_data_sources
                    SET last_synced_at=NOW(), last_sync_rows=%s
                    WHERE pharmacy_id=%s
                """, (sales + inv, self.pharmacy_id))
            self.analytics.commit()

        return {"pharmacy_id": self.pharmacy_id, "status": status,
                "sales_synced": sales, "inventory_synced": inv,
                "duration_seconds": round(duration, 2), "error": error}


# ── Main ──────────────────────────────────────────────────────────────────

def run_pharmacy(src: dict, full_sync: bool = False):
    pharmacy_id = int(src["pharmacy_id"])
    schema_raw  = src.get("schema_map")
    schema      = json.loads(schema_raw) if isinstance(schema_raw, str) else (schema_raw or {})

    analytics_conn = analytics_connect()
    source_conn    = tunnel = None

    try:
        if src.get("conn_type") == "ssh":
            source_conn, tunnel = source_connect_ssh(src)
        else:
            source_conn, tunnel = source_connect_direct(src)

        adapter = DynamicAdapter(pharmacy_id, analytics_conn, source_conn, schema)
        result  = adapter.run(full_sync=full_sync)

    except Exception as exc:
        result = {"pharmacy_id": pharmacy_id, "status": "failed", "error": str(exc),
                  "sales_synced": 0, "inventory_synced": 0, "duration_seconds": 0}
        logger.error(f"[ETL] {src.get('pharmacy_name')} failed: {exc}")
    finally:
        if source_conn:
            source_conn.close()
        if tunnel:
            tunnel.stop()
        analytics_conn.close()

    return result


def main():
    parser = argparse.ArgumentParser(description="DigiPharm AI ETL runner")
    parser.add_argument("--pharmacy", help="Pharmacy slug (e.g. galy)")
    parser.add_argument("--all",  action="store_true", help="Sync all active pharmacies")
    parser.add_argument("--full", action="store_true", help="Full sync (ignore last date)")
    parser.add_argument("--list", action="store_true", help="List configured pharmacies")
    args = parser.parse_args()

    if args.list:
        for p in list_pharmacies():
            ok = "✓" if p.get("last_test_ok") else "?"
            print(f"  {p['slug']:<20} {p['name']:<30} mode={p.get('conn_type','?')} db={p.get('db_name','?')} [{ok}]")
        return

    if args.all:
        sources = load_sources()
    elif args.pharmacy:
        sources = load_sources(args.pharmacy)
        if not sources:
            logger.error(f"No active source found for slug '{args.pharmacy}'")
            sys.exit(1)
    else:
        parser.error("--pharmacy <slug> or --all required")

    any_failed = False
    for src in sources:
        logger.info(f"=== Syncing: {src.get('pharmacy_name')} ===")
        result = run_pharmacy(src, full_sync=args.full)
        if result["status"] == "failed":
            logger.error(f"FAILED: {result['error']}")
            any_failed = True
        else:
            logger.info(f"OK — sales={result['sales_synced']} inventory={result['inventory_synced']} {result['duration_seconds']}s")

    if any_failed:
        sys.exit(1)


if __name__ == "__main__":
    main()

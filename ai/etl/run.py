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
    import paramiko, socket, select, threading

    KEY_PATHS = [
        "/var/www/digipharma/ai/.ssh/id_rsa",
        "/root/.ssh/id_rsa",
    ]

    ssh_pass = _decrypt(src.get("ssh_password") or "")
    db_pass  = _decrypt(src.get("db_password")  or "")

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    connect_kw: dict = dict(
        port=int(src.get("ssh_port") or 22),
        username=src.get("ssh_user") or "root",
        timeout=15,
        allow_agent=False,
        look_for_keys=False,
        banner_timeout=30,
    )
    if ssh_pass:
        connect_kw["password"] = ssh_pass
    else:
        key_path = next((p for p in KEY_PATHS if os.path.exists(p)), None)
        if key_path:
            connect_kw["key_filename"] = key_path
        else:
            raise RuntimeError("Aucune authentification SSH configurée (ni mot de passe, ni clé)")

    client.connect(src["ssh_host"], **connect_kw)
    transport = client.get_transport()

    remote_host = src.get("db_host") or "127.0.0.1"
    remote_port = int(src.get("db_port") or 3306)

    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(("127.0.0.1", 0))
    local_port = srv.getsockname()[1]
    srv.listen(1)
    srv.settimeout(15)

    def _forward():
        lconn = chan = None
        try:
            lconn, addr = srv.accept()
            lconn.settimeout(None)
            chan = transport.open_channel("direct-tcpip", (remote_host, remote_port), addr)
            while True:
                r, _, _ = select.select([lconn, chan], [], [], 1.0)
                if lconn in r:
                    d = lconn.recv(8192)
                    if not d: break
                    chan.sendall(d)
                if chan in r:
                    if chan.closed or (not chan.recv_ready() and chan.eof_received): break
                    d = chan.recv(8192)
                    if not d: break
                    lconn.sendall(d)
        except Exception:
            pass
        finally:
            for obj in (lconn, chan, srv):
                try: obj.close()
                except Exception: pass

    threading.Thread(target=_forward, daemon=True).start()
    logger.info(f"SSH tunnel → {src['ssh_host']} local_port={local_port}")

    conn = pymysql.connect(
        host="127.0.0.1",
        port=local_port,
        user=src.get("db_user") or "root",
        password=db_pass,
        database=src["db_name"],
        charset="utf8mb4",
        autocommit=False,
    )
    return conn, client  # client.close() instead of tunnel.stop()


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
                None if not row.get("expiry_date") or str(row["expiry_date"]).startswith("0000") else str(row["expiry_date"]),
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

    # ── Supplier sync ─────────────────────────────────────────────────────

    def _upsert_supplier(self, row: dict):
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_suppliers
                    (pharmacy_id, source_supplier_id, name, contact, synced_at)
                VALUES (%s,%s,%s,%s,NOW())
                ON DUPLICATE KEY UPDATE
                    name=VALUES(name), contact=VALUES(contact), synced_at=NOW()
            """, (
                self.pharmacy_id,
                str(row["supplier_id"]),
                row.get("supplier_name") or "",
                row.get("contact"),
            ))

    def sync_suppliers(self) -> int:
        s = self.schema
        if not s.get("supplier_table"):
            return 0
        contact_col = f"sup.{s['supplier_contact_col']}" if s.get("supplier_contact_col") else "NULL"
        sql = f"""
            SELECT
                sup.{s['supplier_id_col']}   AS supplier_id,
                sup.{s['supplier_name_col']} AS supplier_name,
                {contact_col}                AS contact
            FROM {s['supplier_table']} sup
        """
        with self.source.cursor() as cur:
            cur.execute(sql)
            cols = [d[0] for d in cur.description]
            rows = cur.fetchall()
        count = 0
        for raw in rows:
            row = dict(zip(cols, raw))
            self._upsert_supplier({
                "supplier_id":   row["supplier_id"],
                "supplier_name": row.get("supplier_name"),
                "contact":       row.get("contact"),
            })
            count += 1
        return count

    # ── Delivery sync ─────────────────────────────────────────────────────

    def _upsert_delivery(self, row: dict) -> int | None:
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_deliveries
                    (pharmacy_id, source_delivery_id, supplier_id, supplier_name,
                     delivery_date, status, source_created_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    supplier_name=VALUES(supplier_name),
                    delivery_date=VALUES(delivery_date),
                    status=VALUES(status)
            """, (
                self.pharmacy_id,
                str(row["delivery_id"]),
                str(row.get("supplier_id") or ""),
                row.get("supplier_name") or "",
                row.get("delivery_date"),
                row.get("status") or "unknown",
                row.get("created_at"),
            ))
            cur.execute("""
                SELECT id FROM ai_deliveries
                WHERE pharmacy_id=%s AND source_delivery_id=%s
            """, (self.pharmacy_id, str(row["delivery_id"])))
            r = cur.fetchone()
            return r[0] if r else None

    def _upsert_delivery_item(self, delivery_analytics_id: int, source_delivery_id: str, row: dict):
        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_delivery_items
                    (pharmacy_id, delivery_id, source_delivery_id, product_id,
                     product_name, quantity, price_cession, public_price,
                     validated, source_created_at)
                VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
                ON DUPLICATE KEY UPDATE
                    quantity=VALUES(quantity),
                    price_cession=VALUES(price_cession),
                    validated=VALUES(validated)
            """, (
                self.pharmacy_id,
                delivery_analytics_id,
                source_delivery_id,
                str(row.get("product_id") or ""),
                row.get("product_name") or "",
                float(row.get("quantity") or 0),
                float(row["price_cession"]) if row.get("price_cession") is not None else None,
                float(row["public_price"]) if row.get("public_price") is not None else None,
                int(bool(row.get("validated"))),
                row.get("created_at"),
            ))

    def sync_deliveries(self) -> int:
        s = self.schema
        if not s.get("delivery_table"):
            return 0

        pname_col = "NULL AS product_name"
        pname_join = ""
        if s.get("products_table") and s.get("products_name_col") and s.get("products_id_col"):
            pname_col  = f"p.{s['products_name_col']} AS product_name"
            pname_join = f"LEFT JOIN {s['products_table']} p ON p.{s['products_id_col']} = di.{s['di_product_fk']}"

        pub_price_col = f"di.{s['di_public_price_col']} AS public_price" if s.get("di_public_price_col") else "NULL AS public_price"

        sql_d = f"""
            SELECT
                d.{s['delivery_id_col']}       AS delivery_id,
                d.{s['delivery_supplier_fk']}  AS supplier_id,
                sup.{s['supplier_name_col']}   AS supplier_name,
                DATE(d.{s['delivery_date_col']}) AS delivery_date,
                d.{s['delivery_status_col']}   AS status,
                d.createdAt                     AS created_at
            FROM {s['delivery_table']} d
            LEFT JOIN {s['supplier_table']} sup
                ON sup.{s['supplier_id_col']} = d.{s['delivery_supplier_fk']}
            ORDER BY d.createdAt
        """

        sql_di = f"""
            SELECT
                di.{s['di_delivery_fk']}   AS source_delivery_id,
                di.{s['di_product_fk']}    AS product_id,
                {pname_col},
                di.{s['di_quantity_col']}  AS quantity,
                di.{s['di_price_col']}     AS price_cession,
                {pub_price_col},
                di.{s['di_validated_col']} AS validated,
                di.createdAt               AS created_at
            FROM {s['delivery_items_table']} di
            {pname_join}
        """

        with self.source.cursor() as cur:
            cur.execute(sql_d)
            d_cols = [c[0] for c in cur.description]
            deliveries = [dict(zip(d_cols, r)) for r in cur.fetchall()]

        with self.source.cursor() as cur:
            cur.execute(sql_di)
            di_cols = [c[0] for c in cur.description]
            all_items = [dict(zip(di_cols, r)) for r in cur.fetchall()]

        items_by_delivery: dict[str, list] = {}
        for item in all_items:
            key = str(item["source_delivery_id"])
            items_by_delivery.setdefault(key, []).append(item)

        count = 0
        for raw_d in deliveries:
            src_id = str(raw_d["delivery_id"])
            analytics_id = self._upsert_delivery({
                "delivery_id":   src_id,
                "supplier_id":   raw_d.get("supplier_id"),
                "supplier_name": raw_d.get("supplier_name"),
                "delivery_date": str(raw_d["delivery_date"]) if raw_d.get("delivery_date") else None,
                "status":        raw_d.get("status"),
                "created_at":    raw_d.get("created_at"),
            })
            if analytics_id is None:
                continue
            for item in items_by_delivery.get(src_id, []):
                self._upsert_delivery_item(analytics_id, src_id, item)
            count += 1
            if count % 200 == 0:
                self.analytics.commit()
                logger.info(f"  {count} livraisons…")
        return count

    # ── Orchestrator ──────────────────────────────────────────────────────

    def run(self, full_sync: bool = False) -> dict:
        from datetime import datetime
        t0        = datetime.now()
        sales     = inv = suppliers = deliveries = 0
        status    = "success"
        error     = None
        last_date = None

        try:
            since = None if full_sync else self.get_last_sync_date()
            logger.info(f"[ETL] pharmacy_id={self.pharmacy_id} since={since}")
            sales = self.sync_sales(since_date=since)
            self.analytics.commit()
            inv = self.sync_inventory()
            self.analytics.commit()
            suppliers = self.sync_suppliers()
            self.analytics.commit()
            deliveries = self.sync_deliveries()
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
        total_rows = sales + inv + suppliers + deliveries

        with self.analytics.cursor() as cur:
            cur.execute("""
                INSERT INTO ai_etl_runs (pharmacy_id, adapter, status, rows_synced, last_synced_date, error_message, duration_sec)
                VALUES (%s,'dynamic',%s,%s,%s,%s,%s)
            """, (self.pharmacy_id, status, total_rows, last_date, error, round(duration, 2)))
        self.analytics.commit()

        if status == "success":
            with self.analytics.cursor() as cur:
                cur.execute("""
                    UPDATE ai_data_sources
                    SET last_synced_at=NOW(), last_sync_rows=%s
                    WHERE pharmacy_id=%s
                """, (total_rows, self.pharmacy_id))
            self.analytics.commit()

        return {
            "pharmacy_id":        self.pharmacy_id,
            "status":             status,
            "sales_synced":       sales,
            "inventory_synced":   inv,
            "suppliers_synced":   suppliers,
            "deliveries_synced":  deliveries,
            "duration_seconds":   round(duration, 2),
            "error":              error,
        }


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
            try:
                tunnel.stop()
            except AttributeError:
                tunnel.close()  # paramiko SSHClient
        analytics_conn.close()

    return result


def main():
    parser = argparse.ArgumentParser(description="digiMind ETL runner")
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

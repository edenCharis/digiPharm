"""
ETL runner — sync a pharmacy's data into digipharmai_db.

Usage:
    python -m etl.run --pharmacy galy [--full]
    python -m etl.run --list

Cron (daily 2am):
    0 2 * * * cd /var/www/digipharma/ai && venv/bin/python -m etl.run --pharmacy galy >> /var/log/digipharmai-etl.log 2>&1

Connection modes per pharmacy:
  - local:  pymysql direct (same MySQL server)
  - ssh:    SSH tunnel via sshtunnel (remote server, MySQL not exposed)
  - agent:  not used by this script — the remote pharmacy pushes data instead
"""
import argparse
import sys
import os
import logging
sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

import pymysql
from core.config import DB_HOST, DB_PORT, DB_USER, DB_PASSWORD
from dotenv import load_dotenv
load_dotenv()

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s %(message)s",
    datefmt="%Y-%m-%d %H:%M:%S",
)
logger = logging.getLogger(__name__)


# ── Adapter registry ──────────────────────────────────────────────────────
# For each pharmacy:
#   class         : dotted path to ETLAdapter subclass
#   pharmacy_id   : id in digipharmai_db.ai_pharmacies
#   connection    : "local" | "ssh"
#   source_db     : MySQL database name on the source server
#
# SSH-mode extra keys (read from env — never hardcode credentials):
#   ssh_host, ssh_port, ssh_user, ssh_key_path (or ssh_password)
#   remote_db_host (usually 127.0.0.1), remote_db_port
#
ADAPTERS: dict[str, dict] = {
    "galy": {
        "class":         "etl.adapters.galy.GalyAdapter",
        "pharmacy_id":   int(os.getenv("GALY_PHARMACY_ID", "1")),
        "connection":    os.getenv("GALY_CONN_MODE", "ssh"),  # "local" or "ssh"
        "source_db":     os.getenv("GALY_DB_NAME", "pharmacie_galy"),
        # SSH tunnel settings (used when connection == "ssh")
        "ssh_host":      os.getenv("GALY_SSH_HOST", ""),
        "ssh_port":      int(os.getenv("GALY_SSH_PORT", "22")),
        "ssh_user":      os.getenv("GALY_SSH_USER", "root"),
        "ssh_key_path":  os.getenv("GALY_SSH_KEY", "/root/.ssh/id_rsa"),
        "ssh_password":  os.getenv("GALY_SSH_PASSWORD", ""),   # set if no key
        # MySQL on the remote server (from the perspective of the remote host)
        "remote_db_host": os.getenv("GALY_REMOTE_DB_HOST", "127.0.0.1"),
        "remote_db_port": int(os.getenv("GALY_REMOTE_DB_PORT", "3306")),
        "remote_db_user": os.getenv("GALY_REMOTE_DB_USER", DB_USER),
        "remote_db_pass": os.getenv("GALY_REMOTE_DB_PASS", DB_PASSWORD),
    },
}

ANALYTICS_DB = os.getenv("ANALYTICS_DB_NAME", "digipharmai_db")


def load_class(dotted: str):
    module, cls = dotted.rsplit(".", 1)
    import importlib
    return getattr(importlib.import_module(module), cls)


def connect_local(db_name: str):
    return pymysql.connect(
        host=DB_HOST, port=DB_PORT,
        user=DB_USER, password=DB_PASSWORD,
        database=db_name, charset="utf8mb4",
        autocommit=False,
    )


def connect_ssh(cfg: dict):
    """Open an SSH tunnel and return (pymysql connection, tunnel) — close both after use."""
    try:
        from sshtunnel import SSHTunnelForwarder
    except ImportError:
        raise RuntimeError("sshtunnel not installed — run: pip install sshtunnel")

    auth = {}
    if cfg.get("ssh_key_path") and os.path.exists(cfg["ssh_key_path"]):
        auth["ssh_pkey"] = cfg["ssh_key_path"]
    elif cfg.get("ssh_password"):
        auth["ssh_password"] = cfg["ssh_password"]
    else:
        raise RuntimeError("SSH auth: set GALY_SSH_KEY or GALY_SSH_PASSWORD in .env")

    tunnel = SSHTunnelForwarder(
        (cfg["ssh_host"], cfg["ssh_port"]),
        ssh_username=cfg["ssh_user"],
        remote_bind_address=(cfg["remote_db_host"], cfg["remote_db_port"]),
        **auth,
    )
    tunnel.start()
    logger.info(f"SSH tunnel open → {cfg['ssh_host']} local port {tunnel.local_bind_port}")

    conn = pymysql.connect(
        host="127.0.0.1",
        port=tunnel.local_bind_port,
        user=cfg["remote_db_user"],
        password=cfg["remote_db_pass"],
        database=cfg["source_db"],
        charset="utf8mb4",
        autocommit=False,
    )
    return conn, tunnel


def main():
    parser = argparse.ArgumentParser(description="DigiPharm AI ETL runner")
    parser.add_argument("--pharmacy", help="Adapter key (e.g. galy)")
    parser.add_argument("--full",     action="store_true", help="Full sync (ignore last date)")
    parser.add_argument("--list",     action="store_true", help="List adapters")
    args = parser.parse_args()

    if args.list:
        for k, c in ADAPTERS.items():
            print(f"  {k:<20} mode={c['connection']}  db={c['source_db']}  pharmacy_id={c['pharmacy_id']}")
        return

    if not args.pharmacy:
        parser.error("--pharmacy required (or --list)")

    key = args.pharmacy.lower()
    if key not in ADAPTERS:
        logger.error(f"Unknown: {key!r}. Available: {list(ADAPTERS)}")
        sys.exit(1)

    cfg = ADAPTERS[key]
    AdapterClass = load_class(cfg["class"])

    analytics_conn = connect_local(ANALYTICS_DB)
    tunnel = None

    try:
        if cfg["connection"] == "ssh":
            source_conn, tunnel = connect_ssh(cfg)
        else:
            source_conn = connect_local(cfg["source_db"])

        adapter = AdapterClass(
            pharmacy_id=cfg["pharmacy_id"],
            analytics_conn=analytics_conn,
            source_conn=source_conn,
        )
        result = adapter.run(full_sync=args.full)
        source_conn.close()
    finally:
        if tunnel:
            tunnel.stop()

    analytics_conn.close()

    if result["status"] == "failed":
        logger.error(f"ETL failed: {result['error']}")
        sys.exit(1)

    logger.info(
        f"Done — sales={result['sales_synced']} "
        f"inventory={result['inventory_synced']} "
        f"duration={result['duration_seconds']}s"
    )


if __name__ == "__main__":
    main()

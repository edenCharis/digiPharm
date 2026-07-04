"""
ETL control endpoints — test connection + trigger sync from the analytics dashboard.
Called via the PHP api.php bridge (API key auth).
"""
import subprocess
import sys
import os
from fastapi import APIRouter, HTTPException, Request
from sqlalchemy import text
from models.analytics import _engine, aquery

router = APIRouter(prefix="/analytics/etl", tags=["etl"])


def _resolve_pharmacy(request: Request) -> int:
    api_key = (
        request.headers.get("X-API-Key")
        or request.query_params.get("api_key")
    )
    if not api_key:
        raise HTTPException(status_code=401, detail="API key required")
    with _engine().connect() as conn:
        row = conn.execute(
            text("SELECT id FROM ai_pharmacies WHERE api_key = :k AND is_active = 1"),
            {"k": api_key},
        ).fetchone()
    if not row:
        raise HTTPException(status_code=403, detail="Invalid API key")
    return int(row[0])


def _load_source(pharmacy_id: int) -> dict | None:
    """Load and decrypt the data source config for a pharmacy."""
    df = aquery(
        "SELECT * FROM ai_data_sources WHERE pharmacy_id = :pid AND is_active = 1 LIMIT 1",
        {"pid": pharmacy_id},
    )
    if df.empty:
        return None
    return df.iloc[0].to_dict()


def _decrypt(value: str) -> str:
    """Decrypt a value encrypted by PHP ai_encrypt()."""
    if not value:
        return ""
    import base64
    from Crypto.Cipher import AES
    from Crypto.Util.Padding import unpad

    enc_key_raw = os.getenv("ENCRYPTION_KEY", "digipharmai_fallback_key_change_me")
    import hashlib
    key = hashlib.sha256(enc_key_raw.encode()).digest()[:32]

    raw = base64.b64decode(value)
    iv  = raw[:16]
    ct  = raw[16:]
    cipher = AES.new(key, AES.MODE_CBC, iv)
    return unpad(cipher.decrypt(ct), AES.block_size).decode()


def _try_connect_ssh(src: dict):
    """Open SSH tunnel + MySQL connection. Returns (conn, tunnel) or raises."""
    from sshtunnel import SSHTunnelForwarder
    import pymysql

    ssh_password = _decrypt(str(src.get("ssh_password") or ""))
    db_password  = _decrypt(str(src.get("db_password")  or ""))

    auth = {}
    if ssh_password:
        auth["ssh_password"] = ssh_password
    else:
        key_path = "/root/.ssh/id_rsa"
        if os.path.exists(key_path):
            auth["ssh_pkey"] = key_path
        else:
            raise ValueError("Aucune clé SSH ni mot de passe configuré")

    tunnel = SSHTunnelForwarder(
        (str(src["ssh_host"]), int(src.get("ssh_port") or 22)),
        ssh_username=str(src.get("ssh_user") or "root"),
        remote_bind_address=(str(src.get("db_host") or "127.0.0.1"), int(src.get("db_port") or 3306)),
        **auth,
    )
    tunnel.start()

    conn = pymysql.connect(
        host="127.0.0.1",
        port=tunnel.local_bind_port,
        user=str(src.get("db_user") or "root"),
        password=db_password,
        database=str(src["db_name"]),
        charset="utf8mb4",
        connect_timeout=8,
    )
    return conn, tunnel


def _try_connect_direct(src: dict):
    import pymysql
    db_password = _decrypt(str(src.get("db_password") or ""))
    conn = pymysql.connect(
        host=str(src.get("db_host") or "127.0.0.1"),
        port=int(src.get("db_port") or 3306),
        user=str(src.get("db_user") or "root"),
        password=db_password,
        database=str(src["db_name"]),
        charset="utf8mb4",
        connect_timeout=8,
    )
    return conn, None


# ── Test connection ───────────────────────────────────────────────────────

@router.post("/test")
async def test_connection(request: Request):
    pid = _resolve_pharmacy(request)
    src = _load_source(pid)
    if not src:
        raise HTTPException(400, "Aucune source configurée pour cette pharmacie")

    conn = tunnel = None
    try:
        if src.get("conn_type") == "ssh":
            conn, tunnel = _try_connect_ssh(src)
        else:
            conn, tunnel = _try_connect_direct(src)

        with conn.cursor() as cur:
            cur.execute("SHOW TABLES")
            tables = [r[0] for r in cur.fetchall()]

        # Update last_test status
        with _engine().connect() as db:
            db.execute(text("""
                UPDATE ai_data_sources
                SET last_tested_at = NOW(), last_test_ok = 1, last_test_error = NULL
                WHERE pharmacy_id = :pid
            """), {"pid": pid})
            db.commit()

        return {"ok": True, "tables": len(tables), "table_names": tables[:20]}

    except Exception as exc:
        err = str(exc)
        with _engine().connect() as db:
            db.execute(text("""
                UPDATE ai_data_sources
                SET last_tested_at = NOW(), last_test_ok = 0, last_test_error = :err
                WHERE pharmacy_id = :pid
            """), {"pid": pid, "err": err[:500]})
            db.commit()
        return {"ok": False, "error": err}

    finally:
        if conn:
            conn.close()
        if tunnel:
            tunnel.stop()


# ── Trigger sync ──────────────────────────────────────────────────────────

@router.get("/sync")
async def trigger_sync(request: Request, full: bool = False):
    pid = _resolve_pharmacy(request)
    src = _load_source(pid)
    if not src:
        raise HTTPException(400, "Aucune source configurée")

    # Find the adapter key for this pharmacy_id
    df = aquery(
        "SELECT slug FROM ai_pharmacies WHERE id = :pid LIMIT 1", {"pid": pid}
    )
    if df.empty:
        raise HTTPException(404, "Pharmacie introuvable")
    slug = str(df.iloc[0]["slug"])

    # Run ETL as subprocess (non-blocking)
    script_dir = os.path.dirname(os.path.dirname(__file__))
    python = os.path.join(script_dir, "venv", "bin", "python3")
    if not os.path.exists(python):
        python = sys.executable

    cmd = [python, "-m", "etl.run", "--pharmacy", slug]
    if full:
        cmd.append("--full")

    try:
        proc = subprocess.Popen(
            cmd,
            cwd=script_dir,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            env={**os.environ, "PYTHONPATH": script_dir},
        )
        # Wait up to 5s for a fast response, then detach
        try:
            out, err = proc.communicate(timeout=5)
            success = proc.returncode == 0
            msg = out.decode()[-300:] if out else err.decode()[-300:]
            return {"ok": success, "message": msg or "Sync lancé", "pid": proc.pid}
        except subprocess.TimeoutExpired:
            # Running in background — that's fine
            return {"ok": True, "message": "Synchronisation lancée en arrière-plan", "pid": proc.pid}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}

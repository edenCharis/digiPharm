"""
ETL control endpoints — test connection + trigger sync from the analytics dashboard.
Called via the PHP api.php bridge (API key auth).
"""
import subprocess
import sys
import os
import hashlib
import base64
from fastapi import APIRouter, HTTPException, Request
from sqlalchemy import text
from models.analytics import _engine, aquery

router = APIRouter(prefix="/analytics/etl", tags=["etl"])


# ── Auth ──────────────────────────────────────────────────────────────────

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


# ── DB helpers ────────────────────────────────────────────────────────────

def _load_source(pharmacy_id: int) -> dict | None:
    df = aquery(
        "SELECT * FROM ai_data_sources WHERE pharmacy_id = :pid AND is_active = 1 LIMIT 1",
        {"pid": pharmacy_id},
    )
    return None if df.empty else df.iloc[0].to_dict()


# ── Crypto ────────────────────────────────────────────────────────────────

def _decrypt(value: str) -> str:
    """Decrypt a value encrypted by PHP ai_encrypt() (AES-256-CBC, SHA-256 key)."""
    if not value:
        return ""
    try:
        from Crypto.Cipher import AES
        from Crypto.Util.Padding import unpad
        enc_key = os.getenv("ENCRYPTION_KEY", "digipharmai_fallback_key_change_me")
        key = hashlib.sha256(enc_key.encode()).digest()[:32]
        raw = base64.b64decode(value)
        cipher = AES.new(key, AES.MODE_CBC, raw[:16])
        return unpad(cipher.decrypt(raw[16:]), AES.block_size).decode()
    except Exception:
        return ""


# ── Connection factory ────────────────────────────────────────────────────

def _open_connection(src: dict, encrypted: bool = True):
    """
    Build and open a DB connection from src dict.
    encrypted=True  → passwords are AES-encrypted (loaded from DB)
    encrypted=False → passwords are plain text (submitted from form)
    """
    from sshtunnel import SSHTunnelForwarder
    import pymysql

    def pwd(field: str) -> str:
        raw = str(src.get(field) or "")
        return _decrypt(raw) if (encrypted and raw) else raw

    db_name = str(src.get("db_name") or "").strip()
    if not db_name:
        raise ValueError("Nom de la base de données manquant")

    db_kwargs = dict(
        user=str(src.get("db_user") or "root"),
        password=pwd("db_password"),
        database=db_name,
        charset="utf8mb4",
        connect_timeout=12,
    )

    if str(src.get("conn_type", "ssh")) == "ssh":
        import paramiko

        ssh_host = str(src.get("ssh_host") or "").strip()
        if not ssh_host:
            raise ValueError("Hôte SSH manquant")

        ssh_pass = pwd("ssh_password")
        auth = {}
        if ssh_pass:
            auth["ssh_password"] = ssh_pass
        else:
            key_path = "/root/.ssh/id_rsa"
            if os.path.exists(key_path):
                auth["ssh_pkey"] = key_path
            else:
                raise ValueError("Mot de passe SSH manquant (et aucune clé SSH trouvée sur le serveur)")

        # sshtunnel uses paramiko.RejectPolicy by default — www-data has no known_hosts.
        # Monkey-patch to AutoAddPolicy for the duration of tunnel creation.
        _orig_policy = paramiko.RejectPolicy
        paramiko.RejectPolicy = paramiko.AutoAddPolicy
        try:
            tunnel = SSHTunnelForwarder(
                (ssh_host, int(src.get("ssh_port") or 22)),
                ssh_username=str(src.get("ssh_user") or "root"),
                remote_bind_address=(str(src.get("db_host") or "127.0.0.1"), int(src.get("db_port") or 3306)),
                allow_agent=False,
                **auth,
            )
            import logging as _log
            _log.getLogger("paramiko").setLevel(_log.DEBUG)
            tunnel.start()
        except Exception as _te:
            import traceback as _tb
            raise RuntimeError(f"[SSH-DEBUG] {type(_te).__name__}: {_te}\n{_tb.format_exc()}") from _te
        finally:
            paramiko.RejectPolicy = _orig_policy
        conn = pymysql.connect(host="127.0.0.1", port=tunnel.local_bind_port, **db_kwargs)
        return conn, tunnel

    else:  # direct TCP
        conn = pymysql.connect(
            host=str(src.get("db_host") or "127.0.0.1"),
            port=int(src.get("db_port") or 3306),
            **db_kwargs,
        )
        return conn, None


# ── Test connection ───────────────────────────────────────────────────────

@router.post("/test")
async def test_connection(request: Request):
    pid = _resolve_pharmacy(request)

    # Priority 1: use form data submitted in the POST body (no save required)
    # Priority 2: fall back to DB-saved config
    body: dict = {}
    try:
        body = await request.json()
    except Exception:
        pass

    from_form = bool(body.get("db_name", "").strip())

    if from_form:
        src, encrypted = body, False
    else:
        src = _load_source(pid)
        if not src:
            return {
                "ok": False,
                "error": "Aucune source configurée. Remplissez le formulaire et sauvegardez d'abord.",
            }
        encrypted = True

    conn = tunnel = None
    try:
        conn, tunnel = _open_connection(src, encrypted=encrypted)

        with conn.cursor() as cur:
            cur.execute("SHOW TABLES")
            tables = [r[0] for r in cur.fetchall()]

        if not from_form:
            with _engine().connect() as db:
                db.execute(text("""
                    UPDATE ai_data_sources
                    SET last_tested_at = NOW(), last_test_ok = 1, last_test_error = NULL
                    WHERE pharmacy_id = :pid
                """), {"pid": pid})
                db.commit()

        return {"ok": True, "tables": len(tables), "table_names": tables[:20]}

    except Exception as exc:
        import paramiko as _pm
        if isinstance(exc, _pm.AuthenticationException):
            err = "Authentification SSH refusée — vérifiez le nom d'utilisateur et le mot de passe SSH."
        elif isinstance(exc, _pm.SSHException):
            err = f"Erreur SSH : {exc}"
        else:
            err = str(exc)
        if not from_form:
            try:
                with _engine().connect() as db:
                    db.execute(text("""
                        UPDATE ai_data_sources
                        SET last_tested_at = NOW(), last_test_ok = 0, last_test_error = :err
                        WHERE pharmacy_id = :pid
                    """), {"pid": pid, "err": err[:500]})
                    db.commit()
            except Exception:
                pass
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
        return {"ok": False, "error": "Aucune source configurée. Sauvegardez d'abord vos paramètres."}

    df = aquery("SELECT slug FROM ai_pharmacies WHERE id = :pid LIMIT 1", {"pid": pid})
    if df.empty:
        raise HTTPException(404, "Pharmacie introuvable")
    slug = str(df.iloc[0]["slug"])

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
        try:
            out, err = proc.communicate(timeout=8)
            success = proc.returncode == 0
            msg = (out or err).decode()[-400:]
            return {"ok": success, "message": msg or "Sync terminé"}
        except subprocess.TimeoutExpired:
            return {"ok": True, "message": "Synchronisation lancée en arrière-plan"}
    except Exception as exc:
        return {"ok": False, "error": str(exc)}

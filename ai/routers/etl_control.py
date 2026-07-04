"""
ETL control endpoints — test connection + trigger sync from the analytics dashboard.
Called via the PHP api.php bridge (API key auth).
"""
import subprocess
import sys
import os
import hashlib
import base64
import socket
import select
import threading
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
        enc_key = os.getenv("ENCRYPTION_KEY", "")
        if not enc_key:
            return ""
        key = hashlib.sha256(enc_key.encode()).digest()[:32]
        raw = base64.b64decode(value)
        cipher = AES.new(key, AES.MODE_CBC, raw[:16])
        return unpad(cipher.decrypt(raw[16:]), AES.block_size).decode()
    except Exception:
        return ""


# ── Paramiko SSH tunnel (replaces sshtunnel) ──────────────────────────────

class _TunnelHandle:
    """Wraps a paramiko SSHClient so callers can do tunnel.stop()."""
    def __init__(self, client):
        self._client = client

    def stop(self):
        try:
            self._client.close()
        except Exception:
            pass


def _make_ssh_tunnel(ssh_host: str, ssh_port: int, ssh_user: str, ssh_pass: str | None,
                     remote_host: str, remote_port: int) -> tuple[int, "_TunnelHandle"]:
    """
    Open an SSH connection and start a local port-forwarding listener.
    Returns (local_port, tunnel_handle).
    pymysql should connect to 127.0.0.1:local_port.
    """
    import paramiko

    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())

    # Key candidates: project-specific key (preferred) then root fallback
    KEY_PATHS = [
        "/var/www/digipharma/ai/.ssh/id_rsa",
        "/root/.ssh/id_rsa",
    ]

    connect_kw: dict = dict(
        port=ssh_port,
        username=ssh_user,
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
            raise ValueError(
                "Mot de passe SSH manquant et aucune clé SSH disponible. "
                "Générez une clé sur le VPS ou renseignez un mot de passe."
            )

    try:
        client.connect(ssh_host, **connect_kw)
    except paramiko.AuthenticationException:
        client.close()
        raise ValueError(
            f"Authentification SSH refusée pour '{ssh_user}@{ssh_host}' — "
            "vérifiez le nom d'utilisateur et le mot de passe SSH"
        )
    except paramiko.SSHException as e:
        client.close()
        raise ValueError(f"Erreur de protocole SSH : {e}")
    except (OSError, socket.timeout, TimeoutError) as e:
        client.close()
        raise ValueError(f"Impossible de joindre {ssh_host}:{ssh_port} — {e}")

    transport = client.get_transport()

    # Bind a local TCP server that pymysql will connect to
    srv = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    srv.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    srv.bind(("127.0.0.1", 0))
    local_port = srv.getsockname()[1]
    srv.listen(1)
    srv.settimeout(15)

    def _forward():
        lconn = None
        chan = None
        try:
            lconn, addr = srv.accept()
            lconn.settimeout(None)
            try:
                chan = transport.open_channel(
                    "direct-tcpip", (remote_host, remote_port), addr
                )
            except Exception as e:
                return  # lconn gets closed in finally

            while True:
                r, _, _ = select.select([lconn, chan], [], [], 1.0)
                if lconn in r:
                    data = lconn.recv(8192)
                    if not data:
                        break
                    chan.sendall(data)
                if chan in r:
                    if chan.closed or not chan.recv_ready() and chan.eof_received:
                        break
                    data = chan.recv(8192)
                    if not data:
                        break
                    lconn.sendall(data)
        except Exception:
            pass
        finally:
            for obj in (lconn, chan, srv):
                try:
                    obj.close()
                except Exception:
                    pass

    threading.Thread(target=_forward, daemon=True).start()
    return local_port, _TunnelHandle(client)


# ── Connection factory ────────────────────────────────────────────────────

def _open_connection(src: dict, encrypted: bool = True):
    """
    Build and open a DB connection from src dict.
    encrypted=True  → passwords are AES-encrypted (loaded from DB)
    encrypted=False → passwords are plain text (submitted from form)
    """
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
        ssh_host = str(src.get("ssh_host") or "").strip()
        if not ssh_host:
            raise ValueError("Hôte SSH manquant")

        ssh_pass = pwd("ssh_password") or None
        remote_host = str(src.get("db_host") or "127.0.0.1")
        remote_port = int(src.get("db_port") or 3306)

        local_port, tunnel = _make_ssh_tunnel(
            ssh_host,
            int(src.get("ssh_port") or 22),
            str(src.get("ssh_user") or "root"),
            ssh_pass,
            remote_host,
            remote_port,
        )

        try:
            conn = pymysql.connect(host="127.0.0.1", port=local_port, **db_kwargs)
        except Exception as e:
            tunnel.stop()
            raise ValueError(f"Connexion MySQL via tunnel SSH échouée : {e}")

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

    body: dict = {}
    try:
        body = await request.json()
    except Exception:
        pass

    from_form = bool(body.get("db_name", "").strip())

    if from_form:
        # Form values are plain text. But password fields may be empty if the page was
        # reloaded (PHP doesn't render stored passwords for security). In that case,
        # fall back to the DB-saved encrypted value for the empty fields.
        db_src = _load_source(pid)
        src = dict(body)
        encrypted_fields = {}

        if db_src:
            for pwd_field in ("ssh_password", "db_password"):
                if not str(body.get(pwd_field) or "").strip():
                    # Field is empty in form → use DB stored (encrypted) value
                    encrypted_fields[pwd_field] = str(db_src.get(pwd_field) or "")
                    src[pwd_field] = encrypted_fields[pwd_field]

        # Build a mixed-encryption resolver: plain text for all fields except
        # those we just loaded from DB (which are encrypted).
        def _resolve_src(field: str) -> str:
            raw = str(src.get(field) or "")
            if field in encrypted_fields and raw:
                return _decrypt(raw)
            return raw

        # Override src with resolved passwords
        for f in ("ssh_password", "db_password"):
            src[f] = _resolve_src(f)

        encrypted = False  # passwords already resolved above
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

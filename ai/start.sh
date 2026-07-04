#!/usr/bin/env bash
# DigiPharm AI — start script
# Usage: ./start.sh [--install]

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

# Create venv if needed
if [ ! -d "venv" ]; then
    echo "[digipharm-ai] Creating virtual environment..."
    python3 -m venv venv
fi

source venv/bin/activate

if [ "$1" == "--install" ]; then
    echo "[digipharm-ai] Installing dependencies..."
    pip install --upgrade pip -q
    pip install -r requirements.txt -q
    echo "[digipharm-ai] Dependencies installed."
fi

# Copy .env if missing
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        echo "[digipharm-ai] .env created from .env.example — edit it with your DB credentials."
        exit 1
    fi
fi

echo "[digipharm-ai] Starting FastAPI on port 8000..."
exec uvicorn main:app --host 0.0.0.0 --port 8000 --workers 2 --log-level info

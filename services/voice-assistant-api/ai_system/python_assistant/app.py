#!/usr/bin/env python3
"""Flask API for Symfony <-> Python assistant integration."""

from __future__ import annotations

import logging
import os
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, Optional

from flask import Flask, jsonify, request

from .command_router import CommandRouter
from .command_safety import CommandPolicy, build_execution_plan
from .health import run_system_health
from .noise_orchestrator import NoiseOrchestrator
from .ollama_service import OllamaService

app = Flask(__name__)
app.config["JSON_SORT_KEYS"] = False

logging.basicConfig(
    level=os.environ.get("LOG_LEVEL", "INFO"),
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
logger = logging.getLogger(__name__)


class _ApiVas:
    def __init__(self) -> None:
        self.last_spoken = ""
        self.logged_out = False

    def vivian_speak(self, text: str) -> None:
        self.last_spoken = text

    def notify_user_logged_out(self) -> None:
        self.logged_out = True


class _ApiProxy:
    def open_booking(self) -> None:
        return

    def cancel_booking(self) -> None:
        return

    def show_bookings_tab(self) -> None:
        return

    def show_search_tab(self) -> None:
        return

    def trigger_payment(self) -> None:
        return

    def describe_screen(self) -> str:
        return "Assistant API screen context"


VAS = _ApiVas()
PROXY = _ApiProxy()
ROUTER = CommandRouter(VAS, PROXY)
OLLAMA = OllamaService()
NOISE = NoiseOrchestrator.get_instance()

POLICY = CommandPolicy(
    allowlist={
        "BOOK": ["assistant", "book"],
        "RESERVER": ["assistant", "book"],
        "RECHERCHER": ["assistant", "search"],
        "MES RESERVATIONS": ["assistant", "show-bookings"],
        "ANNULER": ["assistant", "cancel"],
        "PAYER": ["assistant", "pay"],
        "AIDE": ["assistant", "help"],
        "DECRIRE": ["assistant", "describe"],
        "DECONNEXION": ["assistant", "logout"],
    }
)


def _envelope(success: bool, data: Any = None, error: Optional[str] = None, status: int = 200):
    return (
        jsonify(
            {
                "success": success,
                "data": data,
                "error": error,
                "timestamp": datetime.utcnow().isoformat() + "Z",
            }
        ),
        status,
    )


def _infer_intent_from_text(text: str) -> Optional[str]:
    normalized = CommandRouter._norm(text)
    for key in ROUTER.command_table.keys():
        if CommandRouter._norm(key) in normalized:
            return key
    return None


@app.get("/api/health")
def health():
    try:
        base = Path(__file__).resolve().parents[2]
        env_script = os.environ.get("AGENT_SCRIPT_PATH", "").strip()
        candidates = [
            Path(env_script) if env_script else None,
            base / "ai_system" / "src" / "main" / "resources" / "scripts" / "voice_agent.py",
            base / "src" / "main" / "resources" / "scripts" / "voice_agent.py",
            base / "agent",
        ]
        existing = [p for p in candidates if p is not None and p.exists()]
        fallback = next((p for p in candidates if p is not None), base / "agent")
        agent_script = existing[0] if existing else fallback
        report = run_system_health(agent_script)
        return _envelope(True, data=report, status=200)
    except Exception as exc:
        logger.exception("health error")
        return _envelope(False, error=str(exc), status=500)


@app.post("/api/classify")
def classify():
    payload: Dict[str, Any] = request.get_json(silent=True) or {}
    text = str(payload.get("text", "")).strip()
    if not text:
        return _envelope(False, error="Missing or empty 'text'", status=400)

    response = OLLAMA.classify(text)
    if response is None:
        return _envelope(
            False,
            error=OLLAMA.last_error or "Ollama classification unavailable",
            status=503,
        )

    return _envelope(
        True,
        data={
            "intent": response.intent,
            "action": response.action,
            "confidence": response.confidence,
            "destination": response.destination,
            "date": response.date,
            "engine": response.engine,
            "passengers": response.passengers,
        },
        status=200,
    )


@app.post("/api/command")
def command():
    payload: Dict[str, Any] = request.get_json(silent=True) or {}
    text = str(payload.get("user_input", "")).strip()
    dry_run = bool(payload.get("is_dry_run", True))

    if not text:
        return _envelope(False, error="Missing or empty 'user_input'", status=400)

    intent = _infer_intent_from_text(text)
    if intent is None:
        return _envelope(True, data={"matched": False, "intent": None, "plan": None}, status=200)

    plan = build_execution_plan(intent=intent, entities={}, policy=POLICY, dry_run=dry_run)
    return _envelope(
        True,
        data={
            "matched": True,
            "intent": intent,
            "plan": {
                "intent": plan.intent,
                "command": plan.command,
                "dry_run": plan.dry_run,
                "requires_confirmation": plan.requires_confirmation,
                "blocked": plan.blocked,
                "reason": plan.reason,
            },
            "spoken": VAS.last_spoken,
        },
        status=200,
    )


@app.post("/api/noise/frame")
def noise_frame():
    payload: Dict[str, Any] = request.get_json(silent=True) or {}
    rms = payload.get("rms")
    if not isinstance(rms, (int, float)):
        return _envelope(False, error="Missing numeric 'rms'", status=400)

    is_speech = NOISE.feed_frame(float(rms))
    return _envelope(
        True,
        data={
            "is_speech": is_speech,
            "speech_confidence": NOISE.get_speech_confidence(),
            "vad_state": NOISE.get_vad_state().value,
            "environment": NOISE.get_environment().name,
            "gate_threshold": NOISE.get_gate_threshold(),
            "noise_floor": NOISE.get_noise_floor(),
            "calibrated": NOISE.is_calibrated(),
            "tuning": NOISE.get_tuning_values(),
        },
        status=200,
    )


@app.post("/api/noise/recalibrate")
def noise_recalibrate():
    NOISE.force_recalibrate()
    return _envelope(True, data={"recalibrated": True}, status=200)


if __name__ == "__main__":
    host = os.environ.get("FLASK_HOST", "0.0.0.0")
    port = int(os.environ.get("FLASK_PORT", "5000"))
    debug = os.environ.get("FLASK_DEBUG", "false").lower() == "true"
    app.run(host=host, port=port, debug=debug, threaded=True)

from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Optional

from .models import AgentResponse

OLLAMA_BASE = os.getenv("OLLAMA_BASE", "http://localhost:11434")
MODEL = os.getenv("OLLAMA_MODEL", "llama3.2")
PREFERRED_MODEL_HINTS = tuple(
    hint.strip().lower()
    for hint in os.getenv(
        "OLLAMA_MODEL_HINTS",
        "mini,small,compact,fast,performance"
    ).split(",")
    if hint.strip()
)

SYSTEM_PROMPT = (
    "You are the voice assistant for GoVibe, a travel booking app. "
    "Classify speech into one action only: "
    "BOOK, MES RESERVATIONS, RECHERCHER, ANNULER, PAYER, AIDE, DECRIRE, "
    "DECONNEXION, LOGIN, SIGNUP, FOCUS_EMAIL, FOCUS_PASSWORD, NONE, UNKNOWN. "
    "Also extract destination and date (YYYY-MM-DD) if present. "
    "Respond only with minified JSON containing action, confidence, destination, date, response."
)


@dataclass
class OllamaService:
    available: Optional[bool] = None
    last_error: str = ""

    def list_models(self) -> list[str]:
        req = urllib.request.Request(OLLAMA_BASE + "/api/tags", method="GET")
        try:
            with urllib.request.urlopen(req, timeout=4) as resp:
                if resp.status != 200:
                    return []
                raw = resp.read().decode("utf-8", errors="replace")
            parsed = json.loads(raw)
            models = parsed.get("models", [])
            names: list[str] = []
            for model in models:
                name = model.get("name") if isinstance(model, dict) else None
                if isinstance(name, str) and name.strip():
                    names.append(name.strip())
            return names
        except Exception as exc:
            self.last_error = f"/api/tags: {exc}"
            return []

    def resolve_model(self) -> Optional[str]:
        names = self.list_models()
        if not names:
            self.last_error = f"No Ollama models installed. Expected '{MODEL}'."
            return None

        lower_names = {n.lower(): n for n in names}
        wanted = MODEL.lower()

        if any(hint in wanted for hint in PREFERRED_MODEL_HINTS):
            if wanted in lower_names:
                return lower_names[wanted]
            wanted_with_latest = f"{wanted}:latest"
            if wanted_with_latest in lower_names:
                return lower_names[wanted_with_latest]

        preferred = self._prefer_smaller_model(names)
        if preferred is not None:
            return preferred

        if wanted in lower_names:
            return lower_names[wanted]

        wanted_with_latest = f"{wanted}:latest"
        if wanted_with_latest in lower_names:
            return lower_names[wanted_with_latest]

        # If configured model is missing, use the first available model to keep the system operational.
        return names[0]

    def _prefer_smaller_model(self, names: list[str]) -> Optional[str]:
        ranked: list[str] = []
        for name in names:
            lower = name.lower()
            if any(hint in lower for hint in PREFERRED_MODEL_HINTS):
                ranked.append(name)
        if not ranked:
            return None

        # Prefer the most compact/mini-looking model name first, then the shortest name.
        ranked.sort(key=lambda item: (len(item), item.lower()))
        return ranked[0]

    def is_available(self) -> bool:
        if self.available is not None:
            return self.available
        endpoints = ("/api/tags", "/")
        for ep in endpoints:
            req = urllib.request.Request(OLLAMA_BASE + ep, method="GET")
            try:
                with urllib.request.urlopen(req, timeout=2) as resp:
                    if resp.status == 200:
                        self.available = True
                        self.last_error = ""
                        return True
            except Exception as exc:
                self.last_error = f"{ep}: {exc}"
        self.available = False
        return self.available

    def reset_availability_cache(self) -> None:
        self.available = None
        self.last_error = ""

    def classify(self, text: str) -> Optional[AgentResponse]:
        if not self.is_available():
            return None
        model_name = self.resolve_model()
        if model_name is None:
            return None
        payload = {
            "model": model_name,
            "stream": False,
            "format": "json",
            "messages": [
                {"role": "system", "content": SYSTEM_PROMPT},
                {"role": "user", "content": text},
            ],
        }
        req = urllib.request.Request(
            OLLAMA_BASE + "/api/chat",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json; charset=UTF-8"},
            method="POST",
        )
        try:
            with urllib.request.urlopen(req, timeout=8) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
        except (urllib.error.URLError, TimeoutError, OSError):
            return None
        return self._parse_ollama_response(raw)

    def _parse_ollama_response(self, raw: str) -> Optional[AgentResponse]:
        try:
            root = json.loads(raw)
            content = root["message"]["content"]
            parsed = json.loads(content)
        except Exception:
            return None

        action = str(parsed.get("action", "UNKNOWN"))
        destination = parsed.get("destination")
        date = parsed.get("date")
        confidence = float(parsed.get("confidence", 0.0))
        response = str(parsed.get("response", ""))

        if isinstance(destination, str) and destination.lower() == "null":
            destination = None
        if isinstance(date, str) and date.lower() == "null":
            date = None

        return AgentResponse(
            intent=_action_to_intent(action),
            response=response,
            action=action,
            confidence=confidence,
            destination=destination,
            date=date,
            engine="ollama-python",
            passengers=1,
        )


def _action_to_intent(action: str) -> str:
    action_upper = action.strip().upper()
    mapping = {
        "BOOK": "BOOK",
        "MES RESERVATIONS": "VIEW_BOOKINGS",
        "RECHERCHER": "SEARCH",
        "ANNULER": "CANCEL",
        "PAYER": "PAY",
        "AIDE": "HELP",
        "DECRIRE": "DESCRIBE",
        "DECONNEXION": "LOGOUT",
        "LOGIN": "LOGIN",
        "SIGNUP": "SIGNUP",
        "FOCUS_EMAIL": "FOCUS_EMAIL",
        "FOCUS_PASSWORD": "FOCUS_PASSWORD",
        "NONE": "GREET",
    }
    return mapping.get(action_upper, "UNKNOWN")

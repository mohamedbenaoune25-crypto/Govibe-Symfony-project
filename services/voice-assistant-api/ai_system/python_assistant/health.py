from __future__ import annotations

import importlib.util
import json
import shutil
import sys
from pathlib import Path
from typing import Dict, List

from .ollama_service import MODEL, OLLAMA_BASE, OllamaService
from .nlu_regression import evaluate_golden_set


def run_system_health(agent_script_path: Path) -> Dict[str, object]:
    checks: List[Dict[str, object]] = []

    py_ok = sys.version_info.major == 3 and sys.version_info.minor >= 10
    checks.append({"name": "python_version", "ok": py_ok, "detail": sys.version.split()[0]})

    checks.append({
        "name": "agent_script_exists",
        "ok": agent_script_path.exists(),
        "detail": str(agent_script_path),
    })

    checks.append({
        "name": "pytest_installed",
        "ok": importlib.util.find_spec("pytest") is not None,
        "detail": "pytest",
    })

    checks.append({
        "name": "python_binary_present",
        "ok": shutil.which("python") is not None,
        "detail": shutil.which("python") or "missing",
    })

    ollama = OllamaService()
    ollama_ok = ollama.is_available()
    ollama_detail = OLLAMA_BASE
    if not ollama_ok and ollama.last_error:
        ollama_detail = f"{ollama_detail} ({ollama.last_error})"
    checks.append({
        "name": "ollama_reachable",
        "ok": ollama_ok,
        "detail": ollama_detail,
    })

    ollama_models = ollama.list_models() if ollama_ok else []
    checks.append(
        {
            "name": "ollama_models_present",
            "ok": len(ollama_models) > 0,
            "detail": ", ".join(ollama_models) if ollama_models else "no models installed",
        }
    )

    resolved_model = ollama.resolve_model() if ollama_ok else None
    checks.append(
        {
            "name": "ollama_model_ready",
            "ok": resolved_model is not None,
            "detail": f"configured={MODEL}, resolved={resolved_model or 'none'}, hints=mini/small/compact/fast/performance",
        }
    )

    golden_path = Path(__file__).resolve().parent / "golden_set" / "golden_phrases.json"
    if golden_path.exists():
        regression = evaluate_golden_set(golden_path)
        checks.append(
            {
                "name": "golden_regression_accuracy",
                "ok": regression.accuracy >= 0.9,
                "detail": f"{regression.passed}/{regression.total} ({regression.accuracy:.2%})",
            }
        )
    else:
        checks.append(
            {
                "name": "golden_regression_accuracy",
                "ok": False,
                "detail": "golden_set/golden_phrases.json missing",
            }
        )

    overall = all(
        c["ok"]
        for c in checks
        if c["name"] not in {"ollama_reachable", "ollama_models_present", "ollama_model_ready"}
    )
    return {"ok": overall, "checks": checks}


if __name__ == "__main__":
    base = Path(__file__).resolve().parents[2]
    report = run_system_health(base / "src" / "main" / "resources" / "scripts" / "voice_agent.py")
    print(json.dumps(report, ensure_ascii=False, indent=2))

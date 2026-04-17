import json
import os
import sys
import urllib.request
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

from python_assistant.ollama_service import MODEL, OLLAMA_BASE, OllamaService


def _post_json(url: str, payload: dict) -> tuple[int, str]:
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json; charset=UTF-8"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=600) as resp:
        return resp.status, resp.read().decode("utf-8", errors="replace")


def _pull_model_streaming(name: str) -> bool:
    req = urllib.request.Request(
        OLLAMA_BASE + "/api/pull",
        data=json.dumps({"name": name, "stream": True}).encode("utf-8"),
        headers={"Content-Type": "application/json; charset=UTF-8"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=3600) as resp:
            print(f"[INFO] /api/pull status={resp.status}")
            for raw in resp:
                line = raw.decode("utf-8", errors="replace").strip()
                if not line:
                    continue
                try:
                    obj = json.loads(line)
                except Exception:
                    print(line)
                    continue
                status = obj.get("status")
                completed = obj.get("completed")
                total = obj.get("total")
                if completed is not None and total:
                    pct = (float(completed) / float(total)) * 100.0
                    print(f"[PULL] {status} {pct:.2f}%")
                elif status:
                    print(f"[PULL] {status}")
                if status == "success":
                    return True
    except Exception as exc:
        print(f"[FAIL] Streaming pull failed: {exc}")
        return False
    return False


def main() -> int:
    service = OllamaService()
    if not service.is_available():
        print(f"[FAIL] Ollama API unreachable at {OLLAMA_BASE}: {service.last_error}")
        return 1

    models = service.list_models()
    print(f"[INFO] Existing models: {models if models else 'none'}")
    if service.resolve_model() is not None:
        print(f"[OK] Model ready (configured={MODEL}, resolved={service.resolve_model()})")
        return 0

    target = os.getenv("OLLAMA_MODEL", MODEL)
    print(f"[INFO] Pulling model via REST API: {target}")
    if not _pull_model_streaming(target):
        return 1

    # Re-check tags after pull
    service.reset_availability_cache()
    models_after = service.list_models()
    resolved = service.resolve_model()
    print(f"[INFO] Models after pull: {models_after if models_after else 'none'}")
    if resolved is None:
        print(f"[FAIL] Model still unavailable. Configured={MODEL}")
        return 1

    print(f"[OK] Model ready (configured={MODEL}, resolved={resolved})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())

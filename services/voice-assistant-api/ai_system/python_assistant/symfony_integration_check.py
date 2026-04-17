import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

from python_assistant.health import run_system_health


if __name__ == "__main__":
    root = Path(__file__).resolve().parents[1]
    report = run_system_health(root / "src" / "main" / "resources" / "scripts" / "voice_agent.py")
    for check in report["checks"]:
        status = "OK" if check["ok"] else "FAIL"
        print(f"[{status}] {check['name']}: {check['detail']}")
    print(f"OVERALL={'OK' if report['ok'] else 'FAIL'}")

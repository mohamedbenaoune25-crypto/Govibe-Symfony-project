from __future__ import annotations

import json
from dataclasses import dataclass
from pathlib import Path
from typing import Dict, List

from .command_router import CommandRouter


@dataclass
class RegressionResult:
    total: int
    passed: int
    accuracy: float
    failures: List[Dict[str, str]]


def evaluate_golden_set(golden_set_path: Path) -> RegressionResult:
    data = json.loads(golden_set_path.read_text(encoding="utf-8"))

    class _Vas:
        def vivian_speak(self, _text: str) -> None:
            return

        def notify_user_logged_out(self) -> None:
            return

    class _Proxy:
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
            return "test"

    router = CommandRouter(_Vas(), _Proxy())

    total = len(data)
    passed = 0
    failures: List[Dict[str, str]] = []

    for item in data:
        phrase = item["phrase"]
        should_match = bool(item.get("should_match", True))
        matched = router.try_keyword_match(phrase)
        if matched == should_match:
            passed += 1
        else:
            failures.append({"phrase": phrase, "expected": str(should_match), "actual": str(matched)})

    accuracy = (passed / total) if total else 1.0
    return RegressionResult(total=total, passed=passed, accuracy=accuracy, failures=failures)


if __name__ == "__main__":
    here = Path(__file__).resolve().parent
    golden = here / "golden_set" / "golden_phrases.json"
    result = evaluate_golden_set(golden)
    payload = {
        "total": result.total,
        "passed": result.passed,
        "accuracy": round(result.accuracy, 4),
        "failures": result.failures,
    }
    print(json.dumps(payload, ensure_ascii=False, indent=2))

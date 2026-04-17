import json
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))

from python_assistant.nlu_regression import evaluate_golden_set


if __name__ == "__main__":
    golden = HERE / "golden_set" / "golden_phrases.json"
    result = evaluate_golden_set(golden)
    print(
        json.dumps(
            {
                "total": result.total,
                "passed": result.passed,
                "accuracy": round(result.accuracy, 4),
                "failures": result.failures,
            },
            ensure_ascii=False,
            indent=2,
        )
    )

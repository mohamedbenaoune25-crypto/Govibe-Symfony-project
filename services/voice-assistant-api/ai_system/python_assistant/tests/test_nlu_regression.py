from pathlib import Path

from python_assistant.nlu_regression import evaluate_golden_set


def test_golden_set_regression_accuracy_not_below_threshold():
    here = Path(__file__).resolve().parents[1]
    result = evaluate_golden_set(here / "golden_set" / "golden_phrases.json")
    assert result.total >= 10
    assert result.accuracy >= 0.9

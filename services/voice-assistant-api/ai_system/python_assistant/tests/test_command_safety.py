import pytest

from python_assistant.command_safety import CommandPolicy, build_execution_plan, sanitize_argument


def _policy() -> CommandPolicy:
    return CommandPolicy(
        allowlist={
            "OPEN_FILE": ["python", "-c", "print('ok')", "{target}"],
            "DELETE": ["rm", "{target}"],
            "LIST": ["ls", "{path}"],
        }
    )


def test_sanitize_argument_rejects_injection_chars():
    with pytest.raises(ValueError):
        sanitize_argument("notes.txt; rm -rf /")


def test_allowlisted_command_builds_plan_dry_run():
    plan = build_execution_plan("OPEN_FILE", {"target": "my folder/file.txt"}, _policy(), dry_run=True)
    assert not plan.blocked
    assert plan.dry_run
    assert plan.command[-1] == "my folder/file.txt"


def test_non_allowlisted_intent_blocked():
    plan = build_execution_plan("UNKNOWN", {"target": "x"}, _policy())
    assert plan.blocked
    assert "allowlisted" in plan.reason.lower()


def test_denylisted_command_blocked_and_confirmation_required():
    plan = build_execution_plan("DELETE", {"target": "tmp.log"}, _policy(), dry_run=False)
    assert plan.blocked
    assert plan.requires_confirmation


def test_destructive_intent_requires_confirmation_even_if_not_blocked():
    p = CommandPolicy(
        allowlist={"DELETE": ["echo", "{target}"]},
        denylist={"rm", "format"},
        destructive_intents={"DELETE"},
    )
    plan = build_execution_plan("DELETE", {"target": "tmp.log"}, p, dry_run=False)
    assert not plan.blocked
    assert plan.requires_confirmation

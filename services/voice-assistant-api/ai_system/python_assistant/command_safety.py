from __future__ import annotations

from dataclasses import dataclass, field
from typing import Dict, Iterable, List, Optional, Sequence


@dataclass(frozen=True)
class ExecutionPlan:
    intent: str
    command: List[str]
    dry_run: bool
    requires_confirmation: bool
    blocked: bool = False
    reason: str = ""


@dataclass
class CommandPolicy:
    allowlist: Dict[str, Sequence[str]] = field(default_factory=dict)
    denylist: Iterable[str] = field(default_factory=lambda: {"rm", "rmdir", "format", "del"})
    destructive_intents: Iterable[str] = field(default_factory=lambda: {"DELETE", "FORMAT", "REMOVE"})


_BLOCKED_CHARS = {";", "&", "|", "`", "$", "<", ">", "\n", "\r"}


def sanitize_argument(value: Optional[str]) -> str:
    if value is None:
        return ""
    cleaned = value.strip()
    for bad in _BLOCKED_CHARS:
        if bad in cleaned:
            raise ValueError(f"Unsafe shell character detected: {bad}")
    return cleaned


def build_execution_plan(
    intent: str,
    entities: Dict[str, str],
    policy: CommandPolicy,
    dry_run: bool = True,
) -> ExecutionPlan:
    intent_key = intent.strip().upper()
    template = policy.allowlist.get(intent_key)
    if not template:
        return ExecutionPlan(intent=intent_key, command=[], dry_run=dry_run, requires_confirmation=False, blocked=True, reason="Intent not allowlisted")

    rendered: List[str] = []
    for token in template:
        if token.startswith("{") and token.endswith("}"):
            key = token[1:-1]
            rendered.append(sanitize_argument(entities.get(key)))
        else:
            rendered.append(token)

    if not rendered:
        return ExecutionPlan(intent=intent_key, command=[], dry_run=dry_run, requires_confirmation=False, blocked=True, reason="Empty command")

    command_name = rendered[0].lower()
    if command_name in {d.lower() for d in policy.denylist}:
        return ExecutionPlan(intent=intent_key, command=rendered, dry_run=dry_run, requires_confirmation=True, blocked=True, reason="Command denied by policy")

    requires_confirmation = intent_key in {i.upper() for i in policy.destructive_intents}
    return ExecutionPlan(
        intent=intent_key,
        command=rendered,
        dry_run=dry_run,
        requires_confirmation=requires_confirmation,
        blocked=False,
    )

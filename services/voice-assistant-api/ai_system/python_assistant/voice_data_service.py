from __future__ import annotations

import json
from typing import Any, Iterable, List, Mapping

MAX_PER_CATEGORY = 30


def _take(items: Iterable[Mapping[str, Any]], cap: int = MAX_PER_CATEGORY) -> List[Mapping[str, Any]]:
    out: List[Mapping[str, Any]] = []
    for item in items:
        if len(out) >= cap:
            break
        out.append(item)
    return out


def build_db_context_json(
    activities: Iterable[Mapping[str, Any]] = (),
    cars: Iterable[Mapping[str, Any]] = (),
    hotels: Iterable[Mapping[str, Any]] = (),
) -> str:
    payload = {
        "type": "db_context",
        "activities": _take(activities),
        "cars": _take(cars),
        "hotels": _take(hotels),
    }
    return json.dumps(payload, ensure_ascii=False, separators=(",", ":"))

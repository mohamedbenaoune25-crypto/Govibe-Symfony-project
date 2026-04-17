import json
from typing import Any, Optional


def parse_string_field(json_text: Optional[str], key: str) -> Optional[str]:
    if json_text is None:
        return None
    try:
        obj = json.loads(json_text)
    except Exception:
        return None
    val = obj.get(key)
    if isinstance(val, str):
        return val
    if val is None:
        return None
    return str(val)


def parse_raw_field(json_text: Optional[str], key: str, default: str = "0") -> str:
    if json_text is None:
        return default
    try:
        obj = json.loads(json_text)
    except Exception:
        return default
    val: Any = obj.get(key, default)
    if val is None:
        return default
    return str(val)


def json_string(value: Optional[str]) -> str:
    return json.dumps(value or "", ensure_ascii=False)

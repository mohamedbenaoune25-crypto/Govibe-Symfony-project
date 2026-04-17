from __future__ import annotations

import json
import subprocess
import threading
from concurrent.futures import Future
from pathlib import Path
from typing import Dict, List, Optional

from .json_utils import json_string, parse_raw_field, parse_string_field
from .models import AgentResponse


def build_command(python_cmd: str, *extra_args: str) -> List[str]:
    cmd: List[str] = []
    if python_cmd.startswith('"') or python_cmd.startswith("/") or (len(python_cmd) >= 3 and python_cmd[1] == ":"):
        cmd.append(python_cmd.replace('"', ""))
    else:
        cmd.extend([part for part in python_cmd.split(" ") if part])
    cmd.extend(extra_args)
    return cmd


def is_tts_status_line(line: Optional[str]) -> bool:
    return bool(line and "tts_status" in line)


def parse_response(json_line: str) -> AgentResponse:
    intent = parse_string_field(json_line, "intent") or "UNKNOWN"
    response = parse_string_field(json_line, "response") or ""
    action = parse_string_field(json_line, "action") or "UNKNOWN"
    destination = parse_string_field(json_line, "destination")
    date = parse_string_field(json_line, "date")
    engine = parse_string_field(json_line, "engine") or "unknown"
    confidence = float(parse_raw_field(json_line, "confidence", "0") or "0")
    passengers = int(parse_raw_field(json_line, "passengers", "1") or "1")

    if destination and destination.lower() == "null":
        destination = None
    if date and date.lower() == "null":
        date = None

    return AgentResponse(
        intent=intent,
        response=response,
        action=action,
        confidence=confidence,
        destination=destination,
        date=date,
        engine=engine,
        passengers=max(1, passengers),
    )


class PythonVoiceAgentBridge:
    def __init__(self, script_path: Path, python_cmd: str = "python") -> None:
        self.script_path = script_path
        self.python_cmd = python_cmd
        self.process: Optional[subprocess.Popen[str]] = None
        self.seq = 0
        self.pending: Dict[int, Future[str]] = {}
        self.lock = threading.Lock()
        self.reader_thread: Optional[threading.Thread] = None

    def start(self) -> None:
        if self.process and self.process.poll() is None:
            return
        cmd = build_command(self.python_cmd, "-u", str(self.script_path))
        self.process = subprocess.Popen(
            cmd,
            stdin=subprocess.PIPE,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            encoding="utf-8",
        )
        self.reader_thread = threading.Thread(target=self._reader_loop, daemon=True)
        self.reader_thread.start()

    def stop(self) -> None:
        if self.process and self.process.poll() is None:
            self.process.kill()

    def send_speak(self, text: str, fast: bool = False) -> None:
        if not text.strip() or not self.process or not self.process.stdin:
            return
        payload = {"type": "speak_fast" if fast else "speak", "text": text}
        with self.lock:
            self.process.stdin.write(json.dumps(payload, ensure_ascii=False) + "\n")
            self.process.stdin.flush()

    def process_text(self, text: str, timeout_s: float = 25.0) -> AgentResponse:
        if not self.process or not self.process.stdin:
            return AgentResponse.unknown(text)
        with self.lock:
            self.seq += 1
            seq = self.seq
            fut: Future[str] = Future()
            self.pending[seq] = fut
            payload = {"text": text, "seq": seq}
            self.process.stdin.write(json.dumps(payload, ensure_ascii=False) + "\n")
            self.process.stdin.flush()
        try:
            line = fut.result(timeout=timeout_s)
        except Exception:
            self.pending.pop(seq, None)
            return AgentResponse.unknown(text)
        return parse_response(line)

    def _reader_loop(self) -> None:
        if not self.process or not self.process.stdout:
            return
        while True:
            line = self.process.stdout.readline()
            if not line:
                return
            if is_tts_status_line(line):
                continue
            try:
                obj = json.loads(line)
            except Exception:
                continue
            seq = obj.get("seq")
            if isinstance(seq, int):
                fut = self.pending.pop(seq, None)
                if fut is not None and not fut.done():
                    fut.set_result(line)

    @staticmethod
    def json_string(value: Optional[str]) -> str:
        return json_string(value)

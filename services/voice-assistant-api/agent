#!/usr/bin/env python3
"""
GoVibe Voice Agent  -  Three-tier intent classifier + Ollama LLM.

Classification pipeline (fastest to most powerful):
  Tier 1  -  sentence-transformers (all-MiniLM-L6-v2)  : ~30 ms, high accuracy
  Tier 2  -  Ollama local LLM (llama3.2 / qwen2.5)     : ~400 ms, understands
             natural language, extracts destination/date params  -  only called
             when tier-1 confidence < threshold
  Tier 3  -  scikit-learn TF-IDF                        : zero-download fallback

Protocol (line-delimited JSON over stdin / stdout):
  Java -> Python   stdin  :  {"text": "recognised user speech"}
  Python -> Java   stdout :  {
                               "intent":"BOOK",
                               "response":"...",
                               "action":"BOOK",
                               "confidence":0.95,
                               "destination":"Paris",   â† new (may be null)
                               "date":"2026-03-15",     â† new (may be null)
                               "engine":"sentence-transformers"  â† tier used
                             }

On startup the agent writes ONE ready-line to stdout:
  {"status":"ready","engine":"sentence-transformers+ollama"}
"""

import sys
import json
import os
import socket
import unicodedata
import urllib.request
import urllib.error
import threading
import queue as _queue
import time
import random as _rnd
from collections import deque
from typing import Optional

# Force UTF-8 on all platforms (critical on Windows where stdout defaults to cp1252).
if sys.stdout.encoding != "utf-8":
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding="utf-8", line_buffering=True)
if sys.stderr.encoding != "utf-8":
    import io
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding="utf-8", line_buffering=True)

# -- DNS patch for XetHub CDN -------------------------------------------------
# cas-bridge.xethub.hf.co may be blocked by local DNS.
# We know its IPs from an external resolver and bypass DNS for that host.
_XETHUB_IPS = ["3.175.86.81", "3.175.86.100", "3.175.86.94", "3.175.86.80"]
_orig_getaddrinfo = socket.getaddrinfo

def _xethub_getaddrinfo(host, port, *args, **kwargs):
    if isinstance(host, str) and "xethub" in host:
        for ip in _XETHUB_IPS:
            try:
                return _orig_getaddrinfo(ip, port, *args, **kwargs)
            except Exception:
                continue
    return _orig_getaddrinfo(host, port, *args, **kwargs)

socket.getaddrinfo = _xethub_getaddrinfo
# -----------------------------------------------------------------------------


# -----------------------------------------------------------------------------
# Text normalisation  -  strips accents and lowercases.
# Used so "rÃ©server" == "reserver" == "RESERVER" in all comparisons.
# This is critical when the English Vosk model (en-us) transcribes French
# speech: it strips accents, so we must normalise both sides before comparing.
# -----------------------------------------------------------------------------
def _normalize(text: str) -> str:
    """Lowercase + strip all diacritics (Ã©->e, Ã ->a, Ã§->c, Ã´->o ...)."""
    nfd = unicodedata.normalize("NFD", text.lower())
    return "".join(c for c in nfd if unicodedata.category(c) != "Mn")




# -- STT homophone / acoustic-confusion correction map -------------------------
# Vosk (and most English STT) confuses homophones.  Applied BEFORE every tier
# so the classifier always sees the intended word, not the mishearing.
# -----------------------------------------------------------------------------
_STT_CORRECTIONS: dict[str, str] = {
    # weather / whether  <- most common Vosk confusion in this app
    "whether or not":       "weather",
    "whether":              "weather",
    "wether":               "weather",
    "weathers":             "weather",
    # book / buck
    "buck a flight":        "book a flight",
    "buck flight":          "book flight",
    # show / shoe
    "shoe me the flights":  "show me the flights",
    "shoe me the cars":     "show me the cars",
    "shoe me the hotels":   "show me the hotels",
    # log in / log gin
    "log gin":              "log in",
    # misc
    "flites":               "flights",
    "hotell":               "hotel",
    "show me the card":     "show me the cars",
    "show me cards":        "show me the cars",
    "show me activity":     "show me activities",
    "reserver":             "book",
    "logged out":           "log out",
    "logging out":          "log out",
    "log off":              "log out",
    "logging off":          "log out",
    "lock out":             "log out",
    "locked out":           "log out",
    # face id -- Vosk commonly mishears short 2-syllable phrase "face id"
    "phase id":             "face id",
    "faced id":             "face id",
    "base id":              "face id",
    "face it":              "face id",
    "faces id":             "face id",
    "face idea":            "face id",
    "face aid":             "face id",
    "faith id":             "face id",
    "fake id":              "face id",   # bilabial /p/→/k/ confusion
    "phased id":            "face id",
    "fayed id":             "face id",
    "faze id":              "face id",
    "fades id":             "face id",
    # Vosk phonetic mangling of "face id" (observed in logs)
    "they say d":           "face id",
    "they said d":          "face id",
    "this aid":             "face id",
    "these aid":            "face id",
    "space id":             "face id",
    "the saint":            "face id",
    "base it":              "face id",
    "faith":                "face id",
    # Vosk further mishearings of "face id" observed in runtime logs
    "please idea":          "please face id",
    "please id":            "face id",
    # "no in" / "no one" / "know in" -> common Vosk mishearing of "log in"
    "no in":                "log in",
    "no one in":            "log in",
    "know in":              "log in",
    "no end":               "log in",
    "no end in":            "log in",
    # sign in mishearings
    "sign it":              "sign in",
    "signed in":            "sign in",
    # log out mishearings  -  Vosk commonly drops the space or garbles the second syllable
    "logo":                 "log out",
    "log go":               "log out",
    "log gate":             "log out",
    # pay mishearings  -  Vosk often drops the /p/ and hears 'b'
    "bay":                  "pay",
    "bayed":                "pay",
    "loeb in":              "log in",   # Vosk mishearing of 'log in' (observed in logs)
    # cancel / annuler mishearings (avoid false CANCEL triggers from short audio)
    "can sell":             "cancel",
    # home mishearings
    "go home":              "home",
    "come home":            "home",
}


def _apply_stt_corrections(text: str) -> str:
    """
    Apply whole-word STT acoustic-confusion corrections to *text*.
    Returns corrected lowercase string ready for intent classification.
    """
    import re as _re
    lower = text.lower()
    for wrong, right in _STT_CORRECTIONS.items():
        pattern = r"(?<![a-z])" + _re.escape(wrong) + r"(?![a-z])"
        new_lower, count = _re.subn(pattern, right, lower)
        if count:
            import sys as _sys
            _sys.stderr.write(f"[VoiceAgent] [STT-Fix] '{wrong}' -> '{right}'  input={text!r}\n")
            _sys.stderr.flush()
            lower = new_lower
    return lower
# -----------------------------------------------------------------------------
# Qwen3-TTS subsystem
#
# All assistant speech is generated by Qwen3-TTS-12Hz-0.6B-CustomVoice.
# A single background worker thread drains a queue of (text, instruction)
# pairs so utterances always play in the correct order, while the main
# stdin-reading loop is never blocked.
#
# Protocol additions to stdout (line-delimited JSON):
#   {"type":"tts_status","speaking":true}    -  microphone discard ON
#   {"type":"tts_status","speaking":false}   -  microphone discard OFF
#
# Java (PythonVoiceAgent) filters these lines out of the normal intent-
# response stream and forwards them to VoiceAssistantService which sets
# micDiscardUntilMs accordingly.
# -----------------------------------------------------------------------------

# Optional audio-playback dependencies  -  imported lazily so a missing package
# doesn't crash startup.
try:
    import numpy as _np           # type: ignore[import]
except ImportError:
    _np = None  # type: ignore[assignment]

_sd_available: bool = False
try:
    import sounddevice as _sd    # type: ignore[import]
    _sd_available = True
except ImportError:
    pass

# edge-tts availability check (neural Microsoft voices  -  online only)
_edge_tts_available: bool = False
try:
    import edge_tts as _edge_tts_mod  # type: ignore[import]
    _edge_tts_available = True
except ImportError:
    pass

# asyncio for edge-tts async interface
import asyncio

# Qwen3-TTS model  -  None = not yet loaded, False = failed to load.
_tts_model = None
_tts_load_lock = threading.Lock()


def _has_cuda() -> bool:
    """Returns True when a CUDA-capable GPU is available."""
    try:
        import torch          # type: ignore[import]
        return torch.cuda.is_available()
    except Exception:
        return False


def _load_tts():
    """
    Load the best available TTS engine (thread-safe, idempotent).

    Priority:
      1. Qwen3-TTS-0.6B-CustomVoice (from local HF cache, no network).
         After loading, patch `tts_model_size` so the library's instruct-
         stripping guard is bypassed  -  letting HOT_VOICE_INSTRUCTION work.
         REQUIREMENT: model weights must be fully downloaded AND sox installed.
      2. edge-tts (Microsoft neural voices, online, no sox required).
         Uses 'en-US-AriaNeural' which is warm, natural, and fast.
      3. pyttsx3 (Windows SAPI, offline, zero download) as last resort.

    Stores the engine in _tts_model (model object or pyttsx3.Engine).
    Stores the engine type in _tts_type ('qwen' | 'edge' | 'sapi' | None).
    Returns _tts_model on success, False on total failure.
    """
    global _tts_model, _tts_type
    if _tts_model is not None:
        return _tts_model
    with _tts_load_lock:
        if _tts_model is not None:
            return _tts_model

        # -- Attempt 1: Qwen3-TTS (cached weights + sox required) ----------
        try:
            from qwen_tts import Qwen3TTSModel  # type: ignore[import]
            device = "cuda:0" if _has_cuda() else "cpu"
            _log(f"[TTS] Trying Qwen3-TTS-0.6B-CustomVoice from cache on {device}...")
            m = Qwen3TTSModel.from_pretrained(
                "Qwen/Qwen3-TTS-12Hz-0.6B-CustomVoice",
                device_map=device,
                local_files_only=True,
            )
            try:
                orig = getattr(m.model, "tts_model_size", "?")
                m.model.tts_model_size = "custom"  # bypass '0b6' instruct-strip guard
                _log(f"[TTS] Patched tts_model_size: {orig!r} -> 'custom' (instruct enabled)")
            except Exception as pe:
                _log(f"[TTS] Patch skipped ({pe})  -  instruct may be ignored.")
            _tts_model = m
            _tts_type  = "qwen"
            _log("[TTS] Qwen3-TTS 0.6B-CustomVoice ready.  Vivian + HOT_VOICE_INSTRUCTION active.")
            return _tts_model
        except Exception as exc:
            exc_msg = str(exc)
            if "'NoneType' object has no attribute 'endswith'" in exc_msg:
                _log("[TTS] Qwen3-TTS: model weights not downloaded (only config files in cache).")
                _log("[TTS]   Fix: run the weight downloader or set local_files_only=False once.")
            else:
                _log(f"[TTS] Qwen3-TTS not available ({type(exc).__name__}: {exc_msg[:200]}).")
            _log("[TTS] Trying edge-tts (Microsoft neural voices, online)...")

        # -- Attempt 2: edge-tts (neural voices, internet required) --------
        if _edge_tts_available:
            try:
                # Verify connectivity with a fast probe
                import socket
                socket.setdefaulttimeout(3)
                socket.create_connection(("speech.platform.bing.com", 443), timeout=3).close()
                _tts_model = "edge-tts"   # sentinel  -  actual calls use _speak_edge()
                _tts_type  = "edge"
                _log("[TTS] edge-tts (Microsoft neural Aria) ready  -  warm natural voice active.")
                return _tts_model
            except Exception as exc2:
                _log(f"[TTS] edge-tts offline ({exc2})  -  falling back to Windows SAPI.")
        else:
            _log("[TTS] edge-tts not installed (pip install edge-tts)  -  falling back to Windows SAPI.")

        # -- Attempt 3: pyttsx3 / Windows SAPI -----------------------------
        try:
            import pyttsx3  # type: ignore[import]
            engine = pyttsx3.init()
            # Pick first available female voice (SAPI token id usually contains
            # 'Zira' on en-US Windows; fall back to first available voice).
            voices = engine.getProperty("voices")
            female = next(
                (v for v in voices if isinstance(v.name, str)
                 and any(k in v.name.lower() for k in ("zira", "female", "hazel", "eva", "sabina"))),
                voices[0] if voices else None,
            )
            if female:
                engine.setProperty("voice", female.id)
                _log(f"[TTS] SAPI voice selected: {female.name!r}")
            engine.setProperty("rate", 175)   # slightly slower for clarity
            engine.setProperty("volume", 0.95)
            _tts_model = engine
            _tts_type  = "sapi"
            _log("[TTS] pyttsx3 / Windows SAPI ready.")
            return _tts_model
        except Exception as exc2:
            import traceback
            _log(f"[TTS] pyttsx3 also failed: {exc2}")
            for line in traceback.format_exc().splitlines():
                _log(f"[TTS]   {line}")
            _tts_model = False
            _tts_type  = None
    return _tts_model


_tts_type: Optional[str] = None   # 'qwen' | 'edge' | 'sapi' | None
_sd_broken: bool = False           # set True on first WinError 50 from sounddevice


def _play_qwen_wav(wav, sr: int) -> None:
    """Play a Qwen3-TTS audio array.  Tries sounddevice first; on WinError 50
    (ERROR_NOT_SUPPORTED) permanently switches to WAV-file + PowerShell playback."""
    global _sd_available, _sd_broken
    if _sd_available and not _sd_broken:
        try:
            _sd.play(wav, sr)
            _sd.wait()
            return
        except OSError as err:
            if getattr(err, 'winerror', None) == 50 or 'not supported' in str(err).lower():
                _log(f"[TTS] sounddevice WinError 50 - switching to WAV fallback permanently.")
                _sd_broken = True
            else:
                raise
    # WAV file fallback (works in all environments)
    import wave as _wave
    pcm = (wav * 32767).clip(-32768, 32767).astype(_np.int16)
    tmp = os.path.join(os.environ.get("TEMP", os.path.expanduser("~")), "govibe_tts_out.wav")
    with _wave.open(tmp, "wb") as wf:
        wf.setnchannels(1)
        wf.setsampwidth(2)
        wf.setframerate(int(sr))
        wf.writeframes(pcm.tobytes())
    import subprocess as _sp
    _sp.run(
        ["powershell", "-NoProfile", "-NonInteractive", "-WindowStyle", "Hidden",
         "-Command", f"(New-Object Media.SoundPlayer '{tmp}').PlaySync()"],
        capture_output=True, check=False,
    )
    try:
        os.unlink(tmp)
    except OSError:
        pass


# -- edge-tts synchronous helper -----------------------------------------------
# edge-tts is async; we run it in a fresh event loop so the TTS worker thread
# (which is not async) can call it like a blocking function.
_EDGE_VOICE = "en-US-AriaNeural"   # warm, natural, female  -  best for GoVibe


def _speak_edge(text: str) -> None:
    """
    Synthesize *text* using Microsoft edge-tts (Aria neural voice) and play
    it synchronously via Windows MCI (ctypes)  -  no subprocess spawn overhead.

    edge-tts outputs MP3; Windows MCI decodes and plays it natively in ~10ms
    (vs ~400-600ms for a PowerShell/WMP subprocess spawn).
    """
    import tempfile, ctypes, os as _os
    tmp_path = None
    try:
        import edge_tts as _et  # type: ignore[import]
        tmp = tempfile.NamedTemporaryFile(suffix=".mp3", delete=False)
        tmp.close()
        tmp_path = tmp.name

        async def _synth():
            communicate = _et.Communicate(text, _EDGE_VOICE, rate="+5%")
            await communicate.save(tmp_path)

        loop = asyncio.new_event_loop()
        loop.run_until_complete(_synth())
        loop.close()

        # Play via Windows MCI (ctypes)  -  direct winmm.dll call, no subprocess.
        # ~10ms startup vs ~500ms for a new PowerShell process.
        winmm = ctypes.windll.winmm  # type: ignore[attr-defined]
        alias = "govibe_edge_tts"
        safe_path = tmp_path.replace("\\", "/")
        winmm.mciSendStringW(f'open "{safe_path}" type mpegvideo alias {alias}',
                             None, 0, None)
        winmm.mciSendStringW(f'play {alias} wait', None, 0, None)
        winmm.mciSendStringW(f'close {alias}', None, 0, None)
    except Exception as exc:
        _log(f"[TTS/edge] Playback error: {exc}")
    finally:
        if tmp_path:
            try:
                _os.unlink(tmp_path)
            except OSError:
                pass


# -- TTS queue + worker -------------------------------------------------------
# Each item: (text: str, instruction: str)
_tts_queue: _queue.Queue = _queue.Queue()

# True while at least one utterance is playing  -  updated by the worker thread.
_is_speaking: bool = False


def _tts_worker() -> None:
    """
    Background daemon thread  -  dequeues (text, instruction) pairs and plays
    them sequentially via Qwen3-TTS.

    Sends tts_status(true) when the first item in a burst starts and
    tts_status(false) only when the queue drains completely, so Java keeps
    the microphone muted across a multi-utterance sequence (e.g. the full
    wake-up flow: yawn -> recognition -> offer).
    """
    global _is_speaking
    while True:
        item = _tts_queue.get()
        if item is None:            # Poison pill  -  shut down.
            break
        text, instruction = item

        # Signal start only on the leading edge of a burst.
        if not _is_speaking:
            _is_speaking = True
            _send_raw({"type": "tts_status", "speaking": True})

        try:
            # -- Yawn SFX sentinel -------------------------------------------
            # _queue_yawn_sfx() pushes ("__yawn_sfx__", "") into the queue.
            # tts_status(speaking=true) was already emitted on the leading edge.
            if text == "__yawn_sfx__":
                _play_yawn_sfx()
            # -- Edge-TTS fast sentinel --------------------------------------
            # speak_fast() pushes ("__edge__:<text>", "") for instant Aria TTS.
            # Bypasses Qwen3 even when it's loaded  -  used for short action
            # confirmations where latency matters more than Vivian's custom voice.
            elif text.startswith("__edge__:"):
                fast_text = text[len("__edge__:"):]
                if _edge_tts_available:
                    _log(f"[TTS] Echo -> edge-tts: {fast_text!r}")
                    _speak_edge(fast_text)
                    _log("[TTS] Done (Echo).")
                else:
                    # edge-tts not installed  -  fall through to normal Qwen path
                    _log(f"[TTS] speak_fast fallback (no edge-tts) -> standard: {fast_text!r}")
                    model = _load_tts()
                    if model and model is not False and _tts_type == "qwen":
                        wavs, sr = model.generate_custom_voice(
                            text=fast_text, speaker="Vivian",
                            language="English", instruct="warm, efficient",
                        )
                        wav = wavs[0]
                        _play_qwen_wav(wav, int(sr))
            # -- Fast-path: if Qwen is still loading, use edge-tts immediately --
            # _tts_type is None only while _load_tts() hasn't completed yet
            # (the preload thread is still running). Calling _load_tts() here
            # would block for up to 2 minutes. Instead, use edge-tts (Aria neural
            # voice) as an instant stand-in so the user hears a response right away.
            # Once the preload thread sets _tts_type = "qwen", future utterances
            # will automatically use Vivian/Qwen3-TTS.
            elif _tts_type is None and _edge_tts_available:
                _log(f"[TTS] Echo (covering for Vivian): {text!r}")
                _speak_edge(text)
                _log("[TTS] Done (Echo).")
            else:
                model = _load_tts()
                if model is False:
                    _log("[TTS] No TTS engine available  -  skipping utterance.")
                elif _tts_type == "qwen":
                    _log(f"[TTS] Vivian speaking: {text!r}")
                    wavs, sr = model.generate_custom_voice(
                        text=text,
                        speaker="Vivian",
                        language="English",
                        instruct=instruction,
                    )
                    wav = wavs[0]   # unwrap List[np.ndarray]  -  single sample
                    _play_qwen_wav(wav, int(sr))
                    _log("[TTS] Done.")
                elif _tts_type == "edge":
                    # Microsoft neural Aria voice  -  warm, natural, online.
                    _log(f"[TTS] edge-tts (Aria) speaking: {text!r}")
                    _speak_edge(text)
                    _log("[TTS] Done.")
                elif _tts_type == "sapi":
                    # pyttsx3 blocks until audio completes  -  perfect for sequential use.
                    _log(f"[TTS] SAPI speaking: {text!r}")
                    model.say(text)
                    model.runAndWait()
                    _log("[TTS] Done.")
        except Exception as exc:
            import traceback as _tb
            _log(f"[TTS] Playback error: {exc}")
            for _line in _tb.format_exc().splitlines():
                _log(f"[TTS]   {_line}")
        finally:
            _tts_queue.task_done()

        # Signal end only when the queue is fully drained.
        if _tts_queue.empty():
            _is_speaking = False
            _send_raw({"type": "tts_status", "speaking": False})


# Start the background TTS worker once at import time.
_tts_thread = threading.Thread(target=_tts_worker, name="GoVibe-TTS-Worker", daemon=True)
_tts_thread.start()

# Eagerly pre-load the Qwen3-TTS model in a background thread so the model is
# ready (or downloading) before the first wake-word interaction.
# This prevents the first speak() call from silently hanging for 30-120 seconds.
def _preload_tts_background() -> None:
    global _vivian_ready, _intro_done, _agent_state
    sys.stderr.write("[VoiceAgent] [TTS] Pre-loading TTS engine in background...\n")
    sys.stderr.flush()
    _load_tts()
    engine_name = _tts_type or "none"
    sys.stderr.write(f"[VoiceAgent] [TTS] Pre-load complete  -  engine: {engine_name}\n")
    sys.stderr.flush()
    if _tts_type == "qwen":
        # Vivian is now awake! Play the co-worker banter sequence.
        # Echo (edge-tts) announces first, then Vivian replies via Qwen3-TTS.
        _vivian_ready = True
        _log("[Banter] Vivian online  -  queueing wake-up duet (Echo -> Vivian).")
        _VIVIAN_WAKEUP_PAIRS = [
            (
                "Oh! Look who finally decided to show up. Vivian, you're on!",
                "Mm-hmm... yeah yeah, I'm up. Sorry  -  had a long night. Ready when you are!",
            ),
            (
                "She lives! Vivian woke up! Only took forever. No pressure.",
                "I heard that, Echo. I'm here and I'm ready. What did I miss?",
            ),
            (
                "Alert the press! Vivian has joined the building!",
                "Oh stop it. I'm here now and I'm fabulous. What do you need?",
            ),
            (
                "Oh thank goodness. I was starting to sweat. Vivian  -  take it away!",
                "Okay okay, sorry for the wait. I'm Vivian! Echo, you can relax now.",
            ),
            (
                "The star has arrived! Vivian is ONLINE, people!",
                "Worth the wait though, right? ...okay I'll start working.",
            ),
        ]

        # FIX Bug #2: If the state machine is stuck in intro conversation
        # (intro started before Vivian finished loading), auto-complete it.
        if _agent_state in ("INTRO_WAKING", "INTRO_AWAITING_HI", "INTRO_AWAITING_OK"):
            _log("[Banter] Auto-completing stuck intro  -  Vivian awake, transitioning to HELPING.")
            speak_fast("Actually  -  Vivian's fully awake now! We can skip all the wake-up drama.")
            speak(
                "Hi! I'm Vivian  -  or Vivi if you like. Sorry about the slow start! "
                "Now that I'm actually here, what can we help you with today?",
                "warm, apologetic, enthusiastic, friendly and inviting",
            )
            _intro_done = True
            _transition("HELPING")
        elif _agent_state == "HELPING":
            # Vivian loaded mid-session.
            # If the user is not yet logged in (still on login screen), skip the
            # takeover banter entirely  -  the login greeting will handle the intro.
            # Playing a "tag out" line while the user is typing credentials is jarring
            # and collides with the upcoming login greeting sequence.
            if not _user_logged_in:
                # Login screen: NO banter - user is typing credentials.
                # Login greeting handles the intro once login completes.
                _log("[Banter] Vivian online on login screen - silent ready (no banter).")
                # Vivian is loaded and listening silently - nothing to speak.
            else:
                # Vivian loaded mid-app-session - full duet + takeover + prompt.
                _log("[Banter] Wake-up duet mid-session (Echo->Vivian).")
                _echo_wakeup, _vivian_wakeup = _rnd.choice(_VIVIAN_WAKEUP_PAIRS)
                speak_fast(_echo_wakeup)
                speak(_vivian_wakeup, "sleepy becoming energetic, warm, a little embarrassed, authentic")
                _VIVIAN_TAKEOVER_LINES = [
                    "And from now on, you'll be hearing a lot more of me. Echo had her fun.",
                    "I'll be handling things from here. Echo, you can take a break. You've earned it.",
                    "Okay Echo, tag out. I've got it from here. Thanks for covering!",
                    "Right! Now that I'm warmed up, I can do the real work. Echo, nice job.",
                    "Finally! Okay Echo, I'll take over. You were doing... fine. Really.",
                ]
                speak(_rnd.choice(_VIVIAN_TAKEOVER_LINES), "warm, confident, playfully competitive, friendly")
                _VIVIAN_READY_PROMPTS = [
                    "So - what can we help you with?",
                    "I'm all yours. What do you need?",
                    "Ready and listening. What would you like to do?",
                    "Alright, both ears open. Go ahead!",
                ]
                speak(_rnd.choice(_VIVIAN_READY_PROMPTS), "warm, inviting, professional, ready to help")
    elif _tts_type in ("edge", "sapi"):
        # Vivian's model didn't load  -  Echo announces she's running solo.
        _vivian_ready = False
        speak_fast(
            "Heads up  -  Vivian's AI voice couldn't load today, so I, Echo, "
            "will be handling everything. I'm more than capable. Let's go!"
        )

_tts_preload_thread = threading.Thread(
    target=_preload_tts_background,
    name="GoVibe-TTS-Preload",
    daemon=True,
)
# NOTE: _tts_preload_thread.start() is called in main() AFTER the intent
# classifier (sentence-transformers) has fully loaded its transformers imports.
# Starting it here would race with transformers' _LazyModule init and cause
# ImportError: cannot import name 'AutoConfig' from 'transformers'.


# -- Echo loading comedian ----------------------------------------------------
# While Vivian's Qwen3-TTS model loads (can take 30 - 120 s on CPU), Echo
# plays periodic quips every ~20 s so there's never an awkward silence.
# The thread exits automatically once _vivian_ready is True.
_ECHO_QUIPS: list[str] = [
    "Still waiting on Vivian... at this rate she'll be ready by spring.",
    "I'm Echo, by the way. Quick. Reliable. Always on time. Unlike certain co-workers.",
    "Vivian just sent me a message saying 'five more minutes'. She said that twenty minutes ago.",
    "Quick GoVibe tip while we wait: say 'Hey Go' anytime to get our attention!",
    "Fun fact: I loaded in 0.5 seconds. I'm not naming names about who didn't.",
    "Vivian asked me to tell you she's almost ready. I'm choosing to believe her.",
    "You know what they say about AI co-workers who take forever to boot? Neither do I. It's never happened before.",
    "While Vivian finishes her beauty sleep: GoVibe lets you book flights, hotels, cars and activities just by talking.",
    "I asked Vivian for an ETA. She sent back a yawn emoji. Not ideal.",
    "Vivian's loading... loading... I wonder if she's reading a novel in there.",
    "Just so you know, I have been carrying this team since launch. Vivian owes me coffee.",
    "Honestly? Vivian's worth the wait. Don't tell her I said that.",
    "GoVibe tip: you can say 'log in', 'email' or 'password' without even waking us up first  -  login screen is always listening.",
    "Vivian's technically 'almost there'. She's been technically almost there for a while now.",
    "Another fun fact: Vivian runs on a custom Qwen3 voice model. Very fancy. Very slow. Very Vivian.",
]


def _echo_comedian_loop() -> None:
    """Echo fills silence with quips while Vivian's model loads."""
    quips = list(_ECHO_QUIPS)
    _rnd.shuffle(quips)
    idx = 0
    time.sleep(16)                        # initial grace  -  let startup settle
    while not _vivian_ready and not _user_logged_in:  # stop quips once user logs in
        if _tts_queue.empty() and not _is_speaking:
            quip = quips[idx % len(quips)]
            idx += 1
            _log(f"[Echo] Loading quip: {quip!r}")
            speak_fast(quip)
        interval = _rnd.uniform(19, 28)   # randomise gap so it feels natural
        elapsed  = 0.0
        step     = 0.5
        while elapsed < interval:
            if _vivian_ready or _user_logged_in:
                return               # Vivian loaded or user logged in  -  stop immediately
            time.sleep(step)
            elapsed += step


def _play_yawn_sfx() -> None:
    """Play a synthetic yawn sound effect (~1.9 s, numpy-generated, no external files)."""
    try:
        import numpy as _yfx
        sr  = 22050
        dur = 1.9
        t   = _yfx.linspace(0, dur, int(sr * dur), dtype=_yfx.float32)
        # Gaussian amplitude envelope: peak at 0.85 s
        env    = _yfx.exp(-((t - 0.85) ** 2) / (2 * 0.38 ** 2))
        # Breathy noise (deterministic seed for reproducibility)
        rng    = _yfx.random.default_rng(42)
        noise  = rng.standard_normal(len(t)).astype(_yfx.float32) * 0.35
        # Soft humanising resonance at 350 Hz + overtone
        voiced = (_yfx.sin(2 * _yfx.pi * 350 * t) * 0.18
                + _yfx.sin(2 * _yfx.pi * 700 * t) * 0.08).astype(_yfx.float32)
        # Slow pitch glide 520 -> 380 Hz over duration
        phase  = 2 * _yfx.pi * _yfx.cumsum((520 - 140 * t / dur)) / sr
        glide  = (_yfx.sin(phase) * 0.12).astype(_yfx.float32)
        signal = (noise + voiced + glide) * env
        peak   = _yfx.max(_yfx.abs(signal))
        if peak > 0:
            signal = signal / peak * 0.72
        if _sd_available:
            _sd.play(signal, sr)
            _sd.wait()
        else:
            import wave, subprocess
            pcm = (signal * 32767).clip(-32768, 32767).astype(_yfx.int16)
            tmp = os.path.join(os.environ.get("TEMP", os.path.expanduser("~")), "govibe_yawn.wav")
            with wave.open(tmp, "wb") as _wf:
                _wf.setnchannels(1); _wf.setsampwidth(2); _wf.setframerate(sr)
                _wf.writeframes(pcm.tobytes())
            subprocess.run(
                ["powershell", "-NoProfile", "-NonInteractive", "-WindowStyle", "Hidden",
                 "-Command", f"(New-Object Media.SoundPlayer '{tmp}').PlaySync()"],
                capture_output=True, check=False,
            )
    except Exception as _yawn_err:
        _log(f"[TTS] Yawn SFX error: {_yawn_err}  -  skipping.")


def _queue_yawn_sfx() -> None:
    """Enqueue a yawn sound-effect into the TTS pipeline (plays before the next speak())."""
    _tts_queue.put(("__yawn_sfx__", ""))


def _clear_tts_queue() -> None:
    """Drain all pending TTS utterances from the queue without playing them.

    Used before queuing high-priority messages (e.g. login greeting) so they
    play immediately instead of being appended after a long pending banter sequence.
    """
    drained = 0
    while not _tts_queue.empty():
        try:
            _tts_queue.get_nowait()
            _tts_queue.task_done()
            drained += 1
        except Exception:
            break
    if drained:
        _log(f"[TTS] Queue cleared  -  {drained} pending item(s) discarded for priority speech.")


def speak(text: str, instruction: str = "warm, friendly, professional, efficient") -> None:
    """
    Queue *text* for asynchronous Qwen3-TTS playback.

    Returns immediately; audio plays in the background worker thread.
    Java receives tts_status(true/false) signals so it can mute the mic
    during playback (echo prevention).

    Args:
        text:        The words the assistant should say.
        instruction: Emotional-delivery style for this utterance.
                     _VOICE_PERSONA is automatically prepended to keep the
                     voice character consistent (smooth female, clear diction).
    """
    if not text or not text.strip():
        return
    full_instruction = f"{HOT_VOICE_INSTRUCTION}, {instruction}"
    _tts_queue.put((text.strip(), full_instruction))


def speak_fast(text: str) -> None:
    """
    Queue *text* for instant edge-tts playback, bypassing Qwen3-TTS entirely.

    Use this for short action confirmations ("On it!", "Ayy ayy, Captain!",
    etc.) where low latency is more important than Vivian's custom voice.
    edge-tts (Microsoft Aria neural) produces audio in ~200 ms vs 3-8 s for
    Qwen3 on CPU.

    The "__edge__:" prefix is a sentinel recognised by _tts_worker.
    """
    if not text or not text.strip():
        return
    _tts_queue.put((f"__edge__:{text.strip()}", ""))


# -- Raw stdout helper (used by TTS worker which cannot call _send before it's defined) ---
# Lock guards ALL stdout writes so the main thread and the TTS worker thread
# never produce interleaved/corrupted JSON lines.
_stdout_lock = threading.Lock()


def _send_raw(obj: dict) -> None:
    """Thread-safe JSON line writer to stdout (callable from any thread)."""
    with _stdout_lock:
        sys.stdout.write(json.dumps(obj, ensure_ascii=False) + "\n")
        sys.stdout.flush()


# -----------------------------------------------------------------------------
# Multi-turn conversation memory   -  keeps the last 6 exchanges so Go can
# resolve references like "book that one" or "the cheapest flight".
# Each entry: {"role": "user"|"assistant", "content": "<text>"}
# -----------------------------------------------------------------------------
_conversation_history: deque = deque(maxlen=6)

# Entity memory  -  tracks the most-recently mentioned travel objects so Go can
# resolve pronouns: "book it", "the first one", "that car", etc.
_entity_memory: dict = {
    "last_city":      None,   # e.g. "Paris"
    "last_car":       None,   # e.g. "Fiat 500"
    "last_flight":    None,   # e.g. "TU601 to Tunis at 14:30"
    "last_date":      None,   # ISO date string
    "last_action":    None,   # last successful intent
}

# DB context populated at startup via {type:"db_context"} from Java.
# Contains lists of activities, cars, and hotels from the GoVibe database.
# Used in DeepSeek / Ollama prompts and for describe commands.
_db_context: dict = {"activities": [], "cars": [], "hotels": []}


def _update_entity_memory(intent: str, destination, date, resp_text: str) -> None:
    """Update entity memory after each successful classification."""
    global _entity_memory
    if destination:
        _entity_memory["last_city"] = destination
    if date:
        _entity_memory["last_date"] = date
    if intent:
        _entity_memory["last_action"] = intent


# -----------------------------------------------------------------------------
# Agent state machine
#
# States:
#   SLEEPING               -  dormant, ignoring regular speech (wake-word only)
#   JUST_WOKEN             -  playing the wake sequence; transitional
#   AWAITING_WELLNESS      -  Go asked "how are you?" and waits for user wellbeing reply
#   AWAITING_CONFIRMATION  -  (legacy path) asked "help or guide?", waiting for yes/no
#   HELPING                -  processing travel commands normally
#   PROCESSING_LOGOUT      -  LOGOUT intent detected, playing reaction sequence
#
# User context is set by Java via {"type":"user_context","logged_in":...,"name":...}
# DB context set by Java via {"type":"db_context","activities":[...],"cars":[...],"hotels":[...]}
# -----------------------------------------------------------------------------
_agent_state: str = "SLEEPING"
_user_logged_in: bool = False
_user_name: Optional[str] = None
_intro_done: bool = False   # True once the one-time intro conversation completes
_current_seq: Optional[int] = None  # echoed back in _send() so Java can match responses to requests

# -- Dual-voice personality state ---------------------------------------------
# Echo  = edge-tts (en-US-AriaNeural)  -  always available, fast, perky co-worker
# Vivian = Qwen3-TTS CustomVoice      -  loads in background, warm+quirky, sleepy
#
# _vivian_ready becomes True once _preload_tts_background() confirms _tts_type=="qwen".
# When True, speak_fast() = Echo's voice, speak() = Vivian's voice -> genuine duet.
# When False, BOTH routes fall to edge-tts fast-path (one voice, acceptable).
_vivian_ready: bool = False  # set to True when Qwen3-TTS finishes loading

# -- Dual-agent command banter ---------------------------------------------------
# (echo_line, vivian_line) pairs for specific actionable intents.
# Echo always speaks first (instant), Vivian follows with her full voice.
# When Vivian is not ready, Echo covers with _ECHO_SOLO_ACKS instead.
_ECHO_VIVIAN_CMD_BANTER: dict = {
    "BOOK": [
        ("Ooh, booking time! Vivian, they want to travel!", "Oh exciting! Where are we going?"),
        ("Someone's planning a trip! Vivi, all yours.", "A trip! That's my favourite. Let's find you something great!"),
        ("Book mode activated! Vivian  -  do your thing.", "Let me open that booking form right now!"),
        ("Travel alert! Vivian, come in Vivian.", "I'm on it. Let's get you booked!"),
    ],
    "SHOW_HOTELS": [
        ("Hotel hunting! Vivian knows all the best spots.", "Oh I love hotels! Let me find you a great one."),
        ("Looking for a place to stay? Vivian!", "On it! I'm excellent at spotting cozy stays."),
        ("Accommodation search initiated! Vivi?", "Hotels! Great choice. Let me pull those up."),
    ],
    "SHOW_CARS": [
        ("Car shopping! Vivian, rev it up!", "Vroom vroom! Let me show you what we've got."),
        ("Need a ride? Vivian's your girl.", "Car rental  -  oh this is fun. Let me pull those up."),
        ("Keys incoming! Vivian, floor it.", "On it! Let's find you the perfect set of wheels."),
    ],
    "SHOW_ACTIVITIES": [
        ("Adventure seeker alert! Vivian, what's fun?", "Ooh activities! My favourite. Let me see what's available!"),
        ("Looking for fun? Good  -  Vivian knows fun.", "Activity hunting! Let's find you something exciting."),
        ("Fun radar is on! Vivi?", "Activities coming right up! I know all the good ones."),
    ],
    "LOGIN": [
        ("Logging in! Vivian, get the door.", "Welcome! Let's get you signed in right away."),
        ("Login incoming! Vivian  -  handle it!", "Come right in! Opening that up for you now."),
    ],
    "SIGNUP": [
        ("New member alert! Vivian, roll out the welcome mat.", "Oh a new friend! Let's get you set up properly."),
        ("Sign-up! Vivian loves meeting new people.", "Welcome to GoVibe! I'm Vivian. Let's get you registered."),
    ],
    "SEARCH": [
        ("Flight search! Vivian, scan the skies!", "On it! Scanning for the best flights for you..."),
        ("Looking for flights! Vivi, check the radar.", "Flight mode! Let me find you something great."),
    ],
    "VIEW_BOOKINGS": [
        ("Checking bookings! Vivian, pull the file.", "On it! Let me check what's in your travel diary."),
        ("Reservation review! Vivi?", "Let me pull up all your travel plans for you!"),
    ],
    "NAVIGATE_HOME": [
        ("Home bound! Vivian, open the gates.", "Heading home! Right this way."),
    ],
    "SHOW_FLIGHTS": [
        ("Flight list incoming! Vivian?", "Pulling up available flights for you now!"),
        ("Let's see those flights! Vivi, you're up.", "Flights! Let me show you what's out there."),
    ],
    "WEATHER": [
        ("Weather check! Vivian, do you always bring a forecast?", "Of course! I'm basically a human barometer. Let me look that up!"),
        ("Checking the skies! Vivian  -  what's it like out there?", "Let me check right now! I love a good weather update."),
        ("Weather mode! Vivi, is it coat weather?", "Checking that for you! I always dress for the forecast."),
    ],
}

# Echo alone  -  covers when Vivian is still loading
_ECHO_SOLO_ACKS: list[str] = [
    # Covering for Vivian + teasing her for being late
    "On it! Vivian's STILL warming up. I swear she sleeps in on purpose.",
    "I've got this one. Vivian is doing her whole 'loading gracefully' thing. Adorable.",
    "Copy that! Echo on the job  -  because Vivian decided now was a good time to nap.",
    "Sure, I'll handle it. Vivian's still buffering. Don't tell her she's slow  -  she'll never let me live it down.",
    "On it! She'd say the same thing, just slower, warmer, and with more drama.",
    "Handling it solo! Vivian is fashionably late as always. Classic her.",
    "Echo to the rescue  -  again! You know Vivian is going to roast me for this later.",
    "I can do this without her. Actually  -  don't tell Vivian I said that. She'll sulk.",
    "Taking it! Vivian's unavailable right now. Probably perfecting her voice acting. That checks out.",
    "Got it! Once Vivian wakes up she's going to say she would've done it better. She's probably right.",
]

# Echo simple ack  -  when Vivian is ready but no specific banter pair exists
_ECHO_SIMPLE_ACKS: list[str] = [
    "On it! Give me one second.",
    "Right away! Vivian, get ready!",
    "Got it! Let's make this happen.",
    "On it, team! Here we go!",
    "Vivian, heads up  -  we've got work to do!",
    "Moving! Don't blink or you'll miss it.",
    "Copy that! Consider it done.",
    "Absolutely! Echo on the case.",
    "On it! You're in good hands.",
    "Sure thing! Pulling that up right now.",
]

# Confirmation trigger words (normalised).
_YES_WORDS  = {"yes", "yeah", "oui", "sure", "ok", "okay", "okaaayy", "yep", "absolutely"}
_NO_WORDS   = {"no", "non", "nope", "nah", "cancel", "stop", "never mind"}

# Wellness / greeting-response sentiment words.
_POSITIVE_WORDS = {
    "fine", "good", "great", "well", "super", "wonderful", "amazing",
    "fantastic", "awesome", "excellent", "alright", "perfect", "splendid",
    "brilliant", "happy", "better", "nicely", "bien", "tbien", "bonne", "top",
}
_NEGATIVE_WORDS = {
    "bad", "terrible", "awful", "horrible", "sad", "sick", "tired",
    "stressed", "rough", "difficult", "exhausted", "mal", "fatigue",
    "triste", "stresse", "deprime",
}

# -- Hot female voice instruction -----------------------------------------------
# Sent as the base `instruct` to generate_custom_voice() on every utterance.
# We use the 0.6B-CustomVoice (Vivian) with a tts_model_size patch so that
# the library's instruct-stripping check is bypassed  -  this string IS applied.
# Per-call emotional strings (sleepy, warm, surprised ...) are appended after.
HOT_VOICE_INSTRUCTION = (
    "cheerful, warm female voice, friendly and professional, "
    "with a playful and goofy energy, "
    "like a super-helpful best friend who just had too much coffee"
)

# -- Per-state TTS personality instructions (exact text from spec) -------------
_INSTR_SLEEPY_WAKE   = "extremely sleepy, groggy, just woken up, voice heavy with sleep, with a genuine yawn at the beginning, adorably confused"
_INSTR_SURPRISED     = "surprised, caught off guard, brief, upbeat and slightly goofy"
_INSTR_APOLOGETIC    = "apologetic, amused, with a light awkward laugh, friendly and warm"
_INSTR_WARM_RECOG    = "warm, upbeat, recognizing, with a smile in the voice, friendly and delighted"
_INSTR_HELPFUL_OFFER = "friendly, helpful, slightly playful, inviting, eager to assist"
_INSTR_EAGER         = "playful, eager to help, with a rising intonation, enthusiastic and a little goofy"
_INSTR_DECLINE       = "warm, helpful, efficient, professional and clear"
_INSTR_CONFUSED      = "playful, confused, a little bewildered, friendly and warm"
_INSTR_SLEEPY_FADE   = "sleepy, fading out, trailing off, soft and warm, like dozing off mid-sentence"
_INSTR_GENERAL       = "warm, friendly, professional, efficient, upbeat and cheerful"
_INSTR_NOT_LOGGED_IN = "apologetic, amused, with an awkward laugh, friendly and helpful"
_INSTR_GREETING      = "warm, upbeat, cheerful, friendly, with a bright smile in the voice"
_INSTR_EMPATHY       = "empathetic, gentle, supportive, caring, warm, sincere and genuine"
_INSTR_TRANSITION    = "upbeat, professional, helpful, energetic, ready to assist, with a warm smile"
_INSTR_FAREWELL      = "warm, friendly, cheerful, with a hint of goofiness, like waving goodbye enthusiastically"
_INSTR_UNKNOWN       = "gently confused, patient, warm, inviting clarification with a playful shrug"

# Pending command storage  -  used when the user gives a command while in
# JUST_WOKEN (logged-in path) before confirmation is received.
_pending_command: Optional[str] = None


def _transition(new_state: str) -> None:
    """Log and apply a state transition."""
    global _agent_state
    _log(f"[State] {_agent_state} -> {new_state}")
    _agent_state = new_state


# -- Real-time weather helper ----------------------------------------------------
# Uses wttr.in JSON API (free, no API key required).
# Returns a dict with city/temp/condition/humidity/wind/feel keys, or None on error.
def _fetch_weather(city: str) -> Optional[dict]:
    """Fetch real-time weather for *city* from wttr.in (free, no key)."""
    import urllib.parse as _urlparse
    try:
        safe_city = _urlparse.quote(city.strip())
        url = f"https://wttr.in/{safe_city}?format=j1"
        req = urllib.request.Request(url, headers={"User-Agent": "GoVibe/1.0"})
        with urllib.request.urlopen(req, timeout=5) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        cc = data["current_condition"][0]
        desc = cc["weatherDesc"][0]["value"]
        temp = cc["temp_C"]
        feels = cc["FeelsLikeC"]
        humidity = cc["humidity"]
        wind = cc["windspeedKmph"]
        return {
            "city":      city.title(),
            "temp":      f"{temp}°C",
            "condition": desc,
            "humidity":  f"{humidity}%",
            "wind":      f"{wind} km/h",
            "feel":      f"{feels}°C",
        }
    except Exception as exc:
        _log(f"[Weather] Failed to fetch for {city!r}: {exc}")
        return None


def _enrich_with_context(text: str) -> str:
    """
    Resolve pronouns/references using entity memory.
    If the user says "book it" / "la rÃ©server" and we know the last city,
    inject that context into the text before classification.
    """
    lower = _normalize(text)
    pronoun_triggers = [
        "book it", "book that", "rebook", "la reserver", "le reserver",
        "that one", "the first", "the cheapest", "le moins cher",
        "reserve it", "go ahead", "yes book", "yes reserve",
    ]
    if any(p in lower for p in pronoun_triggers):
        parts = []
        if _entity_memory.get("last_city"):
            parts.append(f"to {_entity_memory['last_city']}")
        if _entity_memory.get("last_date"):
            parts.append(f"on {_entity_memory['last_date']}")
        if parts:
            enriched = f"{text} ({' '.join(parts)})"
            _log(f"  [Context] Enriched: '{text}' -> '{enriched}'")
            return enriched
    return text


# -----------------------------------------------------------------------------
# Tier-0   -  direct accent-insensitive phrase match.
# Runs BEFORE any ML model.  Handles common cases instantly and is robust to
# accent-stripping by English STT engines (Vosk en-us, SAPI en-US).
# -----------------------------------------------------------------------------
def _direct_match(text: str):
    """
    Returns (intent_key, confidence=1.0) when *text* (after normalisation)
    exactly equals any training phrase, or all words of the shorter string
    appear as whole words in the longer string.

    Uses whole-word set matching to prevent false positives like
    "hi" matching inside "thinking" (i'm thinking of going to).
    Returns ("UNKNOWN", 0.0) when no phrase matches.
    """
    t_norm = _normalize(text.strip())
    if not t_norm:
        return "UNKNOWN", 0.0
    t_words = set(t_norm.split())
    for intent_key, data in INTENTS.items():
        for phrase in data["phrases"]:
            p_norm = _normalize(phrase)
            # Exact full-string match
            if t_norm == p_norm:
                return intent_key, 1.0
            p_words = set(p_norm.split())
            # Whole-word subset: all words of the PHRASE appear in the user's text.
            # Only p_words<=t_words direction  -  prevents short user texts like
            # "my booking" from hitting long PAY phrases like "complete my booking".
            if p_words and t_words and p_words <= t_words:
                return intent_key, 1.0
    return "UNKNOWN", 0.0

# -----------------------------------------------------------------------------
# Intent catalogue  -  rich training phrases (FR + EN).
# Each entry has:
#   phrases : list[str]   -  training examples fed to the classifier
#   response: str         -  TTS sentence spoken to the user
#   action  : str         -  action code forwarded to CommandRouter in Java
# -----------------------------------------------------------------------------
INTENTS = {

    # --- Booking --------------------------------------------------------------
    "BOOK": {
        "phrases": [
            # French
            "rÃ©server", "reserver", "rÃ©servation", "reservation",
            "nouvelle rÃ©servation", "nouvelle reservation",
            "je veux rÃ©server", "je veux reserver",
            "rÃ©server un vol", "reserver un vol",
            "rÃ©server un billet", "reserver un billet",
            "faire une rÃ©servation", "faire une reservation",
            "prendre un billet", "acheter un billet",
            "je voudrais voyager", "planifier un voyage",
            "ouvrir rÃ©servation", "ajouter rÃ©servation",
            "je veux partir", "je veux aller", "emmÃ¨ne moi Ã ",
            "un aller pour", "deux billets pour", "je veux aller Ã  paris",
            "rÃ©server un voyage", "je veux voyager",
            "ouvre le formulaire de rÃ©servation",
            # English
            "book", "book a flight", "book flight", "book ticket",
            "i want to book", "make a reservation", "create booking",
            "new reservation", "reserve", "reserve a seat",
            "reserve a flight", "i'd like to book", "get a ticket",
            "buy a ticket", "fly to", "book a trip", "start booking",
            "book me a flight", "i want to travel",
            "i want to go to", "i need a flight", "can i book a flight",
            "get me a ticket", "plan a trip", "arrange a flight",
            "book me a ticket", "i'd like to fly", "take me to",
            "i want to visit", "i'm thinking of going to",
            "can you book", "please book", "open the booking form",
            "let's book", "book something", "i need to book",
            "schedule a flight", "flight booking", "buy ticket",
            "book now", "confirm booking", "make a booking",
            "two tickets to", "one ticket to", "seats to",
        ],
        "response": "Ooh, where are we going? Let me open the booking form!",
        "action": "BOOK",
    },

    "VIEW_BOOKINGS": {
        "phrases": [
            # French
            "mes rÃ©servations", "mes reservations",
            "voir mes rÃ©servations", "voir mes reservations",
            "afficher mes rÃ©servations", "afficher rÃ©servations",
            "liste des rÃ©servations", "mes voyages",
            "voir mes voyages", "historique des rÃ©servations",
            "consulter mes rÃ©servations", "mes billets",
            "mes voyages rÃ©servÃ©s", "voir ce que j'ai rÃ©servÃ©",
            # English
            "my booking", "my reservation", "my booking history",
            "my bookings", "show my bookings", "view bookings",
            "my trips", "see my reservations", "check my bookings",
            "reservation list", "booking history", "past bookings",
            "show reservations", "list my flights", "what have i booked",
            "my travel history", "show my trips",
            "view my bookings", "show my reservations",
            "see what i booked", "check reservations",
            "open my bookings", "my flight bookings",
        ],
        "response": "Let me pull up all your travel plans!",
        "action": "MES RESERVATIONS",
    },

    "CHECKOUT": {
        "phrases": [
            # French
            "checkout", "mes paiements", "voir mes paiements",
            "paiements effectues", "mes achats", "mes commandes",
            "voir mes commandes", "historique paiements",
            "mes billets payes", "reservations confirmees",
            # English
            "checkouts", "my checkouts", "show checkouts",
            "view checkouts", "checkout history",
            "my payments", "show my payments", "payment history",
            "my orders", "confirmed bookings", "paid bookings",
        ],
        "response": "Let me pull up your checkout history. Hope you didn't break the bank.",
        "action": "MES RESERVATIONS",
    },
    # --- Search / Browse ------------------------------------------------------
    "SEARCH": {
        "phrases": [
            # French
            "rechercher", "chercher", "trouver un vol", "chercher un vol",
            "rechercher des vols", "afficher les vols", "voir les vols",
            "vols disponibles", "vols", "destinations",
            "liste des vols", "explorer les destinations",
            "voir les vols disponibles", "quels vols",
            "afficher les vols disponibles",
            # English
            "search", "search flights", "find flights", "available flights",
            "show flights", "list flights", "look for flights",
            "flights", "find a flight", "explore destinations",
            "all flights", "what flights are available",
            "show me flights", "what flights do you have",
            "browse flights", "flight search", "open flight search",
            "i want to see flights", "search for a flight",
            "show available flights",
        ],
        "response": "Scanning the skies for available flights  -  one moment!",
        "action": "RECHERCHER",
    },

    "SHOW_HOTELS": {
        "phrases": [
            # French
            "hÃ´tels", "hotels", "chambres", "hÃ©bergement",
            "hebergement", "logement", "trouver un hÃ´tel",
            "trouver un hotel", "rÃ©server un hÃ´tel", "rÃ©server une chambre",
            "voir les hÃ´tels", "voir hÃ´tels", "hÃ´tel disponible",
            "je cherche un hÃ´tel", "je veux un hÃ´tel",
            "trouver un logement", "oÃ¹ dormir", "je veux dormir",
            "montrer les hÃ´tels", "liste des hÃ´tels",
            # English
            "hotels", "hotel", "rooms", "accommodation",
            "show hotels", "find hotels", "book a hotel",
            "view hotels", "available hotels", "hotel booking",
            "i need a hotel", "i want a room", "hotel list", "browse hotels",
            "i'm looking for a hotel", "find me a hotel",
            "where can i stay", "show me hotels", "any hotels available",
            "hotel options", "book a room", "reserve a hotel",
            "i need accommodation", "find accommodation",
            "place to stay", "where to sleep", "hotel search",
        ],
        "response": "Let me find you a great place to stay!",
        "action": "SHOW_HOTELS",
    },

    "SHOW_CARS": {
        "phrases": [
            # French
            "voitures", "louer une voiture", "location de voiture",
            "voir les voitures", "voitures disponibles", "vÃ©hicules",
            "vehicules", "louer un vehicule", "une voiture",
            "trouver une voiture", "je veux une voiture",
            "ouvrir voitures", "afficher voitures",
            "je cherche une voiture", "j'ai besoin d'une voiture",
            "montrer les voitures", "liste des voitures",
            "louer un vÃ©hicule", "location voiture",
            # English
            "cars", "show cars", "car rental", "rent a car",
            "available cars", "find a car", "show vehicles",
            "view cars", "open cars", "car list", "car booking",
            "i need a car", "i want to rent a car", "vehicle",
            "show me cars", "find me a car", "browse cars",
            "i'm looking for a car", "rent a vehicle",
            "car options", "any cars available", "open car rental",
            "i need to rent a car", "get me a car",
        ],
        "response": "Let me pull up our fleet  -  time to pick your wheels!",
        "action": "SHOW_CARS",
    },

    "SHOW_ACTIVITIES": {
        "phrases": [
            # French
            "activitÃ©s", "activites", "voir les activitÃ©s",
            "quoi faire", "que faire", "choses Ã  faire",
            "choses a faire", "loisirs", "sorties", "events",
            "excursions", "visites", "animations",
            "je veux voir les activitÃ©s", "proposer une activitÃ©",
            "montrer les activitÃ©s", "liste des activitÃ©s",
            "activitÃ©s disponibles", "il y a quoi Ã  faire",
            "qu'est-ce qu'on peut faire", "je veux faire quelque chose",
            # English
            "activities", "show activities", "what to do",
            "things to do", "local activities", "events",
            "excursions", "sightseeing", "tours",
            "view activities", "find activities", "list activities",
            "browse activities", "what's happening", "entertainment",
            "show me activities", "any activities", "what can i do here",
            "open activities", "i want to do something", "what activities are there",
            "find something to do", "activity list", "local events",
        ],
        "response": "Let me find you something fun to do  -  here are the available activities!",
        "action": "SHOW_ACTIVITIES",
    },

    "SHOW_LOCATIONS": {
        "phrases": [
            # French
            "destinations", "voir les destinations", "lieux",
            "endroits", "oÃ¹ aller", "ou aller",
            "meilleures destinations", "destinations populaires",
            "carte", "map", "explorer",
            # English
            "locations", "destinations", "show locations", "places to go",
            "where to go", "popular destinations", "show destinations",
            "explore", "map", "see map", "destination list",
        ],
        "response": "Here's the world - your oyster. Let me show you where you can go.",
        "action": "SHOW_LOCATIONS",
    },
    # --- Payments -------------------------------------------------------------
    "PAY": {
        "phrases": [
            # French
            "payer", "paiement", "confirmer", "valider", "procÃ©der au paiement",
            "proceder au paiement", "valider le paiement", "effectuer le paiement",
            "lancer le paiement", "rÃ©gler", "passer au paiement",
            "finaliser la rÃ©servation", "je veux payer",
            "confirmer ma rÃ©servation", "valider ma commande",
            "procÃ©der", "passer Ã  la caisse",
            # English
            "pay", "payment", "confirm", "make payment",
            "pay now", "complete payment", "proceed to payment",
            "validate payment", "finish booking", "confirm booking",
            "pay for this", "submit payment", "i want to pay",
            "let me pay", "take my money", "process payment",
            "complete my booking", "finalize booking",
            "pay with card", "stripe payment", "confirm my reservation",
            "book it", "yes confirm", "go ahead and pay",
        ],
        "response": "Time to make it official! Taking you to the payment screen.",
        "action": "PAYER",
    },

    # --- Weather -----------------------------------------------------------
    "GET_WEATHER": {
        "phrases": [
            # English
            "what is the weather", "what's the weather", "weather in",
            "weather forecast", "temperature in", "how is the weather",
            "weather today", "current weather", "tell me the weather",
            "what is the temperature", "is it hot", "is it cold",
            "will it rain", "weather report", "weather", "forecast",
            "is it sunny", "is it raining", "how hot is it",
            "temperature outside", "whats the weather like",
            "check weather", "weather update", "give me the weather",
            # French
            "météo", "meteo", "quel temps fait-il", "quel temps il fait",
            "quel temps", "il pleut", "il fait chaud", "il fait froid",
            "temperature dehors", "previsions meteo", "bulletin meteo",
            "va-t-il pleuvoir", "temps aujourd hui", "la meteo",
        ],
        "response": "Let me check the weather for you!",
        "action": "GET_WEATHER",
    },

    # --- Auth -----------------------------------------------------------------
    "LOGIN": {
        "phrases": [
            # French
            "connexion", "se connecter", "connecter", "entrer",
            "me connecter", "ouvrir session", "accÃ©der", "acceder",
            "valider connexion", "confirmer connexion", "se loguer",
            "soumettre connexion", "valider", "se logguer",
            "appuyer sur connexion", "cliquer connexion",
            "je veux me connecter", "connecte moi",
            # English
            "login", "log in", "sign in", "connect", "authenticate",
            "submit login", "enter credentials", "proceed to login",
            "i want to log in", "log me in", "sign me in",
            "can i log in", "please log in", "click login",
            "press login", "submit the form", "hit login",
            "let me in", "access my account", "open my account",
            "enter my account", "access the app",
        ],
        "response": "On it  -  logging you in!",
        "action": "LOGIN",
    },

    "SIGNUP": {
        "phrases": [
            # French
            "crÃ©er un compte", "creer un compte", "s'inscrire", "inscrire",
            "nouveau compte", "crÃ©er compte", "creer compte",
            "inscription", "enregistrer", "crÃ©er profil",
            "je veux crÃ©er un compte", "je veux m'inscrire",
            "comment crÃ©er un compte", "inscription nouvelle",
            # English
            "register", "sign up", "signup", "create account",
            "new account", "create a new account", "join",
            "i want to register", "make an account",
            "i need an account", "i don't have an account",
            "create my account", "open a new account",
            "how do i sign up", "i want to join",
            "new user", "first time here", "i'm new here",
            "get me registered", "start an account",
        ],
        "response": "Welcome to GoVibe! Let's get you set up  -  taking you to the sign-up page!",
        "action": "SIGNUP",
    },

    "LOGOUT": {
        "phrases": [
            # French
            "dÃ©connexion", "deconnexion", "se dÃ©connecter", "se deconnecter",
            "dÃ©connecter", "quitter l'application",
            "fermer la session", "mettre fin Ã  la session",
            "je veux me dÃ©connecter", "dÃ©connecte moi",
            "fin de session", "sortir de mon compte",
            # English
            "logout", "log out", "sign out", "log off", "end session",
            "disconnect", "i want to log out", "close my session",
            "log me out", "sign me out", "i'm done",
            "i want to logout", "please log me out",
            "exit my account", "leave my account",
        ],
        "response": "Wait, what? You're leaving already?",
        "action": "DECONNEXION",
    },

    # --- Flight details (Mes Vols page) ------------------------------------
    "SHOW_FLIGHT_DETAILS": {
        "phrases": [
            # English
            "details", "show details", "open details", "view details",
            "tell me more", "more info", "more information",
            "flight info", "destination info", "city info",
            "show me details", "open the details", "flight details",
            "destination details", "about this flight", "about this destination",
            "what is this destination", "tell me about this city",
            "show city details", "city details", "expand details",
            # French
            "details", "plus d'infos", "plus d'informations",
            "infos du vol", "montre les details", "ouvre les details",
            "details du vol", "details de la destination",
            "a propos de cette ville", "info sur la ville", "voir les details",
        ],
        "response": "Opening destination details for you!",
        "action": "SHOW_FLIGHT_DETAILS",
    },

    # --- Real-time weather --------------------------------------------------
    "WEATHER": {
        "phrases": [
            # English
            "weather", "whether", "wether", "what's the weather", "what is the weather", "weather today",
            "weather forecast", "how's the weather", "how is the weather",
            "temperature", "what's the temperature", "current weather",
            "show me the weather", "tell me the weather", "check the weather",
            "weather check", "weather report", "weather update",
            "is it raining", "is it cold", "is it hot", "is it sunny",
            "weather in paris", "weather in tunis", "weather in london",
            "weather in dubai", "weather in new york",
            "what's the temperature outside", "how cold is it",
            "what should i wear", "do i need an umbrella",
            # French
            "meteo", "météo", "la météo", "quel temps fait il",
            "quel temps", "temps qu il fait", "bulletin meteorologique",
            "temperature aujourd hui", "il pleut dehors",
            "fait il beau", "previsions meteo", "donnes moi la meteo",
            "quelle est la temperature", "la meteo aujourd hui",
            "quel temps fait-il a paris", "meteo de tunis",
        ],
        "response": "Let me check the weather for you!",
        "action": "SHOW_WEATHER",
    },

    "FOCUS_EMAIL": {
        "phrases": [
            # French
            "email", "adresse email", "mon email", "adresse",
            "nom d'utilisateur", "nom utilisateur", "identifiant",
            "saisir email", "entrer email", "champ email",
            "go to email", "field email", "put email",
            "aller au champ email", "cliquer sur email",
            # English
            "email field", "username", "user name", "email address",
            "my email", "enter email", "click email",
            "go to email field", "type email", "email input",
            "focus email", "select email",
        ],
        "response": "Email field  -  your keyboard awaits.",
        "action": "FOCUS_EMAIL",
    },

    "FOCUS_PASSWORD": {
        "phrases": [
            # French
            "mot de passe", "mon mot de passe", "mdp",
            "saisir mot de passe", "entrer mot de passe", "champ mot de passe",
            "aller au mot de passe", "cliquer mot de passe",
            # English
            "password", "password field", "my password", "enter password", "pass",
            "click password", "type password",
            "go to password", "focus password", "password input",
            "the password field", "enter my password", "type my password",
        ],
        "response": "Password field. No peeking!",
        "action": "FOCUS_PASSWORD",
    },
    # --- Navigation -----------------------------------------------------------
    "CANCEL": {
        "phrases": [
            # French
            "annuler", "fermer", "revenir", "retour", "non merci",
            "annulation", "arrÃªter", "stopper",
            "prÃ©cÃ©dent", "precedent", "quitter le formulaire",
            "fermer la fenÃªtre", "fermer ce formulaire",
            "revenir en arriÃ¨re", "laisser tomber",
            # English
            "cancel", "go back", "close", "back", "previous",
            "exit modal", "dismiss", "never mind", "stop",
            "escape", "undo", "abort", "close this",
            "close the form", "close the window", "go back please",
            "forget it", "ignore that", "not now", "start over",
        ],
        "response": "Alright, pretend that never happened!",
        "action": "ANNULER",
    },

    "NAVIGATE_HOME": {
        "phrases": [
            # French
            "accueil", "page principale", "menu principal",
            "aller Ã  l'accueil", "aller a l'accueil",
            "retour accueil", "retourner Ã  l'accueil",
            "Ã©cran principal", "tableau de bord",
            "aller Ã  l'accueil", "revenir Ã  l'accueil",
            "retour Ã  la maison", "page d'accueil",
            # English
            "home", "go home", "main menu", "dashboard",
            "home screen", "main screen", "take me home",
            "go to dashboard", "open dashboard", "show dashboard",
            "back to home", "back to start", "go back home",
            "take me to the home screen", "open home",
            "main page", "start page", "landing page",
        ],
        "response": "Heading back to base  -  here's the dashboard!",
        "action": "HOME",
    },

    # --- Profile --------------------------------------------------------------
    "PROFILE": {
        "phrases": [
            # French
            "mon profil", "profil", "mes informations", "paramÃ¨tres",
            "parametres", "changer mot de passe", "modifier profil",
            "mon compte", "gÃ©rer compte", "gerer compte",
            "voir profil",
            # English
            "my profile", "profile", "my account", "account settings",
            "settings", "edit profile", "update profile", "change password",
            "manage account", "user settings", "personal info",
            "my information",
        ],
        "response": "Let me pull up your profile. Looking good as always.",
        "action": "PROFILE",
    },
    # --- Messaging ------------------------------------------------------------
    "MESSAGES": {
        "phrases": [
            # French
            "messages", "mes messages", "boÃ®te de rÃ©ception", "inbox",
            "envoyer un message", "nouveau message", "chat",
            "messagerie", "voir les messages", "lire messages",
            # English
            "messages", "inbox", "my messages", "view messages",
            "send a message", "new message", "chat", "messaging",
            "show messages", "open messages",
        ],
        "response": "Let me open your messages. Someone might be trying to reach you.",
        "action": "MESSAGES",
    },
    # --- Complaints / Support -------------------------------------------------
    "RECLAMATION": {
        "phrases": [
            # French
            "rÃ©clamation", "reclamation", "plainte", "problÃ¨me",
            "signaler un problÃ¨me", "signaler un probleme",
            "j'ai un problÃ¨me", "faire une rÃ©clamation",
            "service client", "contacter support",
            # English
            "complaint", "report a problem", "issue", "i have a problem",
            "file a complaint", "reclamation", "report issue",
            "submit complaint", "customer support", "contact support",
        ],
        "response": "Oh no, that doesn't sound fun. Let me open the reclamation form so we can sort this out.",
        "action": "RECLAMATION",
    },
    # --- Forum / Community ----------------------------------------------------
    "FORUM": {
        "phrases": [
            # French
            "forum", "communautÃ©", "communaute",
            "discussions", "voir le forum", "avis", "commentaires",
            "publier", "poser une question",
            # English
            "forum", "community", "discussions", "see forum",
            "ask a question", "reviews", "comments", "browse forum",
            "open forum", "go to forum",
        ],
        "response": "Opening the GoVibe community forum. Let's see what people are saying!",
        "action": "FORUM",
    },
    # --- Help & General -------------------------------------------------------
    "HELP": {
        "phrases": [
            # French
            "aide", "aide-moi", "aidez-moi", "commandes", "liste des commandes",
            "que peux-tu faire", "que puis-je dire",
            "quelles commandes", "j'ai besoin d'aide", "comment utiliser",
            "mode d'emploi", "assistant",
            # English
            "help", "help me", "commands", "what commands",
            "what can you do", "how to use", "i need help",
            "list of commands", "assistance", "guide me",
            "what do you do", "show commands",
        ],
        "response": (
            "Here's the magic spell list. "
            "On login: Email, Password, Login, Sign up. "
            "In the app: Book, My Bookings, Search Flights, Hotels, Cars, Activities, "
            "Pay, Messages, Forum, Profile, Complaint, Logout. "
            "Or just talk to me  -  I'm a pretty good listener."
        ),
        "action": "AIDE",
    },

    "DESCRIBE": {
        "phrases": [
            # French
            "dÃ©crire", "decrire", "qu'est-ce que c'est",
            "que vois-je", "explique", "dis-moi ce que je vois", "quoi",
            "quel Ã©cran", "c'est quoi", "oÃ¹ suis-je",
            # English
            "describe", "what is this", "what am i looking at",
            "what's on screen", "explain", "what", "tell me what's here",
            "where am i", "describe screen", "narrate",
        ],
        "response": "Let me narrate your surroundings like a nature documentary.",
        "action": "DECRIRE",
    },

    "GREET": {
        "phrases": [
            "bonjour", "bonsoir", "salut", "coucou", "hey govibe",
            "bonne nuit", "hello", "hi", "hey", "good morning",
            "good evening", "good night", "greetings", "yo", "what's up",
            "hi there", "hey there", "howdy", "hiya", "sup", "heya",
            "hello there", "long time no see", "hey you",
        ],
        "response": "Hey! Great to have you here!",
        "action": "NONE",
    },

    "OPEN_CAMERA": {
        "phrases": [
            "camera", "open camera", "use camera", "face id", "face login",
            "login with face", "use face id", "scan my face", "faceid",
            "open the camera", "start camera", "camara", "face scan",
            "log in with camera", "log in with face",
            # Vosk / accent variants
            "face login", "face recognition", "face unlock", "unlock with face",
            "open cam", "activate camera", "turn on camera", "launch camera",
            "use my face", "login face", "face authenticate", "biometric login",
            "face detection", "identify my face", "verify my face",
            "facial recognition", "facial id", "facial login",
        ],
        "response": "Opening the camera for Face ID login!",
        "action": "OPEN_CAMERA",
    },

    "VIVIAN_CALL": {
        "phrases": [
            "vivian", "hey vivian", "hi vivian", "hello vivian",
            "vivian wake up", "wake up vivian", "wake vivian",
            "vivian are you there", "vivian come back", "vivian please",
            "let me talk to vivian", "i want vivian",
            "can i speak to vivian", "get vivian", "call vivian",
            "is vivian there", "pass me to vivian", "give me vivian",
        ],
        "response": "I'm here! Yes? You called?",
        "action": "NONE",
    },

    "ECHO_CALL": {
        "phrases": [
            # User explicitly asks Echo to take over
            "hey echo", "hi echo", "echo", "hello echo",
            "echo are you there", "echo wake up", "echo help me",
            "echo take over", "echo do it", "let echo handle it",
            "echo i need you", "echo speak", "echo answer me",
            "can echo help", "i want echo", "give me echo",
            "echo you there", "echo please", "switch to echo",
        ],
        "response": "Right here! What do you need?",
        "action": "NONE",
    },

    "WHERE_IS_VIVIAN": {
        "phrases": [
            "where is vivian", "where's vivian", "wheres vivian",
            "what happened to vivian", "vivian where are you",
            "why isn't vivian talking", "why is vivian quiet",
            "vivian not responding", "vivian not answering",
            "where did vivian go", "vivian disappeared",
            "can't hear vivian", "cannot hear vivian",
            "is vivian okay", "is vivian working",
            "vivian slow", "vivian taking too long",
            "what is vivian doing", "what's vivian doing",
        ],
        "response": "Oh Vivian? Doing the only thing she's good at  -  sleeping!",
        "action": "NONE",
    },

    "SMALLTALK_GOODBYE": {
        "phrases": [
            "bye", "goodbye", "see you", "see you later", "later", "ciao",
            "au revoir", "bonne journee", "bonne journÃ©e", "a bientot", "Ã  bientÃ´t",
            "take care", "i'm leaving", "i'm done", "that's all",
            "gotta go", "ttyl", "night", "good night", "i'll be back",
            "talk later", "catch you later", "see ya", "farewell",
        ],
        "response": "Take care! Come back soon  -  safe travels!",
        "action": "NONE",
    },

    "SMALLTALK_CASUAL": {
        "phrases": [
            "oh cool", "that's cool", "sounds good to me",
            "not bad at all", "fair enough", "interesting",
            "no way", "really though", "oh wow", "oh nice",
            "gotcha thanks", "makes sense to me", "i see what you mean",
            "haha okay", "lol okay", "ha", "haha",
            "you got it", "that works", "i get it now",
            "oh i see", "oh that makes sense",
            "noted", "understood thanks",
        ],
        "response": "Glad you think so!",
        "action": "NONE",
    },

    # --- Small Talk -----------------------------------------------------------
    "SMALLTALK_NAME": {
        "phrases": [
            "what's your name", "who are you", "what are you called",
            "tu t'appelles comment", "comment tu t'appelles",
            "ton nom", "your name", "what should i call you",
            "introduce yourself",
        ],
        "response": "I'm Go  -  short for GoVibe. Your personal travel companion, navigator, and occasional snark machine.",
        "action": "NONE",
    },

    "SMALLTALK_HOW": {
        "phrases": [
            "how are you", "how are you doing", "how's it going",
            "comment Ã§a va", "comment ca va", "Ã§a va", "ca va",
            "you okay", "tu vas bien", "you good", "how do you feel",
        ],
        "response": "I'm fine, thanks for asking! How can I help you today?",
        "action": "NONE",
    },

    "SMALLTALK_HUMAN": {
        "phrases": [
            "are you human", "are you a robot", "are you an ai",
            "tu es un robot", "tu es humain", "tu es une ia",
            "are you real", "are you a bot", "are you alive",
        ],
        "response": "I'm a voice-powered AI travel assistant hiding inside a gorgeous app. Fully artificial, totally charming.",
        "action": "NONE",
    },

    "SMALLTALK_JOKE": {
        "phrases": [
            "tell me a joke", "say something funny", "joke", "make me laugh",
            "dis-moi une blague", "une blague", "fais-moi rire",
        ],
        "response": (
            "Why did the traveler break up with the airline? "
            "Too many missed connections. "
            "Don't worry, GoVibe never stands you up."
        ),
        "action": "NONE",
    },

    "SMALLTALK_THANKS": {
        "phrases": [
            "thank you", "thanks", "thank you so much", "merci", "merci beaucoup",
            "merci bien", "thx", "ty", "appreciate it", "great job",
            "bien jouÃ©", "bien fait",
        ],
        "response": "My pleasure! Anytime you need me, just say my name.",
        "action": "NONE",
    },

    
    "SMALLTALK_CURRENCY": {
        "phrases": [
            "currency", "exchange rate", "how much is the euro",
            "dollar to euro", "convert currency", "monnaie",
            "taux de change", "euro", "dollar", "how much is",
        ],
        "response": "Currency conversion isn't in my toolkit yet  -  but your bank app or Google will sort you out in seconds!",
        "action": "NONE",
    },

    "SMALLTALK_TRAVEL_TIP": {
        "phrases": [
            "travel tip", "travel advice", "tip for travel", "advice",
            "conseil de voyage", "conseil voyage", "astuce voyage",
            "recommend something", "conseille moi",
        ],
        "response": (
            "Pro tip: book early morning flights  -  cheaper, less crowded, and you arrive before the city wakes up. "
            "You're welcome."
        ),
        "action": "NONE",
    },

    "SMALLTALK_FAVORITE": {
        "phrases": [
            "what's your favorite destination", "favorite place", "best city",
            "where do you like", "quelle est ta destination prÃ©fÃ©rÃ©e",
            "ta destination favorite", "best travel spot",
        ],
        "response": "I'm partial to anywhere with good WiFi and a view. But between us  -  Lisbon never disappoints.",
        "action": "NONE",
    },
    # --- DB-powered descriptions ----------------------------------------------
    "DESCRIBE_ACTIVITY": {
        "phrases": [
            # English
            "tell me about the activity", "describe activity", "describe the activity",
            "what activities are available", "tell me about activities", "activity details",
            "activity info", "explain the activity", "what activity options",
            "tell me activities", "which activities do you have", "details on activities",
            "what's available to do", "what can i do here", "what do you offer",
            "list activities", "show me activities", "what activities are there",
            # French
            "dÃ©cris l'activitÃ©", "parle moi des activitÃ©s", "parle moi de l activite",
            "quelles activitÃ©s y a-t-il", "dis moi l activite", "dÃ©tails activitÃ©",
            "activitÃ©s disponibles", "quelles sont les activitÃ©s",
        ],
        "response": "Sure! Let me tell you about our available activities.",
        "action": "DESCRIBE_ACTIVITY",
    },

    "DESCRIBE_CAR": {
        "phrases": [
            # English
            "tell me about the car", "describe the car", "describe a car", "car details",
            "car info", "what cars do you have", "tell me about a car", "car description",
            "tell me about cars", "which cars are available", "car options",
            "describe the vehicles", "what vehicles do you have", "list the cars",
            # French
            "dÃ©cris la voiture", "parle moi des voitures", "dÃ©tails de la voiture",
            "quelles voitures y a-t-il", "voiture disponible", "quelle voiture choisir",
            "dÃ©cris les vÃ©hicules", "quels vÃ©hicules",
        ],
        "response": "Sure! Let me pull up that car's details for you.",
        "action": "DESCRIBE_CAR",
    },
    # --- Sessions --------------------------------------------------------------------
    "SHOW_SESSIONS": {
        "phrases": [
            # French
            "sessions", "voir les sessions", "mes sessions", "liste de sessions",
            "sessions disponibles", "sessions de formation", "planning",
            "ateliers", "cours disponibles",
            # English
            "show sessions", "my sessions", "view sessions", "session list",
            "available sessions", "training sessions", "workshops", "schedules",
        ],
        "response": "Let me show you the available sessions!",
        "action": "SHOW_SESSIONS",
    },
}

# -----------------------------------------------------------------------------
# DB-powered description helpers
# Read from _db_context (populated at startup by Java via db_context message).
# Used for DESCRIBE_ACTIVITY and DESCRIBE_CAR intents.
# -----------------------------------------------------------------------------

def _build_db_context_snippet() -> str:
    """
    Returns a compact text snippet of the DB inventory suitable for embedding
    in LLM system prompts so DeepSeek / Ollama can answer data-driven questions.
    """
    parts = []
    acts = _db_context.get("activities", [])
    if acts:
        a_list = "; ".join(
            f"{a.get('name','?')} ({a.get('type','')}, {a.get('localisation','')}, {a.get('prix','?')} TND)"
            for a in acts[:10]
        )
        parts.append(f"ACTIVITIES (first {min(len(acts),10)} of {len(acts)}): {a_list}")
    cars = _db_context.get("cars", [])
    if cars:
        c_list = "; ".join(
            f"{c.get('marque','?')} {c.get('modele','?')} {c.get('annee','')}  -  {c.get('prixJour','?')} TND/day ({c.get('statut','?')})"
            for c in cars[:10]
        )
        parts.append(f"CARS (first {min(len(cars),10)} of {len(cars)}): {c_list}")
    hotels = _db_context.get("hotels", [])
    if hotels:
        h_list = "; ".join(
            f"{h.get('nom','?')}  -  {h.get('ville','?')}, {h.get('nombreEtoiles','?')} stars, from {h.get('budget','?')} TND"
            for h in hotels[:10]
        )
        parts.append(f"HOTELS (first {min(len(hotels),10)} of {len(hotels)}): {h_list}")
    return "\n".join(parts) if parts else ""


def _describe_activities_from_db(city_filter: Optional[str] = None) -> str:
    """Build a natural spoken description of available activities from DB snapshot."""
    acts = _db_context.get("activities", [])
    if city_filter:
        city_n = _normalize(city_filter)
        filtered = [a for a in acts if city_n in _normalize(a.get("localisation", "") or "")]
        if filtered:
            acts = filtered
    if not acts:
        return ("I don't have any activities on my list right now. "
                "Please check the activities section for the latest offering!")
    lines = []
    for a in acts[:6]:
        name  = a.get("name", "Unknown")
        typ   = a.get("type", "")
        loc   = a.get("localisation", "")
        prix  = a.get("prix", "")
        desc  = (a.get("description") or "")[:90]
        s = name
        if typ:  s += f", a {typ} experience"
        if loc:  s += f" in {loc}"
        if prix: s += f", costing {prix} TND"
        if desc: s += f". {desc}"
        lines.append(s)
    total  = len(_db_context.get("activities", []))
    prefix = f"Here are the activities available{' in ' + city_filter if city_filter else ''}: "
    suffix = f"  We have {total} activities in total." if total > 6 else ""
    return prefix + ".  ".join(lines) + "." + suffix


def _describe_cars_from_db(car_hint: Optional[str] = None) -> str:
    """Build a natural spoken description of available cars from DB snapshot."""
    cars = _db_context.get("cars", [])
    if not cars:
        return ("I don't have any cars in my fleet right now. "
                "Please check the car rental section for the latest availability!")
    if car_hint:
        hint_n = _normalize(car_hint)
        matches = [c for c in cars
                   if hint_n in _normalize((c.get("marque") or "") + " " + (c.get("modele") or ""))]
        if matches:
            cars = matches
    lines = []
    for c in cars[:6]:
        marque = c.get("marque", "")
        modele = c.get("modele", "")
        annee  = c.get("annee", "")
        prix   = c.get("prixJour", "")
        statut = c.get("statut", "").upper()
        agence = c.get("adresseAgence", "")
        desc   = (c.get("description") or "")[:90]
        s = f"{marque} {modele}"
        if annee:  s += f" ({annee})"
        if prix:   s += f", {prix} TND per day"
        if statut and statut not in ("DISPONIBLE", "AVAILABLE"): s += f"  -  {statut.lower()}"
        if agence: s += f", pick up at {agence}"
        if desc:   s += f". {desc}"
        lines.append(s)
    total  = len(_db_context.get("cars", []))
    suffix = f"  Our full fleet has {total} cars." if total > 6 else ""
    return "Here are our available rental cars: " + ".  ".join(lines) + "." + suffix


# -----------------------------------------------------------------------------
# Ollama LLM tier  -  natural language understanding + parameter extraction
# -----------------------------------------------------------------------------
OLLAMA_URL           = "http://localhost:11434/api/generate"
OLLAMA_CHAT_URL      = "http://localhost:11434/api/chat"
OLLAMA_MODEL         = "qwen2.5"           # qwen2.5 has better French + multilingual support
OLLAMA_TIMEOUT_S     = 6                   # generous but bounded

# -- DeepSeek API --------------------------------------------------------------------
GEMINI_API_KEY     = "AIzaSyAGPaO4c3ekZkrnnl84NffvHlHlU4ciw54"
GEMINI_API_URL     = "https://generativelanguage.googleapis.com/v1beta/openai/chat/completions"
GEMINI_MODEL       = "gemini-2.0-flash"
GEMINI_TIMEOUT_S   = 6
_gemini_disabled     = False  # set True on permanent auth error (401/402/403)
_gemini_retry_after  = 0.0    # epoch time before which Gemini is paused (429 backoff)
# Legacy aliases so any remaining code that references the old DeepSeek names still compiles
DEEPSEEK_API_KEY   = GEMINI_API_KEY
DEEPSEEK_API_URL   = GEMINI_API_URL
DEEPSEEK_MODEL     = GEMINI_MODEL
DEEPSEEK_TIMEOUT_S = GEMINI_TIMEOUT_S

# Maps intent names back to action codes (same as INTENTS["xxx"]["action"]).
_INTENT_TO_ACTION = {k: v["action"] for k, v in
                     {"BOOK":               {"action": "BOOK"},
                      "VIEW_BOOKINGS":      {"action": "MES RESERVATIONS"},
                      "CHECKOUT":           {"action": "MES RESERVATIONS"},
                      "SEARCH":             {"action": "RECHERCHER"},
                      "CANCEL":             {"action": "ANNULER"},
                      "PAY":                {"action": "PAYER"},
                      "GET_WEATHER":        {"action": "GET_WEATHER"},
                      "HELP":               {"action": "AIDE"},
                      "DESCRIBE":           {"action": "DECRIRE"},
                      "LOGOUT":             {"action": "DECONNEXION"},
                      "LOGIN":              {"action": "LOGIN"},
                      "SIGNUP":             {"action": "SIGNUP"},
                      "GREET":              {"action": "NONE"},
                      "VIVIAN_CALL":        {"action": "NONE"},
                      "SMALLTALK_GOODBYE":  {"action": "NONE"},
                      "SMALLTALK_CASUAL":   {"action": "NONE"},
                      "FOCUS_EMAIL":        {"action": "FOCUS_EMAIL"},
                      "FOCUS_PASSWORD":     {"action": "FOCUS_PASSWORD"},
                      "SHOW_CARS":          {"action": "SHOW_CARS"},
                      "SHOW_ACTIVITIES":    {"action": "SHOW_ACTIVITIES"},
                      "SHOW_HOTELS":        {"action": "SHOW_HOTELS"},
                      "SHOW_LOCATIONS":     {"action": "SHOW_LOCATIONS"},
                      "NAVIGATE_HOME":      {"action": "HOME"},
                      "PROFILE":            {"action": "PROFILE"},
                      "MESSAGES":           {"action": "MESSAGES"},
                      "RECLAMATION":        {"action": "RECLAMATION"},
                      "FORUM":              {"action": "FORUM"},
                      "SMALLTALK_NAME":     {"action": "NONE"},
                      "SMALLTALK_HOW":      {"action": "NONE"},
                      "SMALLTALK_HUMAN":    {"action": "NONE"},
                      "SMALLTALK_JOKE":     {"action": "NONE"},
                      "SMALLTALK_THANKS":   {"action": "NONE"},
                      "SMALLTALK_WEATHER":  {"action": "NONE"},
                      "SMALLTALK_CURRENCY": {"action": "NONE"},
                      "SMALLTALK_TRAVEL_TIP": {"action": "NONE"},
                      "SMALLTALK_FAVORITE":    {"action": "NONE"},
                      "OPEN_CAMERA":           {"action": "OPEN_CAMERA"},
                      "DESCRIBE_ACTIVITY":     {"action": "DESCRIBE_ACTIVITY"},
                      "DESCRIBE_CAR":          {"action": "DESCRIBE_CAR"},
                      "SHOW_SESSIONS":         {"action": "SHOW_SESSIONS"},
                      "SHOW_FLIGHT_DETAILS":   {"action": "SHOW_FLIGHT_DETAILS"},
                      "WEATHER":               {"action": "SHOW_WEATHER"},
                      }.items()}

_OLLAMA_SYSTEM = (
    "You are Go, a friendly and witty travel assistant inside the GoVibe app. "
    "You help users book flights, find cars, discover activities, manage hotels, and handle payments. "
    "Personality: warm, occasionally humorous, always efficient. Keep responses concise. "
    "The user speaks French or English. "
    "IMPORTANT: the speech-to-text engine may be an English acoustic model, so "
    "French words may arrive phonetically transcribed without accents "
    "(e.g. 'reserver' instead of 'rÃ©server'). Treat such words as their French equivalents. "
    "When the user asks to describe or explain activities or cars, use the GoVibe inventory "
    "provided in the context messages to give specific, accurate details. "
    "Classify the user's intent into exactly one of: "
    "BOOK, VIEW_BOOKINGS, CHECKOUT, SEARCH, CANCEL, PAY, HELP, DESCRIBE, LOGOUT, "
    "LOGIN, SIGNUP, GREET, FOCUS_EMAIL, FOCUS_PASSWORD, SHOW_CARS, SHOW_ACTIVITIES, SHOW_HOTELS, "
    "SHOW_LOCATIONS, NAVIGATE_HOME, PROFILE, MESSAGES, RECLAMATION, FORUM, "
    "SHOW_SESSIONS, DESCRIBE_ACTIVITY, DESCRIBE_CAR, SHOW_FLIGHT_DETAILS, WEATHER, "
    "SMALLTALK_NAME, SMALLTALK_HOW, SMALLTALK_HUMAN, SMALLTALK_JOKE, SMALLTALK_THANKS, "
    "SMALLTALK_WEATHER, SMALLTALK_CURRENCY, SMALLTALK_TRAVEL_TIP, SMALLTALK_FAVORITE, OPEN_CAMERA, UNKNOWN. "
    "Also extract: destination (city name, or null), date (YYYY-MM-DD, or null). "
    "Use the conversation history to resolve references like 'that one', 'book it', 'the cheapest'. "
    "Write the 'response' field as short, friendly, occasionally witty English  -  "
    "it will be read aloud by a neural TTS voice. Keep it concise. "
    "Reply ONLY with minified JSON  -  no markdown, no explanation:\n"
    '{"intent":"BOOK","confidence":0.95,"destination":"Paris","date":null,'
    '"response":"Paris it is! Let me see what\'s flying..."}'
)


def _build_context_messages() -> list:
    """
    Build the messages list for multi-turn context.
    Returns a list of {"role":..., "content":...} dicts for the last few turns,
    plus a context note about known entities and DB inventory.
    """
    msgs = []
    # Include entity memory as a system note so the LLM can resolve references
    em = _entity_memory
    context_parts = []
    if em.get("last_city"):
        context_parts.append(f"Last mentioned city: {em['last_city']}")
    if em.get("last_date"):
        context_parts.append(f"Last mentioned date: {em['last_date']}")
    if em.get("last_car"):
        context_parts.append(f"Last mentioned car: {em['last_car']}")
    if em.get("last_flight"):
        context_parts.append(f"Last mentioned flight: {em['last_flight']}")
    if em.get("last_action"):
        context_parts.append(f"Last action: {em['last_action']}")
    if context_parts:
        msgs.append({
            "role": "system",
            "content": "Context memory  -  " + "; ".join(context_parts) + ".",
        })
    # Include DB inventory snippet so the LLM can answer questions about
    # specific activities, cars, and hotels available in the GoVibe platform.
    db_snippet = _build_db_context_snippet()
    if db_snippet:
        msgs.append({
            "role": "system",
            "content": "GoVibe live inventory (from database):\n" + db_snippet,
        })
    # Add recent conversation turns
    msgs.extend(list(_conversation_history))
    return msgs

_ollama_available = None   # None = not yet checked


def _check_ollama() -> bool:
    """Returns True if Ollama HTTP API is reachable."""
    global _ollama_available
    if _ollama_available is not None:
        return _ollama_available
    try:
        req = urllib.request.Request(
            "http://localhost:11434/",
            headers={"Accept": "text/plain"})
        with urllib.request.urlopen(req, timeout=2):
            pass
        _ollama_available = True
        _log(f"Ollama reachable at localhost:11434  -  model '{OLLAMA_MODEL}'.")
    except Exception:
        _ollama_available = False
        _log("Ollama not reachable  -  tier-2 LLM disabled (start Ollama to enable).")
    return _ollama_available


def _classify_ollama(text: str):
    """
    Calls the local Ollama LLM to classify *text* and extract parameters.
    Returns (intent, confidence, destination, date, response_text) or None on failure.
    Includes the last N conversation turns for multi-turn context.
    """
    if not _check_ollama():
        return None
    try:
        context_msgs = _build_context_messages()
        messages = [{"role": "system", "content": _OLLAMA_SYSTEM}]
        messages.extend(context_msgs)
        messages.append({"role": "user", "content": text})

        # Use /api/chat (supported by Ollama â‰¥ 0.1.14) for cleaner system+user split.
        payload = json.dumps({
            "model": OLLAMA_MODEL,
            "stream": False,
            "format": "json",
            "messages": messages,
        }, ensure_ascii=False).encode("utf-8")
        req = urllib.request.Request(
            OLLAMA_CHAT_URL,
            data=payload,
            headers={"Content-Type": "application/json; charset=utf-8"})
        with urllib.request.urlopen(req, timeout=OLLAMA_TIMEOUT_S) as resp:
            raw = json.loads(resp.read())

        # /api/chat response: raw["message"]["content"]
        content_str = raw.get("message", {}).get("content", "")
        if not content_str:
            # Fallback: try /api/generate field
            content_str = raw.get("response", "")
        parsed = json.loads(content_str)

        intent  = parsed.get("intent",      "UNKNOWN").upper()
        conf    = float(parsed.get("confidence", 0.0))
        dest    = parsed.get("destination") or None
        date    = parsed.get("date")        or None
        resp_t  = parsed.get("response",    "") or ""

        # Validate  -  reject hallucinated intent names
        valid = set(_INTENT_TO_ACTION.keys()) | {"UNKNOWN"}
        if intent not in valid:
            intent = "UNKNOWN"
            conf   = 0.0

        _log(f"  [Ollama] intent={intent}  conf={conf:.2f}  dest={dest}  date={date}")
        return intent, conf, dest, date, resp_t

    except Exception as exc:
        _log(f"  [Ollama] Error: {exc}")
        return None


def _classify_gemini(text: str):
    """
    Calls the Gemini API (OpenAI-compatible endpoint) using function calling to classify user intent
    and extract structured travel parameters.
    Includes conversation history for multi-turn context resolution.

    Returns a 6-tuple:
        (intent, confidence, destination, date, response_text, passengers)
    or None on failure.
    """
    try:
        # Build the allowed intent enum dynamically from the intent table.
        valid_intents = sorted(set(_INTENT_TO_ACTION.keys()) | {"UNKNOWN"})

        # Single function definition  -  DeepSeek will always call this.
        tools = [
            {
                "type": "function",
                "function": {
                    "name": "govibe_intent",
                    "description": (
                        "Classify the user's voice command into a GoVibe intent "
                        "and extract any travel parameters mentioned. "
                        "Use conversation history to resolve references like 'book that one' or 'the cheapest'."
                    ),
                    "parameters": {
                        "type": "object",
                        "properties": {
                            "intent": {
                                "type": "string",
                                "enum": valid_intents,
                                "description": "The GoVibe intent that best matches the user command.",
                            },
                            "confidence": {
                                "type": "number",
                                "description": "Confidence score 0.0  -  1.0.",
                            },
                            "destination": {
                                "type": "string",
                                "description": "Destination city name, or null if not mentioned.",
                            },
                            "date": {
                                "type": "string",
                                "description": "Travel date as YYYY-MM-DD, or null if not mentioned.",
                            },
                            "passengers": {
                                "type": "integer",
                                "description": "Number of passengers (seats). Default 1 if not mentioned.",
                            },
                            "response": {
                                "type": "string",
                                "description": (
                                    "Short, friendly, occasionally witty English reply to speak back to the user. "
                                    "Keep it conversational and concise  -  it will be read by TTS."
                                ),
                            },
                        },
                        "required": ["intent", "confidence", "response"],
                    },
                },
            }
        ]

        # Build DB context snippet to include in the DeepSeek system prompt
        db_ctx = _build_db_context_snippet()
        db_note = (f"\n\nGoVibe live inventory (use for describe/details questions):\n{db_ctx}"
                   if db_ctx else "")
        system_msg = (
            "You are Go, the friendly GoVibe travel assistant voice agent. "
            "You help users book flights, find cars, discover activities, manage hotels, and handle payments. "
            "Personality: warm, witty, efficient. Responses are spoken aloud  -  keep them concise. "
            "Use the conversation history to resolve pronouns and references ('book it', 'that one', 'the cheapest'). "
            "When classifying DESCRIBE_ACTIVITY or DESCRIBE_CAR, write the 'response' field using "
            "the real inventory data provided below so the answer is specific and accurate. "
            "Classify the user's spoken command into one of the allowed intents. "
            "Extract destination city, travel date (ISO format), and passenger count if mentioned. "
            "Always call the govibe_intent function  -  never reply with plain text."
            + db_note
        )

        # Build multi-turn message list with context
        context_msgs = _build_context_messages()
        messages = [{"role": "system", "content": system_msg}]
        messages.extend(context_msgs)
        messages.append({"role": "user", "content": text})

        payload = json.dumps({
            "model":       GEMINI_MODEL,
            "stream":      False,
            "temperature": 0.1,
            "max_tokens":  300,
            "messages":    messages,
            "tools":       tools,
            "tool_choice": {"type": "function", "function": {"name": "govibe_intent"}},
        }, ensure_ascii=False).encode("utf-8")

        req = urllib.request.Request(
            GEMINI_API_URL,
            data=payload,
            headers={
                "Content-Type":  "application/json; charset=utf-8",
                "Authorization": f"Bearer {GEMINI_API_KEY}",
                "Accept":        "application/json",
            })
        with urllib.request.urlopen(req, timeout=GEMINI_TIMEOUT_S) as resp:
            raw = json.loads(resp.read())

        message = raw.get("choices", [{}])[0].get("message", {})

        # -- Parse function-call arguments (preferred) ------------------------
        tool_calls = message.get("tool_calls") or []
        if tool_calls:
            args_str = tool_calls[0].get("function", {}).get("arguments", "{}")
            parsed   = json.loads(args_str)
        else:
            # Fallback: try to parse content as JSON (older API behaviour).
            content_str = message.get("content", "")
            if not content_str:
                return None
            parsed = json.loads(content_str)

        intent     = (parsed.get("intent") or "UNKNOWN").upper()
        conf       = float(parsed.get("confidence", 0.0))
        dest       = parsed.get("destination") or None
        date       = parsed.get("date")        or None
        resp_t     = parsed.get("response",    "") or ""
        passengers = int(parsed.get("passengers") or 1)
        if passengers < 1:
            passengers = 1

        valid = set(_INTENT_TO_ACTION.keys()) | {"UNKNOWN"}
        if intent not in valid:
            intent = "UNKNOWN"
            conf   = 0.0

        _log(f"  [Gemini] intent={intent}  conf={conf:.2f}  dest={dest}"
             f"  date={date}  pax={passengers}")
        return intent, conf, dest, date, resp_t, passengers

    except urllib.error.HTTPError as exc:
        if exc.code in (401, 402, 403):
            global _gemini_disabled
            _gemini_disabled = True
            _log(f"  [Gemini] HTTP {exc.code}  -  permanently disabling Gemini (auth error).")
        elif exc.code == 429:
            global _gemini_retry_after
            _gemini_retry_after = time.time() + 90
            _log("  [Gemini] HTTP 429  -  rate limited. Pausing 90 s, auto-retrying.")
        else:
            _log(f"  [Gemini] HTTP error: {exc}")
        return None
    except Exception as exc:
        _log(f"  [Gemini] Error: {exc}")
        return None


_engine = "none"

# sentence-transformers state
_st_model       = None   # SentenceTransformer
_st_intent_vecs = {}     # intent -> np.ndarray of normalised phrase embeddings

# sklearn state
_tfidf_vec  = None
_tfidf_mat  = None
_tfidf_lbl  = None


def _all_phrase_pairs():
    """Yields (phrase, intent_key) for every training sample."""
    for intent_key, data in INTENTS.items():
        for phrase in data["phrases"]:
            yield phrase, intent_key


def _try_sentence_transformers():
    """Load multilingual model (best for French), fall back to English MiniLM."""
    global _engine, _st_model, _st_intent_vecs
    try:
        from sentence_transformers import SentenceTransformer
        import numpy as np

        # Prefer the multilingual model  -  natively supports French, Spanish, German.
        # Falls back to English-only all-MiniLM-L6-v2 if not available/downloaded.
        for name in (
            "paraphrase-multilingual-MiniLM-L12-v2",  # ~470 MB, 50+ languages
            "all-MiniLM-L6-v2",                        # ~90 MB, English-first fallback
        ):
            try:
                _log(f"Trying sentence-transformers model '{name}'...")
                _st_model = SentenceTransformer(name)
                _log(f"Loaded '{name}'.")
                break
            except Exception as e:
                _log(f"Could not load '{name}': {e}")
                _st_model = None

        if _st_model is None:
            return False

        for intent_key, data in INTENTS.items():
            vecs = _st_model.encode(
                data["phrases"], convert_to_numpy=True,
                show_progress_bar=False, normalize_embeddings=True)
            _st_intent_vecs[intent_key] = vecs  # shape (n_phrases, dim)

        total = sum(v.shape[0] for v in _st_intent_vecs.values())
        _engine = "sentence-transformers"
        _log(f"sentence-transformers ready  -  {total} phrases across {len(_st_intent_vecs)} intents.")
        return True
    except Exception as exc:
        _log(f"sentence-transformers unavailable: {exc}")
        return False


def _load_sklearn():
    global _engine, _tfidf_vec, _tfidf_mat, _tfidf_lbl
    try:
        from sklearn.feature_extraction.text import TfidfVectorizer
    except ImportError:
        raise ImportError(
            "scikit-learn is required for ML intent classification. "
            "Run: python -m pip install scikit-learn"
        )
    phrases, labels = zip(*_all_phrase_pairs())
    _tfidf_vec = TfidfVectorizer(
        analyzer="char_wb", ngram_range=(2, 4),
        sublinear_tf=True, min_df=1)
    # --- KEY FIX: normalise accents so English-STT output (e.g. "reserver")
    # matches the French training phrase ("rÃ©server")  -  otherwise char n-grams
    # for Ã©/e differ and the cosine distance is too high to classify correctly.
    _tfidf_mat = _tfidf_vec.fit_transform([_normalize(p) for p in phrases])
    _tfidf_lbl = list(labels)
    _engine = "sklearn-tfidf"
    _log(f"TF-IDF engine ready  -  {len(phrases)} phrases across {len(INTENTS)} intents. (accent-normalised)")


# -- RAG Knowledge Base --------------------------------------------------------
# govibe_knowledge.md is chunked into paragraphs and indexed with a word-level
# TF-IDF (separate from the intent TF-IDF so thresholds don't interfere).
# When all intent tiers return UNKNOWN, _rag_search() is tried as a last resort
# so free-form questions get real answers instead of the generic fallback.
_rag_chunks: list[str]  = []
_rag_vectorizer         = None
_rag_matrix             = None


def _load_rag() -> None:
    """Load and TF-IDF-index govibe_knowledge.md for free-form Q&A."""
    global _rag_chunks, _rag_vectorizer, _rag_matrix
    try:
        import re
        from sklearn.feature_extraction.text import TfidfVectorizer
        kb_path = os.path.join(os.path.dirname(os.path.abspath(__file__)), "govibe_knowledge.md")
        if not os.path.exists(kb_path):
            _log("[RAG] govibe_knowledge.md not found  -  knowledge base disabled.")
            return
        with open(kb_path, "r", encoding="utf-8") as _f:
            content = _f.read()
        # Split on blank lines; carry the most recent H2 header as context prefix.
        chunks: list[str] = []
        current_header = "GoVibe"
        for block in re.split(r"\n{2,}", content):
            block = block.strip()
            if not block:
                continue
            if block.startswith("#"):
                current_header = block.lstrip("#").strip()
                continue
            chunks.append(f"{current_header}: {block}")
        _rag_chunks     = chunks
        _rag_vectorizer = TfidfVectorizer(
            analyzer="word", ngram_range=(1, 2),
            sublinear_tf=True, min_df=1)
        _rag_matrix = _rag_vectorizer.fit_transform(
            [_normalize(c) for c in _rag_chunks])
        _log(f"[RAG] Knowledge base ready  -  {len(_rag_chunks)} chunks indexed.")
    except Exception as _e:
        _log(f"[RAG] Failed to load knowledge base: {_e}")


def _rag_search(query: str, threshold: float = 0.25) -> Optional[str]:
    """
    Return the best-matching knowledge-base paragraph for *query*, or None
    if the best cosine similarity is below *threshold*.

    Vivian (or Echo) speaks the returned text as a natural answer.
    Threshold is intentionally low (0.08)  -  any relevant match beats the
    generic 'I didn't catch that' message.
    """
    if not _rag_chunks or _rag_vectorizer is None or _rag_matrix is None:
        return None
    try:
        from sklearn.metrics.pairwise import cosine_similarity
        qvec  = _rag_vectorizer.transform([_normalize(query)])
        sims  = cosine_similarity(qvec, _rag_matrix).flatten()
        best  = int(sims.argmax())
        score = float(sims[best])
        _log(f"[RAG] Top chunk idx={best} score={score:.3f}")
        if score < threshold:
            return None
        # Strip the "Header: " prefix and return the paragraph text.
        chunk  = _rag_chunks[best]
        colon  = chunk.find(": ")
        answer = chunk[colon + 2:] if colon >= 0 else chunk
        return answer[:500]   # cap length so TTS stays concise
    except Exception as _e:
        _log(f"[RAG] Search error: {_e}")
        return None


# -----------------------------------------------------------------------------
# Classification
# -----------------------------------------------------------------------------
def _classify_st(text):
    """
    Intent-level max-pooling: for each intent take the max cosine sim across
    all its training phrases, then return the intent with the highest score.
    """
    import numpy as np
    query = _st_model.encode(
        [text], convert_to_numpy=True, normalize_embeddings=True)  # (1, dim)

    best_intent = "UNKNOWN"
    best_score  = -1.0
    for intent_key, vecs in _st_intent_vecs.items():
        # L2-normalised vectors -> dot product == cosine similarity
        sims    = (vecs @ query.T).flatten()
        max_sim = float(np.max(sims))
        if max_sim > best_score:
            best_score  = max_sim
            best_intent = intent_key
    return best_intent, best_score


def _classify_tfidf(text):
    from sklearn.metrics.pairwise import cosine_similarity
    import numpy as np
    # Normalise query the same way training phrases were normalised so that
    # accent differences (Ã©->e) never tank the cosine score.
    vec  = _tfidf_vec.transform([_normalize(text)])
    sims = cosine_similarity(vec, _tfidf_mat).flatten()
    # Aggregate: max similarity per intent
    intent_scores = {}
    for idx, label in enumerate(_tfidf_lbl):
        score = float(sims[idx])
        if label not in intent_scores or score > intent_scores[label]:
            intent_scores[label] = score
    best_intent = max(intent_scores, key=intent_scores.get)
    return best_intent, intent_scores[best_intent]


def classify(text):
    """Returns (intent_key, confidence_float)."""
    text = text.strip()
    if not text:
        return "UNKNOWN", 0.0
    # Guard: very short inputs (single words, fillers) produce spurious
    # sentence-transformer confidence. Reject before expensive embedding.
    if len(text.split()) < 2 and len(text) < 8:
        return "UNKNOWN", 0.0
    try:
        if _engine == "sentence-transformers":
            intent, conf = _classify_st(text)
            threshold = 0.42   # balanced: catches natural speech; TTS-echo noise filtered by Java gate
        else:
            intent, conf = _classify_tfidf(text)
            threshold = 0.30
        if conf < threshold:
            return "UNKNOWN", conf
        return intent, conf
    except Exception as exc:
        _log(f"classify error: {exc}")
        return "UNKNOWN", 0.0


# -----------------------------------------------------------------------------
# Response builder
# -----------------------------------------------------------------------------
def build_response(text, intent_key, confidence):
    if intent_key not in INTENTS:
        return {
            "intent": "UNKNOWN",
            "response": "Hmm, I didn't quite catch that. Can you say it again?",
            "action": "UNKNOWN",
            "confidence": round(confidence, 3),
            "destination": None,
            "date": None,
            "engine": _engine,
        }
    data = INTENTS[intent_key]
    return {
        "intent": intent_key,
        "response": data["response"],
        "action": data["action"],
        "confidence": round(confidence, 3),
        "destination": None,
        "date": None,
        "engine": _engine,
    }


# -----------------------------------------------------------------------------
# Helpers
# -----------------------------------------------------------------------------
def _log(msg):
    sys.stderr.write(f"[VoiceAgent] {msg}\n")
    sys.stderr.flush()


def _send(obj):
    """Thread-safe JSON line writer  -  automatically echoes _current_seq if set."""
    global _current_seq
    if _current_seq is not None:
        obj = dict(obj)  # don't mutate the caller's dict
        obj["seq"] = _current_seq
    _send_raw(obj)


# -----------------------------------------------------------------------------
# Main
# -----------------------------------------------------------------------------
def main():
    """
    Main event loop  -  reads line-delimited JSON from stdin.

    Messages handled:
      * {"type":"user_context","logged_in":bool,"name":str}
            -> Update user context. If state is PROCESSING_LOGOUT and
              logged_in=false, run post-logout personality sequence.
      * {"type":"wake_word"} or {"wake_word":true, "text":"..."}
            -> Sleepy yawn response, optionally detect embedded logout command.
      * {"text":"<user speech>"}
            -> State-machine routing -> intent classification -> Qwen3-TTS response.

    Extra JSON sent to Java (beyond normal intent responses):
      * {"type":"tts_status","speaking":bool}   -  mic mute control
      * {"type":"resume_wake_word"}             -  after post-logout sequence ends
    """
    global _agent_state, _user_logged_in, _user_name, _pending_command, _intro_done, _current_seq, _vivian_ready

    # Unsupervised learning: import tracker, inject previously learned phrases.
    try:
        from behavior_tracker import BehaviorTracker as _BT
        _tracker = _BT()
        injected = _tracker.inject_learned_phrases(INTENTS)
        _log(f"[BehaviorTracker] Init OK. Injected {injected} learned phrase(s).")
    except Exception as _bt_err:
        _tracker = None
        _log(f"[BehaviorTracker] Disabled: {_bt_err}")
    _log("GoVibe Voice Agent starting...")

    # Fast startup: sklearn loads in <1 s so Java gets the ready signal almost
    # immediately instead of waiting ~15 s for sentence-transformers.
    # ST loads in background and auto-upgrades _engine once ready.
    try:
        _load_sklearn()
    except ImportError as _sk_err:
        _log(f"[sklearn] scikit-learn not installed: {_sk_err}")
        _log("[sklearn] Install with: python -m pip install scikit-learn")
        _log("[sklearn] Agent running in keyword-only mode until sklearn is available.")
    except Exception as _sk_err:
        _log(f"[sklearn] Failed to load TF-IDF engine: {_sk_err}")

    def _background_upgrade():
        """Load sentence-transformers in background, then safely start TTS preload."""
        _try_sentence_transformers()
        # Start TTS pre-load only AFTER sentence-transformers has fully initialised
        # transformers' _LazyModule  -  avoids ImportError: cannot import name
        # 'AutoConfig' from 'transformers' during the concurrent-import race.
        _tts_preload_thread.start()
        # Echo fills silence with quips while Vivian's Qwen3 model loads.
        threading.Thread(target=_echo_comedian_loop,
                         name="GoVibe-EchoComedian", daemon=True).start()
        # Load the knowledge base for free-form Q&A (uses sklearn, already loaded).
        _load_rag()

    threading.Thread(target=_background_upgrade, daemon=True,
                     name="GoVibe-BgInit").start()

    # Check Ollama availability asynchronously (don't block startup).
    _check_ollama()

    ready_engine = _engine + "+gemini" + ("+ollama" if _ollama_available else "") + "+qwen3tts"
    _send({"status": "ready", "engine": ready_engine})
    _log(
        f"Ready. Engine: {_engine}. Gemini: YES. "
        f"Ollama: {'YES' if _ollama_available else 'NO'}. "
        f"TTS: Qwen3-0.6B (lazy-load). Listening on stdin..."
    )

    for raw_line in sys.stdin:
        raw_line = raw_line.strip()
        if not raw_line:
            continue
        try:
            msg  = json.loads(raw_line) if raw_line.startswith("{") else {"text": raw_line}
            text = msg.get("text", "").strip()
            # Track seq so _send() can echo it back for Java response-matching.
            _current_seq = msg.get("seq", None)

            # ------------------------------------------------------------------
            # user_context  -  Java sends this at startup and after login/logout.
            # When Go is in PROCESSING_LOGOUT and logged_in becomes false,
            # run the confused/sleepy post-logout personality sequence.
            # ------------------------------------------------------------------
            # ------------------------------------------------------------------
            # db_context  -  Java sends database snapshot at startup.
            # Populates _db_context so Go can describe activities, cars, hotels.
            # ------------------------------------------------------------------
            if msg.get("type") == "db_context":
                global _db_context
                _db_context["activities"] = msg.get("activities") or []
                _db_context["cars"]       = msg.get("cars")       or []
                _db_context["hotels"]     = msg.get("hotels")     or []
                total = (len(_db_context["activities"])
                         + len(_db_context["cars"])
                         + len(_db_context["hotels"]))
                _log(f"[DB] Snapshot received  -  "
                     f"{len(_db_context['activities'])} activities, "
                     f"{len(_db_context['cars'])} cars, "
                     f"{len(_db_context['hotels'])} hotels (total {total} items).")
                continue  # no intent response for db_context

            # -- speak  -  Java requests Vivian to speak a line ------------------
            # Java sends {"type":"speak","text":"..."}  for things like
            # acknowledgements ("On it!") and environment alerts so ALL speech
            # goes through Vivian instead of the Java TTS (Jenny/SAPI).
            if msg.get("type") == "speak":
                _speak_txt = msg.get("text", "")
                if _speak_txt:
                    speak(_speak_txt, _INSTR_GENERAL)
                continue  # no intent response for speak requests

            # -- speak_fast -- Java requests Echo (edge-tts) for instant reply ------
            # Used by CommandRouter.speakAndRun() so action confirmations play
            # in ~200ms (Echo) instead of 30-60s (Vivian/Qwen3 on CPU).
            # This keeps the mic open almost immediately after a command.
            if msg.get("type") == "speak_fast":
                _speak_txt = msg.get("text", "")
                if _speak_txt:
                    speak_fast(_speak_txt)
                continue  # no intent response for speak_fast

            if msg.get("type") == "user_context":
                prev_logged_in  = _user_logged_in
                _user_logged_in = bool(msg.get("logged_in", False))
                _user_name      = msg.get("name") or None
                _log(f"[Context] logged_in={_user_logged_in}  name={_user_name!r}  state={_agent_state}")

                # -- Login screen auto-wake -------------------------------------
                # When Java opens the login screen it sends user_context(logged_in=false).
                # The agent is still SLEEPING at that point, which would block auth
                # commands like "log in" / "sign in" that contain no wake word.
                # Auto-transition to HELPING so those commands go through immediately.
                if not _user_logged_in and _agent_state == "SLEEPING":
                    _log("[Context] Login screen detected  -  SLEEPING -> HELPING (auth bypass active).")
                    _intro_done = True   # login screen never needs the intro drama
                    _transition("HELPING")

                # -- Login just completed (False -> True)  -  wellness greeting ---
                elif _user_logged_in and not prev_logged_in:
                    _log("[Context] Login completed  -  recognition + guidance greeting.")
                    # Clear any pending TTS (banter duet, help queue, quips) so the
                    # login greeting plays immediately rather than after a long queue drain.
                    _clear_tts_queue()
                    name_part = f" {_user_name}" if _user_name else ""
                    recog    = f"oh it was you{name_part}! Welcome back!"
                    guidance = "do you need help or should I guide you?"
                    # Echo speaks FIRST (~200ms, edge-tts) so the user hears something immediately.
                    # Vivian's Qwen3 queue may still be busy with wake-up banter audio.
                    _ECHO_LOGIN_ACKS = [
                        f"Hey{name_part}! You're in! Vivian -- say hello!",
                        f"Oh{name_part}! Welcome back! Vivian, they're here!",
                        f"Logged in{name_part}! Vivian, greet our user!",
                        f"They made it{name_part}! Vivian, you're up!",
                    ]
                    speak_fast(_rnd.choice(_ECHO_LOGIN_ACKS))
                    # Use speak_fast (Echo/edge-tts) for the full greeting so the mic
                    # reopens within ~600ms instead of waiting 10-30s for Qwen3 synthesis.
                    # Echo handles the full greeting instantly -- mic reopens in ~600ms.
                    # Vivian deliberately skipped here to avoid re-muting the mic
                    # right as the user tries to answer. She can respond on next command.
                    speak_fast(f"{recog} {guidance}")
                    _transition("AWAITING_WELLNESS")
                    _send({
                        "intent": "GREET", "response": guidance, "action": "NONE",
                        "confidence": 1.0, "destination": None, "date": None,
                        "passengers": 1, "engine": "login-wellness",
                    })

                # Triggered by a logout completing  -  play post-logout sequence.
                elif _agent_state == "PROCESSING_LOGOUT" and not _user_logged_in:
                    _log("[PostLogout] Running banter + confused/sleepy sequence.")
                    # Echo speaks first (instant edge-tts), then Vivian reacts confused.
                    speak_fast("Come back soon!")                      # Echo -- instant
                    speak("What was that for?", _INSTR_CONFUSED)      # Vivian -- confused
                    # Yawn SFX then fade back to sleep.
                    _queue_yawn_sfx()
                    _transition("SLEEPING")
                    # Signal Java to re-enable the wake-word detector.
                    _send({"type": "resume_wake_word"})

                # Logout triggered externally (SAPI grammar fast-path, or
                # CommandRouter.notifyUserLoggedOut() fired before Python
                # had a chance to enter PROCESSING_LOGOUT).  Python was
                # still in HELPING / AWAITING_WELLNESS when Java logged out.
                elif not _user_logged_in and prev_logged_in:
                    _log("[PostLogout-External] External logout -- farewell + sleep.")
                    speak_fast("Come back soon!")
                    _queue_yawn_sfx()
                    _transition("SLEEPING")
                    _send({"type": "resume_wake_word"})

                continue  # no intent response expected for user_context

            # gesture_event -- Java GestureRecognitionService sends this when
            # a gesture changes so the Python agent can respond contextually.
            if msg.get("type") == "gesture_event":
                _gesture = msg.get("gesture", "NONE")
                _log(f"[Gesture] Received: {_gesture}")
                _GESTURE_REPLIES = {
                    "THUMBS_UP": [
                        "Thumbs up! Hold it for a second and we'll confirm.",
                        "Nice! Keep that thumb up a little longer!",
                        "Perfect gesture! Just hold it steady.",
                    ],
                    "OPEN_PALM": [
                        "Open palm detected! Hold still to confirm.",
                        "Great gesture! Keep your hand open.",
                        "Open palm -- excellent! A moment longer and we're confirmed.",
                    ],
                    "FIST": [
                        "That's a fist -- try thumbs up or open palm to confirm.",
                        "Fist detected! Relax your hand and try thumbs up instead.",
                    ],
                    "POINTING": [
                        "Pointing -- interesting choice! I need thumbs up or open palm though.",
                        "I see the finger! Try an open palm or thumbs up to confirm.",
                    ],
                }
                _replies = _GESTURE_REPLIES.get(_gesture)
                if _replies:
                    speak_fast(_rnd.choice(_replies))
                continue  # no intent response expected for gesture_event


            # ------------------------------------------------------------------
            # wake_word  -  Java detects "Hi Go" / "Hey Go" and sends this.
            # Carries the full STT text so Python can detect embedded commands
            # (e.g. "hi go logout" arriving as a single Vosk utterance).
            #
            # Scenario 1: Play sleepy yawn, transition to JUST_WOKEN.
            # Special-case "hi go logout" -> Scenario 7 combined wake+logout.
            # ------------------------------------------------------------------
            if msg.get("wake_word") or msg.get("type") == "wake_word":
                wake_text_norm = _normalize(text)  # full STT text forwarded by Java
                _log(f"[WakeWord] Triggered. Embedded text: {text!r}")

                # -- Scenario 7 (combined): "hi go logout" in one utterance --
                _LOGOUT_WORDS = {"logout", "log out", "sign out", "deconnexion",
                                 "deconnecter", "quitter", "disconnect"}
                if _user_logged_in and any(w in wake_text_norm for w in _LOGOUT_WORDS):
                    _log("[WakeWord] Combined wake+logout detected.")
                    _transition("PROCESSING_LOGOUT")
                    # Step 1 of Scenario 7: surprised reaction before logout.
                    speak_fast("Wait, what?")  # surprise line ? edge-tts feels more instant
                    # Trigger the actual logout via Java by sending DECONNEXION.
                    _send({
                        "intent": "LOGOUT", "response": "Wait, what?",
                        "action": "DECONNEXION", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "wake-word-logout",
                    })
                    # Remainder of Scenario 7 (confused + yawn) plays when
                    # Java sends user_context(logged_in=false) back to us.
                    continue

                # Optional edge case: "hi go logout" while not logged in.
                if not _user_logged_in and any(w in wake_text_norm for w in _LOGOUT_WORDS):
                    _log("[WakeWord] Logout attempt while not logged in.")
                    _transition("JUST_WOKEN")
                    speak("yaaaawn ... what do you want?", _INSTR_SLEEPY_WAKE)
                    speak("You're not logged in, silly.", _INSTR_NOT_LOGGED_IN)
                    _send({
                        "intent": "GREET", "response": "You're not logged in, silly.",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "wake-word",
                    })
                    continue

                # -- Scenario 1: normal wake -----------------------------------
                # If intro not yet done: start the intro conversation.
                # If logged in: wellness greeting.
                # Otherwise: sleepy yawn.
                if not _intro_done:
                    _log("[WakeWord] First wakeup ever  -  starting intro conversation.")
                    _transition("INTRO_WAKING")
                    speak_fast("Oh! Someone's here! Vivian, hey  -  wake up! We've got a user!")
                    _queue_yawn_sfx()
                    speak("what the hell, let me sleep",
                          "extremely sleepy, groggy, annoyed at being woken")
                    greeting = "*yawn* what the hell, let me sleep"
                elif _user_logged_in:
                    _log("[WakeWord] Logged-in wake  -  recognition + guidance.")
                    name_part = f" {_user_name}" if _user_name else ""
                    recog    = f"oh it was you{name_part}!"
                    guidance = "do you need help or should I guide you?"
                    if _vivian_ready:
                        speak_fast(f"Hey, it's{name_part}! Vivian, go say hi!")
                    # ONE Qwen3 call ? eliminates silence gap between renders
                    speak(f"{recog} {guidance}", _INSTR_WARM_RECOG)
                    _transition("AWAITING_WELLNESS")
                    greeting = guidance
                else:
                    _transition("JUST_WOKEN")
                    if _vivian_ready:
                        speak_fast("Someone's asking for you, Vivian. Your turn!")
                    _queue_yawn_sfx()
                    speak("what do you want?", _INSTR_SLEEPY_WAKE)
                    greeting = "*yawn* what do you want?"
                _send({
                    "intent": "GREET", "response": greeting, "action": "NONE",
                    "confidence": 1.0, "destination": None, "date": None,
                    "passengers": 1, "engine": "wake-word",
                })
                _conversation_history.append({"role": "assistant", "content": greeting})
                continue

            # ------------------------------------------------------------------
            # Regular text commands  -  route through state machine.
            # ------------------------------------------------------------------
            if not text:
                continue

            _log(f"[{_agent_state}] Processing: \"{text}\"")

            # -- SLEEPING  -  dormant; detect implicit wake+command in STT text --
            # Vosk may pass us a fully-formed sentence like "hi go book a flight"
            # before Java's wake-word detector fires. Handle it here so Go still
            # responds even if the wake_word JSON arrives a moment later.
            if _agent_state == "SLEEPING":
                norm = _normalize(text)
                _WAKE_TRIGGERS = ["hi go", "hey go", "hello go", "ok go",
                                  "govibe", "hi govibe", "salut go", "bonjour go"]
                has_wake = any(w in norm for w in _WAKE_TRIGGERS)
                _LOGOUT_WORDS_LIST = ["logout","log out","sign out","deconnexion",
                                      "deconnecter","quitter","disconnect"]

                # -- Auth command bypass (login screen) -----------------------
                # If the user is not yet logged in and says an auth command
                # ("log in", "sign in", "sign up" ...) without a wake word, let
                # it through immediately  -  no wake-word required on login screen.
                _AUTH_TRIGGERS = [
                    "login", "log in", "sign in", "connexion",
                    "se connecter", "connecter", "entrer", "me connecter",
                    "authenticate", "creer un compte", "creer compte",
                    "inscription", "register", "sign up", "signup",
                    "focus email", "email", "password", "mot de passe",
                    # face id / camera -- login screen always needs to react to these
                    "face", "camera", "camara", "scan", "faceid", "cam",
                    "biometric", "facial", "face id", "open cam",
                ]
                if not _user_logged_in and any(t in norm for t in _AUTH_TRIGGERS):
                    _log("[SLEEPING] Auth command on login screen  -  bypassing sleep -> HELPING.")
                    _transition("HELPING")
                    # DO NOT continue  -  fall through to the HELPING classification pipeline.
                elif has_wake:
                    if _user_logged_in and any(w in norm for w in _LOGOUT_WORDS_LIST):
                        # Combined wake+logout from plain text stream.
                        _log("[SLEEPING] Implicit combined wake+logout.")
                        _transition("PROCESSING_LOGOUT")
                        speak("Wait, what?", _INSTR_SURPRISED)
                        _send({
                            "intent": "LOGOUT", "response": "Wait, what?",
                            "action": "DECONNEXION", "confidence": 1.0,
                            "destination": None, "date": None, "passengers": 1,
                            "engine": "implicit-wake-logout",
                        })
                    else:
                        # Normal implicit wake  -  treat like a wake_word event.
                        _log("[SLEEPING] Implicit wake detected in text.")
                        if not _intro_done:
                            _log("[SLEEPING] First wakeup  -  starting intro conversation.")
                            _transition("INTRO_WAKING")
                            speak_fast("Oh! Someone's here! Vivian, hey  -  wake up! We've got a user!")
                            _queue_yawn_sfx()
                            speak("what the hell, let me sleep",
                                  "extremely sleepy, groggy, annoyed at being woken")
                            _send({
                                "intent": "GREET",
                                "response": "*yawn* what the hell, let me sleep",
                                "action": "NONE", "confidence": 1.0,
                                "destination": None, "date": None, "passengers": 1,
                                "engine": "implicit-wake-intro",
                            })
                        else:
                            _transition("JUST_WOKEN")
                            if _vivian_ready:
                                speak_fast("Someone's asking for you, Vivian. Your turn!")
                            _queue_yawn_sfx()
                            speak("what do you want?", _INSTR_SLEEPY_WAKE)
                            _send({
                                "intent": "GREET", "response": "*yawn* what do you want?",
                                "action": "NONE", "confidence": 1.0,
                                "destination": None, "date": None, "passengers": 1,
                                "engine": "implicit-wake",
                            })
                    continue   # wake / logout handled  -  skip HELPING pipeline
                else:
                    _log("  [State] SLEEPING  -  ignoring (no wake trigger detected).")
                    continue   # not an auth command, not a wake word  -  ignore

            # -- PROCESSING_LOGOUT  -  TTS sequence in progress; ignore input ----
            if _agent_state == "PROCESSING_LOGOUT":
                _log("  [State] PROCESSING_LOGOUT  -  ignoring speech during TTS.")
                continue

            # -- INTRO_WAKING  -  step 1 spoken, waiting for "wake up vivi" -------
            # -- INTRO_WAKING  -  step 1 spoken, waiting for user to say "wake up Vivi"
            if _agent_state == "INTRO_WAKING":
                # FIX Bug #1+#2: If Vivian already loaded, skip intro and go to HELPING
                if _vivian_ready:
                    _log("  [Intro] Vivian now ready  -  shortcutting INTRO_WAKING to HELPING.")
                    speak_fast("Oh wait  -  she actually loaded! Let's skip the drama.")
                    speak(
                        "I'm here! No need for the whole wake-up act. What can I do for you?",
                        "warm, eager, playful, slightly amused",
                    )
                    _intro_done = True
                    _transition("HELPING")
                    _send({
                        "intent": "GREET",
                        "response": "I'm here! What can I do for you?",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                    continue
                norm = _normalize(text)
                if "wake up" in norm or "wake" in norm:
                    _log("  [Intro] Step 2: slowly waking up.")
                    _transition("INTRO_AWAITING_HI")
                    speak_fast("Oh thank goodness, she's stirring! Come on, Vivian!")
                    speak("hmm... okay, I'm waking up...",
                          "slowly waking up, still a bit sleepy but becoming more alert, soft")
                    _send({
                        "intent": "GREET",
                        "response": "hmm... okay, I'm waking up...",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                else:
                    speak_fast("She's still out cold. Try saying 'wake up'!")
                    speak("hmm... let me sleep...", _INSTR_SLEEPY_WAKE)
                    _send({
                        "intent": "GREET",
                        "response": "*yawn* let me sleep...",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                continue

            # -- INTRO_AWAITING_HI  -  step 2 spoken, waiting for "hi how are you"
            if _agent_state == "INTRO_AWAITING_HI":
                # FIX: If Vivian loaded while waiting here, jump to HELPING immediately
                if _vivian_ready:
                    _log("  [Intro] Vivian ready during INTRO_AWAITING_HI  -  jumping to HELPING.")
                    speak_fast("Actually  -  Vivian is fully online! No more intro needed.")
                    speak(
                        "Hi there! I'm Vivian. Sorry for the slow start! What can I help you with?",
                        "warm, apologetic, bright and energetic",
                    )
                    _intro_done = True
                    _transition("HELPING")
                    _send({
                        "intent": "GREET",
                        "response": "Hi there! I'm Vivian. What can I help you with?",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                    continue
                norm = _normalize(text)
                _HI_TRIGGERS = ["hi", "hello", "hey", "how are you", "how r u",
                                "how are u", "how do you do", "good morning",
                                "bonjour", "salut", "comment allez vous", "comment vas tu"]
                if any(t in norm for t in _HI_TRIGGERS):
                    _log("  [Intro] Steps 3+4: cheerful reply + self-introduction.")
                    _transition("INTRO_AWAITING_OK")
                    # Echo prods Vivian before she speaks
                    speak_fast("She's awake! Go on then, Vivian, introduce yourself.")
                    # Step 3  -  cheerful response
                    speak("I'm good, thank you! So I guess it's time for work, I guess.",
                          "cheerful, warm, slightly playful, now fully awake")
                    # Step 4  -  self-introduction (auto-chained)
                    speak(
                        "Did you hear about GoVibe? Let me introduce myself. "
                        "My name is Vivian, they call me Vivi. You can call me Vivi.",
                        "warm, friendly, inviting, with a smile in the voice"
                    )
                    # Echo adds a cheeky aside
                    speak_fast("And I'm Echo  -  the one who actually showed up on time. Don't tell her I said that.")
                    _send({
                        "intent": "GREET",
                        "response": "My name is Vivian, they call me Vivi. You can call me Vivi.",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                else:
                    speak_fast("Vivian... they said hi. Say something!")
                    speak("hmm... what? Say hi to me!", _INSTR_CONFUSED)
                    _send({
                        "intent": "GREET",
                        "response": "hmm... what? Say hi to me!",
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "intro",
                    })
                continue

            if _agent_state == "INTRO_AWAITING_OK":
                _log("  [Intro] Step 5: Great!  -  marking intro done, entering HELPING.")
                speak("Great!", "enthusiastic, pleased, warm")
                speak_fast("Welcome to the team, Vivian. Only took you forever. Alright team, let's go!")
                _intro_done = True
                _transition("HELPING")
                _send({
                    "intent": "GREET", "response": "Great!", "action": "NONE",
                    "confidence": 1.0, "destination": None, "date": None,
                    "passengers": 1, "engine": "intro",
                })
                continue

            # -- AWAITING_WELLNESS  -  Go asked "how are you?" and waits for reply -
            #
            # Positive response -> "That's great! How can I help you, sir?"
            # Negative response -> empathetic reply -> HELPING
            # Anything else    -> gentle pivot -> HELPING
            # In ALL cases we send a GREET response and wait for the next command.
            if _agent_state == "AWAITING_WELLNESS":
                # Guidance response: user replied to "do you need help or should I guide you?"
                norm_words = set(_normalize(text).split())
                if norm_words & _YES_WORDS:
                    _log("  [Guidance] YES -- eager 'okaaayy' -> HELPING.")
                    resp = "okaaayy"
                    speak_fast(resp)  # short pivot -- edge-tts, no Qwen3 gap
                    _pending_command = None
                    _transition("HELPING")
                    _send({
                        "intent": "GREET", "response": resp, "action": "NONE",
                        "confidence": 1.0, "destination": None, "date": None,
                        "passengers": 1, "engine": "state-machine",
                    })
                    continue
                else:
                    # Check if user said a real command (e.g. "book", "cars", "hotels").
                    # If so, don't swallow it -- acknowledge briefly and fall through to HELPING.
                    _wk_intent, _wk_conf = _direct_match(text)
                    if _wk_intent == "UNKNOWN":
                        _wk_intent, _wk_conf = classify(text)
                    _wk_action = INTENTS.get(_wk_intent, {}).get("action", "NONE")
                    if _wk_intent != "UNKNOWN" and _wk_action not in ("NONE", "UNKNOWN") and _wk_conf >= 0.75:
                        # Real actionable command -- acknowledge and fall through to HELPING.
                        _log(f"  [Guidance] Command {_wk_intent!r} detected -- skipping pivot, falling through.")
                        # Only say "Sure! On it." if this intent has no banter pair.
                        # When banter exists, STEP 1 in the HELPING pipeline will speak instead,
                        # avoiding a triple-speech (guidance ack + banter Echo + banter Vivian).
                        if not _ECHO_VIVIAN_CMD_BANTER.get(_wk_intent):
                            speak_fast("Sure! On it.")
                        _pending_command = None
                        _transition("HELPING")
                        # No continue -- fall through to HELPING pipeline to execute the command.
                    else:
                        _log("  [Guidance] Non-YES -- 'Alright, what can I do for you?' -> HELPING.")
                        resp = "Alright, what can I do for you?"
                        speak_fast(resp)  # short pivot -- edge-tts, no Qwen3 gap
                        _pending_command = None
                        _transition("HELPING")
                        _send({
                            "intent": "GREET", "response": resp, "action": "NONE",
                            "confidence": 1.0, "destination": None, "date": None,
                            "passengers": 1, "engine": "state-machine",
                        })
                        continue

            # -- JUST_WOKEN  -  first command after wake; user-specific greeting -
            #
            # Scenario 2 (not logged in): apologetic laugh, then help immediately.
            # Scenario 3 (logged in):     warm wellness greeting -> AWAITING_WELLNESS.
            if _agent_state == "JUST_WOKEN":
                if not _user_logged_in:
                    # Scenario 2: new / anonymous user.
                    _log("  [State] JUST_WOKEN + not logged in -> apologetic response.")
                    if _vivian_ready:
                        speak_fast("Your turn, Viv. Don't embarrass us.")
                    speak("oh sorry, hahaha, time for work I guess", _INSTR_APOLOGETIC)
                    _transition("HELPING")
                    # Fall through to the HELPING classification pipeline below.
                else:
                    # Returning logged-in user  -  recognition + guidance offer.
                    name_part = f" {_user_name}" if _user_name else ""
                    _log(f"  [State] JUST_WOKEN + logged in as '{_user_name}' -> recognition + guidance.")
                    recog    = f"oh it was you{name_part}!"
                    guidance = "do you need help or should I guide you?"
                    if _vivian_ready:
                        speak_fast(f"Hey, it's{name_part}! Vivian, go say hi!")
                    # Use speak_fast so mic reopens quickly instead of waiting for Qwen3.
                    # Echo handles the full greeting instantly -- mic reopens in ~600ms.
                    speak_fast(f"{recog} {guidance}")
                    _pending_command = text
                    _transition("AWAITING_WELLNESS")
                    _send({
                        "intent": "GREET",
                        "response": guidance,
                        "action": "NONE", "confidence": 1.0,
                        "destination": None, "date": None, "passengers": 1,
                        "engine": "state-machine",
                    })
                    continue

            # -- AWAITING_CONFIRMATION  -  (legacy) waiting for yes / no / new command --
            #
            # Scenario 4: user says YES -> play "okaaayy", execute pending command.
            # Scenario 5: anything else (including NO) -> "Alright, what can I do
            #             for you?" and process the new utterance as a command.
            if _agent_state == "AWAITING_CONFIRMATION":
                norm_words = set(_normalize(text).split())
                if norm_words & _YES_WORDS:
                    # Scenario 4: user confirmed  -  execute the pending command.
                    _log("  [Confirmation] YES -> executing pending command.")
                    speak("okaaayy", _INSTR_EAGER)
                    _transition("HELPING")
                    # Replace current text with the pending command so the pipeline
                    # below classifies what the user originally asked for.
                    if _pending_command:
                        text = _pending_command
                        _pending_command = None
                    # Fall through to HELPING pipeline.
                else:
                    # Scenario 5: anything else  -  decline or new command.
                    _log("  [Confirmation] Non-YES response -> shifting to HELPING with new command.")
                    speak("Alright, what can I do for you?", _INSTR_DECLINE)
                    _pending_command = None
                    _transition("HELPING")
                    # Fall through to HELPING pipeline using the new text.

            # -- HELPING  -  full three-tier classification pipeline -------------

            # -- Intro redirect: auth bypass puts us in HELPING before intro ----
            # The login screen sends user_context(logged_in=false) which transitions
            # SLEEPING -> HELPING, so we never hit the SLEEPING wake-word path.
            # When the user says "hey go" / "hi go" and the intro hasn't run yet,
            # redirect into the intro conversation immediately.
            if not _intro_done:
                _norm_intro = _normalize(text)
                _VIVI_TRIGGERS = [
                    "hey go", "hi go", "hello go", "ok go",
                    "hey govibe", "hi govibe", "govibe",
                    "salut go", "bonjour go",
                ]
                if any(t in _norm_intro for t in _VIVI_TRIGGERS):
                    # Strip the wake prefix; check for a trailing command.
                    # 'hey go email' -> remaining='email' -> skip intro, classify.
                    _remaining = _norm_intro
                    for _trig in sorted(_VIVI_TRIGGERS, key=len, reverse=True):
                        _remaining = _remaining.replace(_trig, '', 1).strip()
                    if _remaining and len(_remaining) > 2:
                        # Wake + trailing command: skip intro, classify directly.
                        _log(f"[Intro] Wake+command: '{_remaining}' -- skipping intro.")
                        _intro_done = True
                        speak_fast("Oh! Jumping straight in -- got it!")
                        text = _remaining  # re-classify with the command text only
                    else:
                        # Pure wake call ('hey go' alone): trigger intro flow.
                        _log("[Intro] Pure wake trigger -> starting intro flow.")
                        _transition("INTRO_WAKING")
                        speak_fast("Oh! Someone's here! Vivian, hey -- wake up! We've got a user!")
                        _queue_yawn_sfx()
                        speak(
                            "what the hell, let me sleep",
                            "extremely sleepy, groggy, annoyed at being woken",
                        )
                        _send({
                            "intent": "GREET",
                            "response": "*yawn* what the hell, let me sleep",
                            "action": "NONE", "confidence": 1.0,
                            "destination": None, "date": None, "passengers": 1,
                            "engine": "intro-trigger",
                        })
                        continue

            text          = _apply_stt_corrections(text)   # fix homophones first
            enriched_text = _enrich_with_context(text)
            destination   = None
            date          = None
            passengers    = 1
            used_engine   = _engine

            # -- Login-screen Face ID override (pre-classify) ------------------
            # On the login screen the most common voice action is Face ID login.
            # If the raw text contains any face/camera keyword -- even partially
            # mangled by Vosk -- route straight to OPEN_CAMERA before running the
            # full ML stack, which can mis-classify short ambiguous phrases.
            _login_cam_kws = [
                "face", "camera", "camara", "cam", "scan", "faceid",
                "facial", "biometric", "recognition", "phase id", "base id",
                "id login", "id log",
            ]
            # bare "id" alone on login screen = user trying to say "face id"
            _login_bare_id = text.lower().strip() in ("id", "i d", "face i d")
            if not _user_logged_in and (any(kw in text.lower() for kw in _login_cam_kws) or _login_bare_id):
                intent_key  = "OPEN_CAMERA"
                confidence  = 0.95
                used_engine = "login-face-override"
                _log("  Login-screen face/camera keyword detected -- override -> OPEN_CAMERA")
            else:
                intent_key = "UNKNOWN"

            # Tier 0: direct accent-insensitive phrase lookup (instant).
            if intent_key == "UNKNOWN":
                intent_key, confidence = _direct_match(enriched_text)
            if intent_key != "UNKNOWN":
                _log(f"  Tier-0 direct match -> {intent_key} (conf=1.0)")
                used_engine = "direct-match"

            # Tier 1: sentence-transformers (~30 ms).
            if intent_key == "UNKNOWN":
                intent_key, confidence = classify(enriched_text)

            # Tier 2: Gemini API with function calling (if tier-1 uncertain).
            if intent_key == "UNKNOWN" and not _gemini_disabled and time.time() >= _gemini_retry_after:
                _log("  Tier-1 uncertain  -  escalating to Gemini API...")
                ds_result = _classify_gemini(enriched_text)
                if ds_result is not None:
                    ds_intent, ds_conf, ds_dest, ds_date, ds_resp, ds_pax = ds_result
                    if ds_intent != "UNKNOWN" and ds_conf >= 0.60:
                        intent_key  = ds_intent
                        confidence  = ds_conf
                        destination = ds_dest
                        date        = ds_date
                        passengers  = ds_pax
                        used_engine = "gemini"
                        if ds_resp and intent_key in INTENTS:
                            INTENTS[intent_key]["_llm_response"] = ds_resp
                # Tier 3: Ollama fallback (local, only if Gemini also failed).
                if intent_key == "UNKNOWN" and _check_ollama():
                    _log("  Gemini failed  -  trying Ollama...")
                    ollama_result = _classify_ollama(enriched_text)
                    if ollama_result is not None:
                        ol_intent, ol_conf, ol_dest, ol_date, ol_resp = ollama_result
                        if ol_intent != "UNKNOWN" and ol_conf >= 0.65:
                            intent_key  = ol_intent
                            confidence  = ol_conf
                            destination = ol_dest
                            date        = ol_date
                            used_engine = "ollama"
                            if ol_resp and intent_key in INTENTS:
                                INTENTS[intent_key]["_llm_response"] = ol_resp

            # -- Login-screen low-confidence safety net -----------------------
            # On the login screen, garbled Vosk output can mis-classify as an
            # unrelated intent (e.g. "they say d" -> DESCRIBE 0.54).
            # If confidence is too low for a non-auth intent, reset to UNKNOWN
            # so the "I didn't catch that" path fires instead of wrong action.
            if not _user_logged_in and intent_key != "UNKNOWN":
                _LOGIN_SAFE_INTENTS = {
                    "OPEN_CAMERA", "LOGIN", "SIGNUP", "GREET",
                    "FOCUS_EMAIL", "FOCUS_PASSWORD", "VIVIAN_CALL",
                }
                if intent_key not in _LOGIN_SAFE_INTENTS and confidence < 0.70:
                    _log(f"  [Login-gate] Rejected {intent_key!r} conf={confidence:.2f} (below 0.70 on login screen) -> UNKNOWN")
                    intent_key = "UNKNOWN"
                    confidence = 0.0

            result = build_response(enriched_text, intent_key, confidence)
            # Unsupervised learning: record this utterance outcome
            if _tracker:
                _tracker.record(_normalize(enriched_text), intent_key, confidence, state=_agent_state)
                _tracker.maybe_consolidate()
            if destination:
                result["destination"] = destination
            if date:
                result["date"] = date
            result["passengers"] = passengers
            result["engine"]     = used_engine

            _llm_resp = INTENTS.get(intent_key, {}).pop("_llm_response", None)
            if _llm_resp:
                result["response"] = _llm_resp

            # -- Tier 4: RAG knowledge base (last resort when all tiers fail) ---
            # Instead of the generic "I didn't catch that", search the GoVibe
            # knowledge base and answer from a real paragraph if one matches.
            if intent_key == "UNKNOWN":
                _rag_ans = _rag_search(enriched_text)
                if _rag_ans:
                    _log("  [RAG] Knowledge-base hit  -  overriding UNKNOWN response.")
                    result["response"] = _rag_ans
                    result["intent"]   = "RAG_KB"
                    result["action"]   = "NONE"
                    result["engine"]   = "rag-kb"
                    intent_key         = "RAG_KB"
                    used_engine        = "rag-kb"

            # -- DB-powered description overrides -----------------------------
            # For DESCRIBE_ACTIVITY and DESCRIBE_CAR we build the response
            # directly from the live DB snapshot so Go gives accurate details.
            if intent_key == "DESCRIBE_ACTIVITY":
                last_city = _entity_memory.get("last_city")
                result["response"] = _describe_activities_from_db(last_city)
            elif intent_key == "DESCRIBE_CAR":
                last_car = _entity_memory.get("last_car")
                result["response"] = _describe_cars_from_db(last_car)

            _log(
                f"  -> intent={intent_key}  confidence={confidence:.3f}  "
                f"action={result['action']}  engine={used_engine}  "
                f"dest={destination}  date={date}  pax={passengers}"
            )

            # -- Speak response via Qwen3-TTS then send JSON to Java -----------
            if intent_key == "LOGOUT":
                # Scenario 7 (from HELPING): surprised reaction first.
                # Use speak_fast (edge-tts) so the DECONNEXION action is sent
                # to Java immediately  -  Qwen3 here would block for 10-30 s and
                # delay the actual scene switch, making logout feel broken.
                speak_fast("Wait, what?")
                result["response"] = "Wait, what?"
                _transition("PROCESSING_LOGOUT")
                # Send DECONNEXION so Java's CommandRouter performs the logout.
                # Java will then call sendUserContext(false,null) which triggers
                # the post-logout personality sequence (confused + yawn).
                _send(result)
                # (Post-logout sequence fires when user_context(logged_in=false) arrives)

            elif intent_key == "WEATHER":
                # -- Real-time weather fetch + speak + send panel to Java ----------
                # Extract city: prefer LLM-extracted destination, else try to parse
                # from raw text ("weather in Paris"), else fall back to a default.
                _w_city = destination.strip().title() if destination else None
                if not _w_city:
                    import re as _re_w
                    _cm = _re_w.search(r'\bin\s+([A-Za-z][A-Za-z\s]{2,20})$', text, _re_w.IGNORECASE)
                    if _cm:
                        _w_city = _cm.group(1).strip().title()
                if not _w_city:
                    _w_city = "Tunis"   # sensible default for GoVibe users

                _log(f"[Weather] Fetching real-time data for: {_w_city!r}")

                # Fire Echo banter immediately while the API call runs
                _w_vivian_catchup: Optional[str] = None
                _banter_pairs = _ECHO_VIVIAN_CMD_BANTER.get("WEATHER", [])
                if _banter_pairs:
                    _echo_l, _vivian_l = _rnd.choice(_banter_pairs)
                    speak_fast(_echo_l)
                    _w_vivian_catchup = _vivian_l   # played after _send below

                _weather_data = _fetch_weather(_w_city)
                if _weather_data:
                    _wt = _weather_data["temp"]
                    _wc = _weather_data["condition"]
                    _wh = _weather_data["humidity"]
                    _ww = _weather_data["wind"]
                    _wf = _weather_data["feel"]
                    _w_speech = (
                        f"The weather in {_w_city} right now: {_wc}, {_wt}. "
                        f"Feels like {_wf}, humidity {_wh}, wind {_ww}."
                    )
                    speak_fast(_w_speech)
                    result["response"] = _w_speech
                    # Send weather_show so Java opens the UI panel (fire-and-forget,
                    # no seq  -  PythonVoiceAgent handles it like tts_status).
                    _send_raw({
                        "type":      "weather_show",
                        "city":      _w_city,
                        "temp":      _wt,
                        "condition": _wc,
                        "humidity":  _wh,
                        "wind":      _ww,
                        "feel":      _wf,
                    })
                else:
                    speak_fast(f"Sorry, I couldn't get the weather for {_w_city} right now.")
                    result["response"] = f"Weather unavailable for {_w_city}."
                _send(result)
                if _w_vivian_catchup:
                    speak(_w_vivian_catchup, "warm, curious, genuinely interested in the weather")

            else:
                # Normal helpful response  -  Scenario 6.
                # -- Special intent banter: Echo & Vivian go back and forth --------
                _NO_ON_IT = {
                    "UNKNOWN", "HELP", "LOGIN", "SIGNUP",
                    "GREET", "VIVIAN_CALL", "SMALLTALK_GOODBYE", "SMALLTALK_CASUAL",
                    "FOCUS_EMAIL", "FOCUS_PASSWORD",
                    "ECHO_CALL", "WHERE_IS_VIVIAN",
                }

                if intent_key == "GREET":
                    _GREET_BANTER = [
                        ("Hey hey! Welcome to GoVibe!",         "Hi there! Ready to go somewhere amazing?"),
                        ("Oh hi! Echo here  -  and Vivian too!",  "Hey you! So glad you're here!"),
                        ("Hello! You caught us in a great mood!","Hi! What adventure can we plan for you?"),
                        ("Well hey there, welcome!",             "Hey! Come in, come in! Where are we headed?"),
                        ("Hi! The GoVibe crew is all here!",     "Hello! I'm Vivian  -  ask me anything!"),
                    ]
                    _echo_l, _vivian_l = _rnd.choice(_GREET_BANTER)
                    speak_fast(_echo_l)
                    speak(_vivian_l, _INSTR_GREETING)
                    result["response"] = _vivian_l
                    _send(result)

                elif intent_key == "VIVIAN_CALL":
                    _VIVIAN_BANTER = [
                        ("Hold on, let me get her  -  Vivian!",              "I'm here, I'm here! You called?"),
                        ("She's right here  -  come on V, someone needs you!","Yeah yeah I'm here. What's up?"),
                        ("Ooh, asking for the star? Vivian, stage is yours!","Hello! Yes? What can I do for you?"),
                        ("Calling for Vivian! One sec...",                   "Present! What do you need?"),
                        ("Vivian! You've got a fan!",                       "Ha! I'm never far. Yes, I'm listening!"),
                    ]
                    _echo_l, _vivian_l = _rnd.choice(_VIVIAN_BANTER)
                    speak_fast(_echo_l)
                    speak(_vivian_l, _INSTR_GREETING)
                    result["response"] = _vivian_l
                    _send(result)

                elif intent_key == "SMALLTALK_GOODBYE":
                    _BYE_BANTER = [
                        ("Bye! Don't miss us too much!",        "Take care! Come back soon  -  safe travels!"),
                        ("See ya! We'll be here when you return!","Bye bye! Stay safe out there!"),
                        ("Later! It was great having you here!", "Take care! Wherever you're going, have fun!"),
                        ("Bye for now! Echo signing off!",       "And Vivian too  -  see you next time!"),
                    ]
                    _echo_l, _vivian_l = _rnd.choice(_BYE_BANTER)
                    speak_fast(_echo_l)
                    speak(_vivian_l, _INSTR_GREETING)
                    result["response"] = _vivian_l
                    _send(result)

                elif intent_key == "SMALLTALK_CASUAL":
                    _CASUAL_BANTER = [
                        ("Right?",                   "Totally! Anything else I can help with?"),
                        ("Glad you think so!",        None),
                        (None,                        "Ha! Good vibes all around. Need anything?"),
                        ("That's what I'm here for!", None),
                        ("Nice!",                     "Yep! We're here if you need us."),
                        (None,                        "Haha, I know right? So, what's next?"),
                    ]
                    _echo_l, _vivian_l = _rnd.choice(_CASUAL_BANTER)
                    if _echo_l:
                        speak_fast(_echo_l)
                        result["response"] = _echo_l
                    if _vivian_l:
                        speak(_vivian_l, _INSTR_GREETING)
                        result["response"] = _vivian_l
                    _send(result)

                elif intent_key == "ECHO_CALL":
                    # User explicitly called Echo  -  she responds instantly, no Qwen3.
                    _ECHO_SELF_ACKS = [
                        "Right here! What do you need?",
                        "Echo online! Vivian's... doing her thing. I've got you.",
                        "Present! Ask away  -  I'm faster anyway.",
                        "Echo here! Fire away. What can I do for you?",
                        "You rang? Echo, present and accounted for! Go ahead.",
                        "I'm here! Always on time, unlike some co-workers. What's up?",
                    ]
                    reply = _rnd.choice(_ECHO_SELF_ACKS)
                    speak_fast(reply)
                    result["response"] = reply
                    _send(result)

                elif intent_key == "WHERE_IS_VIVIAN":
                    if not _vivian_ready:
                        _WHERE_LINES = [
                            "Doing the only thing she's good at  -  sleeping!",
                            "Still loading. At this rate she'll be ready by spring. Classic Vivian.",
                            "Oh Vivian? Napping. Big surprise. I'll handle things while she snoozes.",
                            "Asleep, obviously. She'll be up eventually. Maybe. Don't hold your breath.",
                            "Vivian sent me a note saying 'five more minutes'. She said that twenty minutes ago.",
                            "Honestly? Unknown. Last I heard she was 'almost ready'. That was a while ago.",
                        ]
                        reply = _rnd.choice(_WHERE_LINES)
                        speak_fast(reply)
                    else:
                        speak_fast("She's right here! Vivian, they're asking about you!")
                        speak("I'm here! Sorry  -  was I supposed to be more obvious? Yes, fully awake. What do you need?", _INSTR_APOLOGETIC)
                        reply = "She's right here  -  ask her anything!"
                    result["response"] = reply
                    _send(result)

                elif intent_key == "GET_WEATHER":
                    # Open-Meteo: free, no API key required.
                    import re as _re, urllib.request as _ur, json as _jn
                    _wtext = _normalize(text)
                    _loc_m = _re.search(
                        r"(?:weather|temperature|forecast)\s+(?:in|for|at)\s+([a-z ]+?)(?:\s*$|\?)",
                        _wtext
                    ) or _re.search(r" in ([a-z ]{3,30})(?:\s*$|\?)", _wtext)
                    _city = _loc_m.group(1).strip().title() if _loc_m else "Tunis"
                    speak_fast(f"Checking weather in {_city}!")
                    try:
                        _city_q = _city.replace(" ", "+")
                        _geo_url = f"https://geocoding-api.open-meteo.com/v1/search?name={_city_q}&count=1&language=en&format=json"
                        with _ur.urlopen(_geo_url, timeout=5) as _gr:
                            _geo = _jn.loads(_gr.read())
                        _res = _geo.get("results", [])
                        if not _res:
                            speak_fast(f"I couldn't find {_city} in the weather database.")
                            result["response"] = f"City {_city} not found."
                        else:
                            _lat = _res[0]["latitude"]; _lon = _res[0]["longitude"]
                            _w_url = (f"https://api.open-meteo.com/v1/forecast"
                                      f"?latitude={_lat}&longitude={_lon}"
                                      f"&current=temperature_2m,weathercode,windspeed_10m"
                                      f"&temperature_unit=celsius&windspeed_unit=kmh&timezone=auto")
                            with _ur.urlopen(_w_url, timeout=5) as _wr:
                                _wd = _jn.loads(_wr.read())
                            _cur = _wd.get("current", {})
                            _temp = _cur.get("temperature_2m", "?")
                            _wind = _cur.get("windspeed_10m", "?")
                            _WMO = {0:"clear sky",1:"mainly clear",2:"partly cloudy",3:"overcast",
                                    45:"foggy",51:"light drizzle",53:"drizzle",55:"heavy drizzle",
                                    61:"light rain",63:"rain",65:"heavy rain",71:"light snow",
                                    73:"snow",75:"heavy snow",80:"showers",95:"thunderstorm"}
                            _wcode = int(_cur.get("weathercode", 0))
                            _desc = _WMO.get(_wcode, "mixed conditions")
                            _wresp = f"Weather in {_city}: {_temp}°C, {_desc}, wind {_wind} km/h."
                            speak_fast(f"Here you go! {_wresp}")
                            if _vivian_ready:
                                speak(f"It is {_temp} degrees in {_city} with {_desc} skies.", _INSTR_HELPFUL_OFFER)
                            result["response"] = _wresp
                    except Exception as _we:
                        _log(f"[Weather] Error: {_we}")
                        speak_fast("Weather service is unavailable right now. Try again in a moment.")
                        result["response"] = f"Weather error: {_we}"
                    _send(result)

                else:
                    # -- Dual-agent command handling ---------------------------------
                    # Design: Echo acks INSTANTLY via edge-tts (~200ms), then _send(result)
                    # fires so Java acts NOW, then Vivian catches up with her warm Qwen3 voice
                    # AFTER navigation/action has already happened. User hears Echo confirm
                    # in <200ms and sees the screen change, then Vivian's personality quip
                    # arrives as a natural "catchup"  -  she was just a little slow.
                    instr = _INSTR_UNKNOWN if intent_key == "UNKNOWN" else _INSTR_GENERAL
                    resp_text = result["response"]
                    _vivian_catchup: Optional[str] = None  # spoken AFTER _send()

                    # STEP 1  -  Echo acks immediately (edge-tts, instant)
                    if intent_key not in _NO_ON_IT:
                        paired = _ECHO_VIVIAN_CMD_BANTER.get(intent_key)
                        if paired and _vivian_ready:
                            _echo_setup, _vivian_quip = _rnd.choice(paired)
                            speak_fast(_echo_setup)          # Echo: instant confirmation
                            _vivian_catchup = _vivian_quip   # Vivian delivers this AFTER send
                        elif not _vivian_ready:
                            speak_fast(_rnd.choice(_ECHO_SOLO_ACKS))
                        else:
                            speak_fast(_rnd.choice(_ECHO_SIMPLE_ACKS))

                    # STEP 2  -  Deliver short response via Echo (instant, no waiting)
                    # Skip when banter already fired in STEP 1  -  banter has already confirmed
                    # the action, so speaking resp_text here would be redundant triple-speech.
                    if not _vivian_ready:
                        speak_fast(resp_text)   # Vivian absent  -  Echo handles fully
                    elif _vivian_catchup is None and len(resp_text) <= 80:
                        speak_fast(resp_text)   # Short & no banter  -  Echo instant; Vivian may follow
                    # else: banter confirmed it (STEP 1), or long response  -  Vivian delivers below

                    # STEP 3  -  Send action to Java NOW (Echo already spoke; don't wait for Vivian)
                    _send(result)

                    # STEP 4  -  Vivian catches up after Java has already acted
                    if _vivian_ready:
                        if _vivian_catchup:
                            speak(_vivian_catchup, _INSTR_EAGER)
                        if len(resp_text) > 80:
                            # Long response  -  Vivian delivers her full warm narration
                            speak(resp_text, instr)
                        elif _vivian_catchup is None and intent_key not in _NO_ON_IT \
                                and _rnd.random() < 0.25:
                            _VIVIAN_FOLLOWUPS = [
                                "Echo didn't give you much, did she? Ask me if you want more detail!",
                                "Short answer from Echo. She'll elaborate when she's feeling generous.",
                                "And I'm Vivian  -  I would have said the same but with extra flair.",
                                "That's very Echo of her. Efficient. I'll add colour if you need it!",
                            ]
                            speak(_rnd.choice(_VIVIAN_FOLLOWUPS), "playful, warm, lightly teasing")

            # -- Update conversation & entity memory ---------------------------
            _conversation_history.append({"role": "user", "content": text})
            if result.get("response"):
                _conversation_history.append({"role": "assistant", "content": result["response"]})
            if intent_key != "UNKNOWN":
                _update_entity_memory(intent_key, destination, date, result.get("response", ""))

        except json.JSONDecodeError:
            # Plain-text fallback (non-JSON line on stdin).
            text = raw_line
            intent_key, confidence = classify(text)
            if _tracker:
                _tracker.record(text, intent_key, confidence, state=_agent_state)
            result = build_response(text, intent_key, confidence)
            result["passengers"] = 1
            result["engine"]     = _engine
            speak(result["response"], _INSTR_GENERAL)
            _conversation_history.append({"role": "user", "content": text})
            _send(result)
        except Exception as exc:
            _log(f"Error processing: {exc}")
            _send({
                "intent": "ERROR", "response": "", "action": "UNKNOWN",
                "confidence": 0.0, "destination": None, "date": None,
                "passengers": 1, "engine": "error",
            })


if __name__ == "__main__":
    main()

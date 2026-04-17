from __future__ import annotations

import os
import threading
import time
from collections import deque
from dataclasses import dataclass
from enum import Enum
from typing import Callable, Deque, Optional


@dataclass(frozen=True)
class AcousticProfile:
    label: str
    max_floor: float
    min_floor: float
    multiplier: float
    min_gate: float


class AcousticEnvironment(Enum):
    SILENT = AcousticProfile("Silencieux", 80.0, -1.0, 1.2, 200.0)
    QUIET = AcousticProfile("Calme", 200.0, 80.0, 1.5, 300.0)
    NORMAL = AcousticProfile("Normal", 450.0, 200.0, 1.8, 400.0)
    NOISY = AcousticProfile("Bruyant", 800.0, 450.0, 2.4, 550.0)
    VERY_NOISY = AcousticProfile("Tres bruyant", float("inf"), 800.0, 3.2, 700.0)

    @property
    def label(self) -> str:
        return self.value.label

    @property
    def multiplier(self) -> float:
        return self.value.multiplier

    @property
    def min_gate(self) -> float:
        return self.value.min_gate

    @staticmethod
    def from_floor(floor: float) -> "AcousticEnvironment":
        for env in AcousticEnvironment:
            p = env.value
            if floor >= p.min_floor and floor < p.max_floor:
                return env
        return AcousticEnvironment.VERY_NOISY

    def gate_threshold(self, floor: float) -> float:
        # Use an additive gate model so extremely noisy floors do not explode the threshold.
        adaptive_gate = floor + max(120.0, floor * 0.35)
        return max(self.min_gate, adaptive_gate)


class VADState(Enum):
    SILENCE = "silence"
    SPEECH_START = "speech_start"
    SPEECH_ACTIVE = "speech_active"
    SPEECH_END = "speech_end"


class NoiseOrchestrator:
    DETECTOR_WINDOW = 80
    CALIB_FRAMES = 40
    CALIB_MAX_OBSERVED_FRAMES = 200
    CALIB_MIN_LOW_FRAMES = 10
    CALIB_LOW_RMS_CUTOFF = 180.0
    TTS_SETTLE_MS = 4500
    SNR_WARN_THRESHOLD = 4.0
    SNR_SAMPLE_INTERVAL = 50
    REDETECT_COOLDOWN_MS = 15000
    SPEECH_HANGOVER_FRAMES = 6
    LOW_GATE_FACTOR = 0.75
    SPEECH_OFF_CONFIRM_FRAMES = 3
    ENV_SWITCH_CONFIRM_WINDOWS = 6
    FLOOR_SMOOTHING_ALPHA = 0.1

    _instance: Optional["NoiseOrchestrator"] = None

    @classmethod
    def get_instance(cls) -> "NoiseOrchestrator":
        if cls._instance is None:
            cls._instance = NoiseOrchestrator()
        return cls._instance

    @classmethod
    def create_fresh_for_testing(cls) -> "NoiseOrchestrator":
        return NoiseOrchestrator(tts_settle_ms=NoiseOrchestrator.TTS_SETTLE_MS)

    def __init__(self, tts_settle_ms: int = 0) -> None:
        self._lock = threading.Lock()
        self.calib_low_rms_cutoff = self._env_float(
            "NOISE_CALIB_LOW_RMS_CUTOFF",
            self.CALIB_LOW_RMS_CUTOFF,
            min_value=50.0,
            max_value=1000.0,
        )
        self.low_gate_factor = self._env_float(
            "NOISE_LOW_GATE_FACTOR",
            self.LOW_GATE_FACTOR,
            min_value=0.5,
            max_value=0.95,
        )
        self.speech_off_confirm_frames = self._env_int(
            "NOISE_SPEECH_OFF_CONFIRM_FRAMES",
            self.SPEECH_OFF_CONFIRM_FRAMES,
            min_value=1,
            max_value=12,
        )
        self.current_env = AcousticEnvironment.NORMAL
        self.noise_floor = 50.0
        self.gate_threshold = 500.0
        self.calibrated = False

        self.rms_window: Deque[float] = deque(maxlen=self.DETECTOR_WINDOW)
        self.speech_peaks: Deque[float] = deque(maxlen=20)
        self.calib_samples: Deque[float] = deque(maxlen=self.CALIB_MAX_OBSERVED_FRAMES)

        self.calib_count = 0
        self.calib_sum = 0.0
        self.calib_observed_count = 0
        self.start_time = int(time.time() * 1000) - tts_settle_ms
        self.discarding = False
        self.post_settle_reset_done = False

        self.snr_sample_count = 0
        self.last_redetect_time = 0
        self.speech_hangover = 0
        self.speech_active = False
        self.speech_off_counter = 0
        self.last_confidence = 0.0
        self.vad_state = VADState.SILENCE
        self.pending_env: Optional[AcousticEnvironment] = None
        self.env_change_counter = 0

        self.on_environment_changed: Optional[Callable[[AcousticEnvironment], None]] = None
        self.on_redetect_requested: Optional[Callable[[], None]] = None

    def set_on_environment_changed(self, cb: Optional[Callable[[AcousticEnvironment], None]]) -> None:
        self.on_environment_changed = cb

    def set_on_redetect_requested(self, cb: Optional[Callable[[], None]]) -> None:
        self.on_redetect_requested = cb

    def is_calibrated(self) -> bool:
        with self._lock:
            return self.calibrated

    def get_environment(self) -> AcousticEnvironment:
        with self._lock:
            return self.current_env

    def get_noise_floor(self) -> float:
        with self._lock:
            return self.noise_floor

    def get_gate_threshold(self) -> float:
        with self._lock:
            return self.gate_threshold

    def get_speech_confidence(self) -> float:
        with self._lock:
            return self.last_confidence

    def get_vad_state(self) -> VADState:
        with self._lock:
            return self.vad_state

    def get_tuning_values(self) -> dict[str, float | int]:
        with self._lock:
            return {
                "calib_low_rms_cutoff": self.calib_low_rms_cutoff,
                "low_gate_factor": self.low_gate_factor,
                "speech_off_confirm_frames": self.speech_off_confirm_frames,
            }

    @staticmethod
    def _env_float(name: str, default: float, *, min_value: float, max_value: float) -> float:
        raw = os.environ.get(name)
        if raw is None:
            return default
        try:
            parsed = float(raw)
        except ValueError:
            return default
        return max(min_value, min(max_value, parsed))

    @staticmethod
    def _env_int(name: str, default: int, *, min_value: int, max_value: int) -> int:
        raw = os.environ.get(name)
        if raw is None:
            return default
        try:
            parsed = int(raw)
        except ValueError:
            return default
        return max(min_value, min(max_value, parsed))

    def feed_frame(self, rms: float) -> bool:
        with self._lock:
            return self._feed_frame_locked(rms)

    def _feed_frame_locked(self, rms: float) -> bool:
        now = int(time.time() * 1000)

        if (now - self.start_time) < self.TTS_SETTLE_MS:
            self.discarding = True
            self.post_settle_reset_done = False
            self.last_confidence = 0.0
            self.vad_state = VADState.SILENCE
            return False

        if self.discarding and not self.post_settle_reset_done:
            self.discarding = False
            self.post_settle_reset_done = True
            self.calib_count = 0
            self.calib_sum = 0.0
            self.calib_observed_count = 0
            self.calib_samples.clear()
            self.rms_window.clear()
            self.speech_peaks.clear()
            self.speech_hangover = 0
            self.speech_active = False
            self.speech_off_counter = 0
            self.vad_state = VADState.SILENCE

        if not self.calibrated:
            self.calib_observed_count += 1
            self.calib_samples.append(rms)
            if rms < self.calib_low_rms_cutoff:
                self.calib_sum += rms
                self.calib_count += 1
            if self.calib_count >= self.CALIB_FRAMES:
                floor = self.calib_sum / self.calib_count
                self._commit_new_floor(floor)
                self.calibrated = True
            elif self.calib_observed_count >= self.CALIB_FRAMES and self.calib_count >= self.CALIB_MIN_LOW_FRAMES:
                floor = self.calib_sum / self.calib_count
                self._commit_new_floor(floor)
                self.calibrated = True
            elif self.calib_observed_count >= self.CALIB_FRAMES and self.calib_count < self.CALIB_MIN_LOW_FRAMES:
                floor = self._estimate_calibration_floor()
                self._commit_new_floor(floor)
                self.calibrated = True
            elif self.calib_observed_count >= self.CALIB_MAX_OBSERVED_FRAMES:
                floor = self._estimate_calibration_floor()
                self._commit_new_floor(floor)
                self.calibrated = True
            self.last_confidence = 0.0
            self.vad_state = VADState.SILENCE
            return False

        self.rms_window.append(rms)
        high_gate = self.gate_threshold
        low_gate = self.gate_threshold * self.low_gate_factor
        active_gate = low_gate if (self.speech_active or self.speech_hangover > 0) else high_gate

        frame_is_speech = rms >= active_gate
        self.last_confidence = min(1.0, max(0.0, rms / max(1.0, active_gate)))
        was_speech_active = self.speech_active

        if not frame_is_speech:
            self.noise_floor = 0.98 * self.noise_floor + 0.02 * rms
            self.speech_hangover = max(0, self.speech_hangover - 1)
            self.speech_off_counter += 1
            if self.speech_off_counter >= self.speech_off_confirm_frames and self.speech_hangover == 0:
                self.speech_active = False
                if was_speech_active:
                    self.vad_state = VADState.SPEECH_END
                else:
                    self.vad_state = VADState.SILENCE
            elif self.speech_active:
                self.vad_state = VADState.SPEECH_ACTIVE
            else:
                self.vad_state = VADState.SILENCE
        else:
            self.speech_peaks.append(rms)
            self.speech_hangover = self.SPEECH_HANGOVER_FRAMES
            self.speech_active = True
            self.speech_off_counter = 0
            self.vad_state = VADState.SPEECH_START if not was_speech_active else VADState.SPEECH_ACTIVE

        if len(self.rms_window) == self.DETECTOR_WINDOW:
            low_quartile = sorted(self.rms_window)[: max(1, self.DETECTOR_WINDOW // 4)]
            rolling_floor = sum(low_quartile) / len(low_quartile)
            self.noise_floor = (1.0 - self.FLOOR_SMOOTHING_ALPHA) * self.noise_floor + (
                self.FLOOR_SMOOTHING_ALPHA * rolling_floor
            )
            self._check_environment_change(self.noise_floor)

        self.snr_sample_count += 1
        if self.snr_sample_count >= self.SNR_SAMPLE_INTERVAL:
            self.snr_sample_count = 0
            self._validate_snr(now)
            self.speech_peaks.clear()

        return self.speech_active

    def force_recalibrate(self) -> None:
        with self._lock:
            self._force_recalibrate_locked()

    def _force_recalibrate_locked(self) -> None:
        self.calibrated = False
        self.calib_count = 0
        self.calib_sum = 0.0
        self.calib_observed_count = 0
        self.start_time = int(time.time() * 1000) - self.TTS_SETTLE_MS
        self.rms_window.clear()
        self.speech_peaks.clear()
        self.calib_samples.clear()
        self.speech_hangover = 0
        self.speech_active = False
        self.speech_off_counter = 0
        self.last_confidence = 0.0
        self.vad_state = VADState.SILENCE
        self.discarding = False
        self.post_settle_reset_done = False
        self.pending_env = None
        self.env_change_counter = 0

    def _estimate_calibration_floor(self) -> float:
        if not self.calib_samples:
            return self.noise_floor
        low_quartile = sorted(self.calib_samples)[: max(1, len(self.calib_samples) // 4)]
        return sum(low_quartile) / len(low_quartile)

    def _commit_new_floor(self, floor: float) -> None:
        self.noise_floor = floor
        new_env = AcousticEnvironment.from_floor(floor)
        self.gate_threshold = new_env.gate_threshold(floor)
        prev = self.current_env
        self.current_env = new_env
        if prev != new_env and self.on_environment_changed:
            self.on_environment_changed(new_env)

    def _check_environment_change(self, rolling_floor: float) -> None:
        detected = AcousticEnvironment.from_floor(rolling_floor)
        if detected == self.current_env:
            self.pending_env = None
            self.env_change_counter = 0
            return

        if self.pending_env != detected:
            self.pending_env = detected
            self.env_change_counter = 1
            return

        self.env_change_counter += 1
        if self.env_change_counter >= self.ENV_SWITCH_CONFIRM_WINDOWS:
            self._commit_new_floor(rolling_floor)
            self.pending_env = None
            self.env_change_counter = 0

    def _validate_snr(self, now_ms: int) -> None:
        if not self.speech_peaks or self.noise_floor <= 0:
            return
        mean_speech = sum(self.speech_peaks) / len(self.speech_peaks)
        snr = (mean_speech - self.noise_floor) / max(1.0, self.noise_floor)
        if snr < self.SNR_WARN_THRESHOLD and (now_ms - self.last_redetect_time) > self.REDETECT_COOLDOWN_MS:
            self.last_redetect_time = now_ms
            self._force_recalibrate_locked()
            if self.on_redetect_requested:
                self.on_redetect_requested()

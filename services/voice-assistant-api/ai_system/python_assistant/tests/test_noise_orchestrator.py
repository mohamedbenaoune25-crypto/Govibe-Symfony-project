from python_assistant.noise_orchestrator import AcousticEnvironment, NoiseOrchestrator, VADState


def test_environment_classification_boundaries():
    assert AcousticEnvironment.from_floor(0) == AcousticEnvironment.SILENT
    assert AcousticEnvironment.from_floor(80) == AcousticEnvironment.QUIET
    assert AcousticEnvironment.from_floor(450) == AcousticEnvironment.NOISY
    assert AcousticEnvironment.from_floor(800) == AcousticEnvironment.VERY_NOISY


def test_gate_threshold_never_below_min_gate():
    assert AcousticEnvironment.SILENT.gate_threshold(40) == 200.0
    assert AcousticEnvironment.QUIET.gate_threshold(150) == 300.0


def test_calibrates_at_40_frames():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(39):
        o.feed_frame(150.0)
    assert not o.is_calibrated()
    o.feed_frame(150.0)
    assert o.is_calibrated()
    assert o.get_environment() == AcousticEnvironment.QUIET


def test_speech_gate_after_calibration():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(40):
        o.feed_frame(150.0)
    assert o.feed_frame(1000.0)
    assert o.feed_frame(100.0)
    off_state = True
    for _ in range(10):
        off_state = o.feed_frame(100.0)
    assert not off_state


def test_very_noisy_gate_not_over_aggressive():
    threshold = AcousticEnvironment.VERY_NOISY.gate_threshold(1000.0)
    assert threshold == 1350.0


def test_speech_hangover_keeps_short_followup_frame():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(40):
        o.feed_frame(150.0)

    assert o.feed_frame(500.0)
    assert o.feed_frame(250.0)


def test_calibration_ignores_loud_frames_bias():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(20):
        o.feed_frame(350.0)
    for _ in range(40):
        o.feed_frame(120.0)

    assert o.is_calibrated()
    assert o.get_noise_floor() < 200.0


def test_vad_state_transitions():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(40):
        o.feed_frame(120.0)

    assert o.get_vad_state() == VADState.SILENCE
    assert o.feed_frame(450.0)
    assert o.get_vad_state() == VADState.SPEECH_START
    assert o.feed_frame(420.0)
    assert o.get_vad_state() == VADState.SPEECH_ACTIVE

    for _ in range(12):
        o.feed_frame(80.0)

    assert o.get_vad_state() in (VADState.SPEECH_END, VADState.SILENCE)


def test_dual_gate_hysteresis_allows_continuation():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(40):
        o.feed_frame(150.0)

    assert o.feed_frame(380.0)
    assert o.feed_frame(240.0)


def test_noisy_room_calibration_fallback_completes():
    o = NoiseOrchestrator.create_fresh_for_testing()
    for _ in range(40):
        o.feed_frame(700.0)

    assert o.is_calibrated()
    assert o.get_environment() in (AcousticEnvironment.NOISY, AcousticEnvironment.VERY_NOISY)


def test_env_tuning_overrides_and_invalid_fallback(monkeypatch):
    monkeypatch.setenv("NOISE_CALIB_LOW_RMS_CUTOFF", "210")
    monkeypatch.setenv("NOISE_LOW_GATE_FACTOR", "0.80")
    monkeypatch.setenv("NOISE_SPEECH_OFF_CONFIRM_FRAMES", "5")
    tuned = NoiseOrchestrator.create_fresh_for_testing()

    assert tuned.calib_low_rms_cutoff == 210.0
    assert tuned.low_gate_factor == 0.80
    assert tuned.speech_off_confirm_frames == 5

    monkeypatch.setenv("NOISE_CALIB_LOW_RMS_CUTOFF", "bad")
    monkeypatch.setenv("NOISE_LOW_GATE_FACTOR", "bad")
    monkeypatch.setenv("NOISE_SPEECH_OFF_CONFIRM_FRAMES", "bad")
    fallback = NoiseOrchestrator.create_fresh_for_testing()

    assert fallback.calib_low_rms_cutoff == NoiseOrchestrator.CALIB_LOW_RMS_CUTOFF
    assert fallback.low_gate_factor == NoiseOrchestrator.LOW_GATE_FACTOR
    assert fallback.speech_off_confirm_frames == NoiseOrchestrator.SPEECH_OFF_CONFIRM_FRAMES

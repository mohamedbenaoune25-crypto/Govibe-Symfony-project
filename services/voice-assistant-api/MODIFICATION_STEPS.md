# Modification Steps Log

Date: 2026-04-14

1. Created a new isolated folder at project root:
   - `symfony_voice_agent/`

2. Copied the existing voice assistant agent without editing content:
   - Source: `src/main/resources/scripts/voice_agent.py`
   - Target: `symfony_voice_agent/agent`

3. Added integration documentation for Symfony usage:
   - File: `symfony_voice_agent/README.md`
   - Includes stdin/stdout protocol and Symfony Process example.

4. Preserved existing project code:
   - No modifications were made to Java, Python, FXML, DB, or config files in the original project tree.

5. Copied the full AI subsystem into a dedicated package tree:
   - Target root: `symfony_voice_agent/ai_system/`
   - Copied resource scripts folder: `src/main/resources/scripts/`
   - Copied AI view: `src/main/resources/AiAssistantForumView.fxml`
   - Copied Java assistant wiring: `src/main/java/org/example/assistant/`
   - Copied AI controller: `src/main/java/org/example/controllers/AiAssistantForumController.java` to `symfony_voice_agent/ai_system/src/main/java/org/example/controllers/AiAssistantForumController.java`
   - Copied assistant test suite: `src/test/java/org/example/assistant/`
   - Copied face-id scripts: `ai_scripts/`
   - Copied risk API module: `risk_api/`
   - Copied root AI model/helper files: `models.json`, `models_list.json`, `download_model.py`, `check_download.py`, `verify_vivian.py`, `download_progress.txt`

6. Updated package documentation to reflect full-system copy:
   - File updated: `symfony_voice_agent/README.md`
   - Added complete list of included AI components.

7. Extended the package with speech-engine/library modules for fuller portability:
   - Copied `jvosk-main/` to `symfony_voice_agent/ai_system/jvosk-main/`
   - Copied `java/lib/` to `symfony_voice_agent/ai_system/java-lib/`
   - Copied root `pom.xml` to `symfony_voice_agent/ai_system/pom.xml`

8. Added a Python replacement layer for Java assistant runtime components:
    - Created package folder: `symfony_voice_agent/ai_system/python_assistant/`
    - Added core modules:
       - `__init__.py`
       - `models.py` (AgentResponse data model)
       - `json_utils.py` (JSON parsing helpers)
       - `command_router.py` (keyword routing and command dispatch)
       - `noise_orchestrator.py` (adaptive noise environment logic)
       - `ollama_service.py` (local Ollama classification adapter)
       - `python_voice_agent.py` (Python subprocess bridge)
       - `voice_data_service.py` (db_context JSON builder)
       - `health.py` (system health checks)

9. Added Python unit test suite for migrated runtime behavior:
    - Test folder: `symfony_voice_agent/ai_system/python_assistant/tests/`
    - Added tests:
       - `test_agent_response_parser.py`
       - `test_command_router.py`
       - `test_noise_orchestrator.py`
       - `test_python_voice_agent_internals.py`
       - `test_ollama_service.py`
       - `test_voice_data_service.py`
    - Added test config: `pytest.ini`
    - Added package dependency file: `requirements.txt`

10. Added Symfony integration validation entrypoint:
      - Created `symfony_integration_check.py`
      - Validates Python runtime, agent script presence, pytest availability,
         python binary discovery, and Ollama reachability.

11. Executed validation commands:
      - Installed test dependency from `ai_system/python_assistant/requirements.txt`
      - Ran pytest suite in `ai_system/python_assistant/`:
         - Result: `17 passed`
      - Ran health check script:
         - `python_version`: OK
         - `agent_script_exists`: OK
         - `pytest_installed`: OK
         - `python_binary_present`: OK
         - `ollama_reachable`: FAIL (localhost:11434 not reachable)
         - Overall health: OK (Ollama treated as optional for core readiness)

12. Enforced absolute testing/safety rules with dedicated artifacts:
      - Added command execution safety module:
         - `ai_system/python_assistant/command_safety.py`
         - Includes allowlist/denylist policy, dry-run planning, destructive-intent
            confirmation flags, and argument sanitization against injection chars.
      - Added NLU regression evaluator:
         - `ai_system/python_assistant/nlu_regression.py`
      - Added standalone regression runner:
         - `ai_system/python_assistant/run_golden_regression.py`
      - Added golden phrase set:
         - `ai_system/python_assistant/golden_set/golden_phrases.json`
      - Added absolute-rule checklist and export template:
         - `ai_system/python_assistant/TESTING_CHECKLIST.md`
         - `ai_system/python_assistant/TEST_CASE_TEMPLATE.csv`

13. Added new automated tests for mandatory rule coverage:
      - `ai_system/python_assistant/tests/test_command_safety.py`
      - `ai_system/python_assistant/tests/test_nlu_regression.py`

14. Updated Python assistant package exports and docs:
      - Updated `ai_system/python_assistant/__init__.py` exports.
      - Updated `ai_system/python_assistant/README.md` with regression/checklist workflow.
      - Updated `ai_system/python_assistant/health.py` to include
         `golden_regression_accuracy` health gate.

15. Re-ran validation after absolute-rule additions:
      - Pytest:
         - Result: `23 passed`
      - Golden regression runner:
         - Result: `20/20` matched, accuracy `100.00%`
      - Health check:
         - `python_version`: OK
         - `agent_script_exists`: OK
         - `pytest_installed`: OK
         - `python_binary_present`: OK
         - `golden_regression_accuracy`: OK (20/20)
         - `ollama_reachable`: FAIL (localhost:11434 unreachable)
         - Overall: OK (Ollama optional)

16. Verified Ollama REST API status and improved diagnostics:
      - Confirmed direct REST check to `http://localhost:11434/api/tags` fails on this machine
         (remote server unreachable / timeout).
      - Confirmed `ollama` CLI is not available in PATH (`Get-Command ollama` -> not found),
         so local Ollama server cannot be started from this environment.
      - Updated `ai_system/python_assistant/ollama_service.py`:
         - `OLLAMA_BASE` is now configurable via `OLLAMA_BASE` environment variable.
         - Availability check now probes `/api/tags` first, then `/` as fallback.
         - Added `last_error` field to expose root-cause diagnostics.
      - Updated `ai_system/python_assistant/health.py`:
         - Health report now includes detailed Ollama error context.
      - Validation after patch:
         - Pytest: `23 passed`
         - Health check: `ollama_reachable` still FAIL with explicit timeout detail.

17. Wired Ollama runtime model handling and bootstrap flow:
      - Updated `ai_system/python_assistant/ollama_service.py`:
         - Added `OLLAMA_MODEL` environment-variable support (default `llama3.2`).
         - Added model discovery via `/api/tags`.
         - Added model resolution logic (configured model, `:latest` variant, fallback to first available).
         - Classification now uses resolved installed model name.
      - Updated `ai_system/python_assistant/health.py`:
         - Added `ollama_models_present` check.
         - Added `ollama_model_ready` check.
      - Added `ai_system/python_assistant/ollama_bootstrap.py`:
         - Verifies API reachability.
         - Streams `/api/pull` progress for configured model.
         - Re-validates tags/model readiness after pull.

18. Runtime wiring validation status:
      - `ollama_reachable`: OK (REST API responds on `http://localhost:11434`).
      - `ollama_models_present`: FAIL before pull (`models: []`).
      - Started bootstrap pull for `llama3.2` via REST; download is in progress in background terminal:
         - Terminal ID: `ad0e37fb-a4e8-4e7d-a07a-c6fdcab745ca`
         - Observed progress stream reached ~24% during this session.

19. Added mini/performance fallback preference for Ollama model selection:
      - Updated `ai_system/python_assistant/ollama_service.py` so model resolution now:
         - Prefers installed models whose names include `mini`, `small`, `compact`,
            `fast`, or `performance` when they exist.
         - Still honors an exact configured match when the configured model itself
            is already a mini-style model.
         - Falls back to the configured model, its `:latest` variant, or the first
            installed model if no mini-style model exists.
      - Added `OLLAMA_MODEL_HINTS` environment-variable support to override the
         preference keywords.
      - Added a regression test proving a smaller installed model wins over the
         default when available.
      - Validation after patch:
         - Pytest: `24 passed`
         - Health check: still reports no installed Ollama models because the local
            registry currently returns `{"models":[]}`.

20. Prepared project for Docker integration with Symfony:
      - Rebuilt `ai_system/python_assistant/app.py` to be runtime-safe with package imports
         and current module APIs.
      - Added `ai_system/python_assistant/Dockerfile` (Gunicorn-based API container).
      - Added `ai_system/python_assistant/.dockerignore`.
      - Added root `docker-compose.yml` with:
         - `ollama` service
         - `ollama-init` bootstrap service (pulls configured model)
         - `python-assistant` API service
      - Added root `.env.docker.example` with `OLLAMA_MODEL` setting.
      - Updated README files with Docker runbook and Symfony endpoint mapping.

21. Finalized Docker Compose reliability and validation:
      - Fixed `ollama-init` command variable expansion so model name is resolved
         inside the container (`$${OLLAMA_MODEL}`) and not emptied during compose parsing.
      - Removed obsolete `version` field from `docker-compose.yml`.
      - Validated compose file with `docker compose config` (clean output, no warnings).
      - Re-ran Python assistant tests after API/container wiring: `24 passed`.

22. Connected project to installed local Ollama runtime:
      - Verified Ollama CLI is available:
         - `ollama version is 0.20.7`
      - Verified REST API reachability:
         - `GET http://localhost:11434/api/tags` -> `200`
      - Installed a lightweight fallback model for immediate readiness:
         - Executed `ollama pull tinyllama`
         - Pull completed successfully.
      - Verified model registry contains:
         - `tinyllama:latest`
      - Re-ran integration health check:
         - `ollama_reachable`: OK
         - `ollama_models_present`: OK
         - `ollama_model_ready`: OK (configured `llama3.2`, resolved `tinyllama:latest`)
         - Overall: OK
      - Verified live generation path with `/api/generate` using `tinyllama`.

   23. Migrated package to fully Python-only runtime and removed Java/Maven artifacts:
         - Deleted Java-related directories/files:
            - `ai_system/java-lib/`
            - `ai_system/jvosk-main/`
            - `ai_system/pom.xml`
            - `ai_system/src/main/java/`
            - `ai_system/src/test/java/`
         - Verified no remaining `*.java` files and no `pom.xml` files in workspace.
         - Simplified `docker-compose.yml` to remove heavy Ollama container pulls and use
            host-installed Ollama via:
            - `OLLAMA_BASE=http://host.docker.internal:11434`
         - Added `extra_hosts` mapping for `host.docker.internal` in Python container.
         - Updated root and Python assistant README files to reflect Python-only architecture.
         - Validation after migration:
            - `docker compose config`: OK
            - `symfony_integration_check.py`: OVERALL=OK
            - Pytest: `24 passed`

   24. Executed live project run and fixed health endpoint pathing:
         - Started Flask API locally from package:
            - `python -m python_assistant.app`
         - Ran live endpoint checks:
            - `GET /api/health`
            - `POST /api/classify`
            - `POST /api/command`
         - Identified and fixed a runtime path issue in `app.py` health endpoint where
            the agent script path did not match the Python-only layout.
         - Updated health endpoint to resolve the first existing path from:
            - `ai_system/src/main/resources/scripts/voice_agent.py`
            - `src/main/resources/scripts/voice_agent.py`
            - `agent`
         - Re-ran live health check after patch:
            - All checks OK, overall `ok=true`.
         - Re-ran Python tests after patch:
            - `24 passed`.

   25. Resolved Docker runtime startup issue and validated containerized run:
         - Root cause of `docker compose up -d` failure:
            - Docker Desktop Linux engine pipe not available (`//./pipe/dockerDesktopLinuxEngine`).
         - Recovery action:
            - Started Docker Desktop executable (`C:\Program Files\Docker\Docker\Docker Desktop.exe`).
         - Re-ran `docker compose up -d --build` successfully.
         - Confirmed container is up and listening on port `5000`.

   26. Fixed container health false-negative for agent script path:
         - Updated `ai_system/python_assistant/app.py` health endpoint to accept
            `AGENT_SCRIPT_PATH` environment override and robust candidate selection.
         - Updated `docker-compose.yml`:
            - Added `AGENT_SCRIPT_PATH=/app/scripts/voice_agent.py`
            - Mounted `./ai_system/src/main/resources/scripts:/app/scripts:ro`
         - Rebuilt and restarted container.
         - Final container health check: all checks `OK`, overall `ok=true`.

   27. Fixed noisy-room over-filtering in noise orchestrator:
         - Root cause:
            - Voice gate threshold scaled too aggressively in loud environments,
              which could block real speech (`floor * multiplier`, especially in `VERY_NOISY`).
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Replaced multiplicative gate with additive adaptive gate:
              - `gate = max(min_gate, floor + max(120, floor * 0.35))`
            - Added short speech hangover support to avoid chopping words between frames:
              - `SPEECH_HANGOVER_FRAMES = 6`
              - `CONTINUATION_GATE_FACTOR = 0.8`
         - Added regression tests in `ai_system/python_assistant/tests/test_noise_orchestrator.py`:
            - `test_very_noisy_gate_not_over_aggressive`
            - `test_speech_hangover_keeps_short_followup_frame`
         - Validation:
            - Noise orchestrator tests: `6 passed`
            - Full Python assistant tests: `26 passed`

   28. Hardened noise orchestrator with stability and API clarity fixes:
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Simplified frame API to `feed_frame(rms: float)` (removed unused raw buffer args).
            - Initialized `noise_floor` with safe baseline `50.0` to reduce unstable startup behavior.
            - Improved calibration robustness:
               - Primary calibration uses low-RMS frames (`rms < 200`).
               - Added fallback calibration after bounded observation window
                 (`CALIB_MAX_OBSERVED_FRAMES`) using low-quartile estimation to avoid dead calibration.
            - Added environment-switch stability guard:
               - Requires repeated detection windows before committing environment change.
            - Added floor smoothing from rolling floor estimate (`FLOOR_SMOOTHING_ALPHA`).
            - Improved speech-state stability with hysteresis:
               - Maintains short continuity (`SPEECH_HANGOVER_FRAMES`).
               - Uses off-confirm frames (`SPEECH_OFF_CONFIRM_FRAMES`) before dropping speech state.
            - Improved settle/reset handling:
               - Added one-shot post-settle reset guard (`post_settle_reset_done`) to avoid stale discard-state behavior.
            - Improved SNR metric:
               - `snr = (mean_speech - noise_floor) / max(1.0, noise_floor)`.
               - Clears `speech_peaks` after each SNR validation window to avoid long-term drift.
            - Added thread safety with `threading.Lock()` around stateful operations.
         - Updated call site in `ai_system/python_assistant/app.py` to use new feed signature.
         - Added `speech_confidence` field in `/api/noise/frame` API response.
         - Updated and extended tests in `ai_system/python_assistant/tests/test_noise_orchestrator.py`.
         - Validation:
            - Full Python assistant tests: `27 passed`.

   29. Upgraded noise orchestrator toward production VAD behavior:
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Added explicit VAD state machine enum:
               - `SILENCE`, `SPEECH_START`, `SPEECH_ACTIVE`, `SPEECH_END`.
            - Replaced continuation-only gate logic with dual-threshold hysteresis:
               - High gate for speech entry.
               - Lower gate (`LOW_GATE_FACTOR`) for speech continuation.
            - Added `get_vad_state()` accessor for runtime observability.
            - Normalized `speech_confidence` to a stable [0..1] range using active gate ratio.
            - Fixed indentation/style in recalibration helper and ensured state resets include VAD state.
         - Updated `ai_system/python_assistant/app.py`:
            - `/api/noise/frame` now returns `vad_state` in response payload.
         - Extended `ai_system/python_assistant/tests/test_noise_orchestrator.py`:
            - Added `test_vad_state_transitions`.
            - Added `test_dual_gate_hysteresis_allows_continuation`.
         - Validation:
            - Full Python assistant tests: `29 passed`.

   30. Performed live/noisy tuning pass and fixed calibration/runtime reliability gaps:
         - Root issue found during API sweep:
            - Stateful noise calibration can be inconsistent with multi-worker process routing.
         - Updated `ai_system/python_assistant/Dockerfile`:
            - Switched Gunicorn workers from `2` to `1` for stateful VAD consistency.
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Added early noisy-room calibration fallback:
               - `CALIB_MIN_LOW_FRAMES = 10`
               - If observed frames reach `CALIB_FRAMES` with too few low-RMS samples,
                 calibrate using percentile fallback instead of waiting too long.
            - Tuned calibration low-RMS cutoff from `200` to `180` based on sweep behavior.
         - Extended tests:
            - Added `test_noisy_room_calibration_fallback_completes`.
         - Validation:
            - Full Python assistant tests: `30 passed`.

   31. Added environment-variable tuning for noise orchestrator (no code edits required):
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Added env-driven parameters with validation and clamping:
               - `NOISE_CALIB_LOW_RMS_CUTOFF` (float)
               - `NOISE_LOW_GATE_FACTOR` (float)
               - `NOISE_SPEECH_OFF_CONFIRM_FRAMES` (int)
            - Kept safe defaults when env vars are missing or invalid.
         - Updated `docker-compose.yml`:
            - Injected the three tuning env vars with defaults.
         - Updated `.env.docker.example`:
            - Added sample values for all three tuning knobs.
         - Updated `ai_system/python_assistant/README.md`:
            - Added section documenting noise tuning via environment variables.
         - Added test coverage in `ai_system/python_assistant/tests/test_noise_orchestrator.py`:
            - `test_env_tuning_overrides_and_invalid_fallback`.

   32. Exposed active noise tuning values in live frame API response:
         - Updated `ai_system/python_assistant/noise_orchestrator.py`:
            - Added `get_tuning_values()` thread-safe accessor.
         - Updated `ai_system/python_assistant/app.py`:
            - `/api/noise/frame` now includes:
               - `data.tuning.calib_low_rms_cutoff`
               - `data.tuning.low_gate_factor`
               - `data.tuning.speech_off_confirm_frames`

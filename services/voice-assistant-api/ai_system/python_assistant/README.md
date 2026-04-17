# Python Assistant Migration Layer

This package replaces the Java assistant core with Python modules suitable for Symfony integration.

## Included Modules

- `command_router.py`: voice intent keyword router with callback-based actions.
- `noise_orchestrator.py`: adaptive noise profiling and speech-gate logic.
- `python_voice_agent.py`: bridge to the existing `voice_agent.py` process.
- `ollama_service.py`: local Ollama fallback classifier.
- `voice_data_service.py`: DB snapshot payload builder.
- `health.py`: runtime/system-health report.
- `command_safety.py`: allowlist/denylist, dry-run and sanitization guardrails.
- `nlu_regression.py`: golden-set replay and intent accuracy calculation.

## Run Unit Tests

```powershell
cd ai_system/python_assistant
python -m pip install -r requirements.txt
python -m pytest
```

## Run Golden Regression

```powershell
cd ai_system/python_assistant
python run_golden_regression.py
```

## Run Health Check

```powershell
cd ai_system/python_assistant
python -m python_assistant.health
```

## Bootstrap Ollama Model

```powershell
cd ai_system/python_assistant
python ollama_bootstrap.py
```

This command connects to the local Ollama REST API, verifies model availability,
and pulls the configured model (`OLLAMA_MODEL`, default `llama3.2`) if missing.

## Symfony Integration (PHP)

Use the existing process-based protocol:

- Start the Python `voice_agent.py` process once per worker.
- Send line-delimited JSON requests (`{"text":"...","seq":1}`).
- Read line-delimited JSON responses and route by `seq`.

The `PythonVoiceAgentBridge` class in this package provides the same flow in Python and can be mirrored in Symfony service code.

## Docker Integration

From project root:

```powershell
copy .env.docker.example .env
docker compose up -d --build
```

Services started:

- `python-assistant` at `http://localhost:5000`

Required host service:

- Local Ollama daemon at `http://localhost:11434`

Symfony should call these endpoints on the Python container:

- `POST /api/classify` with `{ "text": "..." }`
- `POST /api/command` with `{ "user_input": "...", "is_dry_run": true }`
- `GET /api/health`

Inside Docker network (Symfony container -> assistant):

- Base URL: `http://python-assistant:5000`

Python assistant container -> host Ollama:

- `OLLAMA_BASE=http://host.docker.internal:11434`

### Noise Tuning Via Environment Variables

You can tune the noise orchestrator without editing code:

- `NOISE_CALIB_LOW_RMS_CUTOFF` (default: `180`)
- `NOISE_LOW_GATE_FACTOR` (default: `0.75`)
- `NOISE_SPEECH_OFF_CONFIRM_FRAMES` (default: `3`)

Example:

```powershell
$env:NOISE_CALIB_LOW_RMS_CUTOFF = "170"
$env:NOISE_LOW_GATE_FACTOR = "0.78"
$env:NOISE_SPEECH_OFF_CONFIRM_FRAMES = "4"
python -m python_assistant.app
```

## Mandatory Test Governance

- Checklist: `TESTING_CHECKLIST.md`
- Export template: `TEST_CASE_TEMPLATE.csv`
- Golden set: `golden_set/golden_phrases.json`

These files implement the absolute testing rules for STT, NLU, execution safety,
error handling, context, performance, response quality, and regression automation.

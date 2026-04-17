# Symfony AI System Package

This folder contains an isolated copy of your existing AI system (voice assistant + related services), prepared for Symfony integration without modifying the current project code.

## Included

- `agent`: exact copy of `src/main/resources/scripts/voice_agent.py`
- `ai_system/`: full copied AI subsystem with structure preserved
- `MODIFICATION_STEPS.md`: detailed change log of what was done

## AI Subsystem Tree (Python-Only)

- `ai_system/src/main/resources/scripts/` (voice agent scripts + assets)
- `ai_system/src/main/resources/AiAssistantForumView.fxml`
- `ai_system/ai_scripts/` (Face ID scripts)
- `ai_system/risk_api/` (risk scoring API + model)
- `ai_system/python_assistant/` (Python API + NLU + safety + health + tests)
- `ai_system/models.json`
- `ai_system/models_list.json`
- `ai_system/download_model.py`
- `ai_system/check_download.py`
- `ai_system/verify_vivian.py`
- `ai_system/download_progress.txt`

## Run Agent Manually

```bash
python agent
```

The process uses line-delimited JSON over stdin/stdout.

Input line example:

```json
{"text":"book a flight to Paris"}
```

Output line example:

```json
{"intent":"BOOK","response":"...","action":"BOOK","confidence":0.95}
```

## Symfony Integration (PHP Process)

Use Symfony Process to keep the Python agent alive and communicate through stdin/stdout.

```php
<?php

use Symfony\Component\Process\Process;

$agentPath = __DIR__ . '/symfony_voice_agent/agent';
$process = new Process(['python', $agentPath]);
$process->start();

// Wait for ready line from agent.
$ready = null;
foreach ($process as $type => $data) {
    $ready = trim($data);
    if ($ready !== '') {
        break;
    }
}

// Send one request.
$payload = json_encode(['text' => 'show me hotels']) . PHP_EOL;
$process->getInput()->write($payload);

// Read one response line.
$response = null;
foreach ($process as $type => $data) {
    $line = trim($data);
    if ($line !== '') {
        $response = $line;
        break;
    }
}

// Stop process when done.
$process->stop(2);
```

## Notes

- This is a direct copy, so behavior is unchanged.
- `ai_system/` preserves internal file relationships so the micro-kernel style wiring remains portable.
- Install Python dependencies in the Symfony environment before production use.
- Keep this folder independent and versioned with your Symfony app if needed.

## Python-First Migration Layer

The Java assistant runtime components now have Python equivalents under:

- `ai_system/python_assistant/`

This package includes Python modules for command routing, noise orchestration,
Ollama fallback classification, voice-agent bridge logic, db-context payload
building, and runtime health checks.

Quick validation:

```powershell
cd ai_system/python_assistant
python -m pip install -r requirements.txt
python -m pytest
python symfony_integration_check.py
```

Health output marks `ollama_reachable` as informational (optional). Core system
readiness remains valid when Ollama is offline.

### Absolute Testing Rules (Mandatory)

The absolute NLU testing and command-execution safety rules are implemented in:

- `ai_system/python_assistant/TESTING_CHECKLIST.md`
- `ai_system/python_assistant/TEST_CASE_TEMPLATE.csv`
- `ai_system/python_assistant/command_safety.py`
- `ai_system/python_assistant/golden_set/golden_phrases.json`

Run full validation:

```powershell
python -m pytest ai_system/python_assistant
python ai_system/python_assistant/run_golden_regression.py
python ai_system/python_assistant/symfony_integration_check.py
```

## Docker Compose Integration

This repository now includes a ready Docker stack at `docker-compose.yml` with:

- `python-assistant` (Flask + Gunicorn API)

Ollama runs on the host machine and is reached from container via `host.docker.internal`.

Start stack:

```powershell
copy .env.docker.example .env
docker compose up -d --build
```

Use from Symfony container:

- `http://python-assistant:5000/api/health`
- `http://python-assistant:5000/api/classify`
- `http://python-assistant:5000/api/command`

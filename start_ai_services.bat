@echo off
echo ========================================================
echo  GoVibe AI Platform Services Startup
echo ========================================================
echo.

echo [1/3] Starting Prediction API (Booking ^& Weather Predictions - Port 8001)...
start "GoVibe Prediction API (FastAPI)" cmd /c "cd services\prediction-api && if not exist venv (python -m venv venv) && call venv\Scripts\activate.bat && pip install -r requirements.txt -q && uvicorn api:app --reload --port 8001"

echo [2/3] Starting Auth Risk Scoring API (Port 5001)...
start "GoVibe Risk API" cmd /c "cd services\voice-assistant-api\ai_system\risk_api && if not exist venv (python -m venv venv) && call venv\Scripts\activate.bat && pip install -r requirements.txt -q && python app.py"

echo [3/3] Starting Desktop Voice Agent (Vosk / Edge TTS / Qwen TTS)...
start "GoVibe Voice Agent (Desktop)" cmd /c "cd services\voice-assistant-api\ai_system\src\main\resources\scripts && if not exist venv (python -m venv venv) && call venv\Scripts\activate.bat && pip install edge-tts vosk pyaudio sounddevice requests configparser beautifulsoup4 -q && python voice_agent.py"

echo.
echo All AI services and the desktop voice agent have been launched in separate windows!
echo - FastAPI Predictions: http://localhost:8001
echo - Risk API: http://localhost:5001
echo - Voice Agent: Listening to your microphone...
echo.
pause

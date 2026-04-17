@echo off
REM AI Service Startup Script for Windows

setlocal enabledelayedexpansion

echo ==================================
echo AI Model Inference Service
echo ==================================

REM Set working directory to script location
cd /d "%~dp0"

REM Check if virtual environment exists
if not exist "venv" (
    echo Creating Python virtual environment...
    python -m venv venv
)

REM Activate virtual environment
echo Activating virtual environment...
call venv\Scripts\activate.bat

REM Install dependencies
echo Installing dependencies...
python -m pip install -q --upgrade pip
pip install -q -r requirements.txt

REM Start API server
echo Starting AI Service...
echo Server will run at: http://127.0.0.1:8000
echo API Docs: http://127.0.0.1:8000/docs
echo Press Ctrl+C to stop
echo.

python api.py

pause

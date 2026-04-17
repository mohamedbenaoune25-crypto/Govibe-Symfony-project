@echo off
echo ======================================
echo  GoVibe AI Risk Scoring API
echo ======================================
echo.

REM Check if venv exists, create if not
if not exist "venv" (
    echo Creating virtual environment...
    python -m venv venv
)

REM Activate venv
call venv\Scripts\activate.bat

REM Install dependencies
echo Installing dependencies...
pip install -r requirements.txt -q

REM Start the API
echo.
echo Starting Risk Scoring API on http://localhost:5001 ...
python app.py

pause

#!/bin/bash
# AI Service Startup Script for Unix/Linux/Mac

echo "=================================="
echo "AI Model Inference Service"
echo "=================================="

# Set working directory
cd "$(dirname "$0")" || exit

# Check if virtual environment exists
if [ ! -d "venv" ]; then
    echo "Creating Python virtual environment..."
    python3 -m venv venv
fi

# Activate virtual environment
echo "Activating virtual environment..."
source venv/bin/activate

# Install dependencies
echo "Installing dependencies..."
pip install -q --upgrade pip
pip install -q -r requirements.txt

# Start API server
echo "Starting AI Service..."
echo "Server will run at: http://127.0.0.1:8000"
echo "API Docs: http://127.0.0.1:8000/docs"
echo "Press Ctrl+C to stop"

python api.py

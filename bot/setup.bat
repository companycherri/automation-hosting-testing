@echo off
:: Bot Setup Script for Windows Command Prompt (cmd)
:: Run: setup.bat

echo === Barcode Bot Setup ===

echo [1/5] Creating virtual environment...
python -m venv venv
if errorlevel 1 (echo ERROR: Python not found && exit /b 1)

echo [2/5] Activating virtual environment...
call venv\Scripts\activate.bat

echo [3/5] Installing packages...
pip install -r requirements.txt

echo [4/5] Installing Playwright Chromium...
playwright install chromium

echo [5/5] Creating downloads folder...
if not exist downloads mkdir downloads

echo.
echo Setup complete! Run:  python bot.py
pause

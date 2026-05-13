# Bot Setup Script for Windows PowerShell
# Run this once inside the bot/ folder:
#   cd bot
#   .\setup.ps1

Write-Host "=== Barcode Bot Setup ===" -ForegroundColor Cyan

# Step 1: Create virtual environment
Write-Host "`n[1/5] Creating Python virtual environment..." -ForegroundColor Yellow
python -m venv venv
if (-not $?) { Write-Host "ERROR: python not found. Install Python 3.11+ and add to PATH." -ForegroundColor Red; exit 1 }

# Step 2: Activate venv
Write-Host "[2/5] Activating virtual environment..." -ForegroundColor Yellow
& .\venv\Scripts\Activate.ps1

# Step 3: Install pip packages
Write-Host "[3/5] Installing Python packages..." -ForegroundColor Yellow
pip install -r requirements.txt

# Step 4: Install Playwright browser
Write-Host "[4/5] Installing Chromium browser for Playwright..." -ForegroundColor Yellow
playwright install chromium

# Step 5: Create downloads folder
Write-Host "[5/5] Creating downloads folder..." -ForegroundColor Yellow
if (-not (Test-Path "downloads")) { New-Item -ItemType Directory -Name "downloads" }

Write-Host "`n✅ Setup complete!" -ForegroundColor Green
Write-Host "To start the bot, run:" -ForegroundColor Cyan
Write-Host "  python bot.py" -ForegroundColor White

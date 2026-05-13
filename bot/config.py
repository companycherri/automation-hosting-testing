# ============================================================
# Bot Configuration
# All values read from environment variables via os.getenv().
# Safe defaults allow the bot to run locally (XAMPP) with no
# env vars set, and in Docker with .env-supplied values.
#
# Variable names match .env.example exactly.
# ============================================================

import os

# ── API ────────────────────────────────────────────────────
# Full base URL of the PHP backend API.
# Docker  : http://nginx/api   (nginx proxies to php container)
# XAMPP   : http://localhost/mini-automation/backend/api
API_BASE_URL = os.getenv(
    "API_BASE_URL",
    "http://localhost/mini-automation/backend/api"
)

# ── Polling ────────────────────────────────────────────────
# Seconds between job queue polls.
POLL_INTERVAL = int(os.getenv("BOT_POLL_INTERVAL", os.getenv("POLL_INTERVAL", "5")))

# ── Browser ────────────────────────────────────────────────
# HEADLESS=true  → invisible browser (required on VPS/Docker)
# HEADLESS=false → visible browser window (local debugging)
HEADLESS = os.getenv("HEADLESS", "false").lower() in ("true", "1", "yes")

# Milliseconds between Playwright actions.
# 0 = full speed (production)   500 = watchable (debugging)
SLOW_MO = int(os.getenv("SLOW_MO", "500"))

# ── Debug ──────────────────────────────────────────────────
# true = capture screenshot after every single field fill
# false = only capture on errors (default, less disk usage)
DEBUG_SCREENSHOTS = os.getenv("DEBUG_SCREENSHOTS", "false").lower() in ("true", "1", "yes")

# ── File paths ─────────────────────────────────────────────
# Docker  : /app/downloads  /app/screenshots  /app/logs
#           (mounted as Docker volumes)
# XAMPP   : relative paths inside bot/ directory
DOWNLOAD_DIR   = os.getenv("DOWNLOAD_PATH",   "downloads")
SCREENSHOT_DIR = os.getenv("SCREENSHOT_PATH", "screenshots")
LOG_DIR        = os.getenv("LOG_PATH",        "logs")

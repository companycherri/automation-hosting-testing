# Bot configuration
# All values read from environment variables so the same code
# works in local XAMPP dev (no env vars → defaults) and Docker.

import os

API_BASE_URL   = os.getenv("API_BASE_URL",  "http://localhost/mini-automation/backend/api")
POLL_INTERVAL  = int(os.getenv("POLL_INTERVAL",  "5"))
DOWNLOAD_DIR   = os.getenv("DOWNLOAD_DIR",  "downloads")
SCREENSHOT_DIR = os.getenv("SCREENSHOT_DIR","screenshots")
HEADLESS       = os.getenv("HEADLESS",      "false").lower() == "true"
SLOW_MO        = int(os.getenv("SLOW_MO",   "500"))

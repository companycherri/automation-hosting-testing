# ============================================================
# Bot Dockerfile — Python 3.11 + Playwright + Chromium
# Runs headless browser automation continuously.
#
# Layer order is optimised for Docker cache:
#   1. Base image + minimal system tools
#   2. requirements.txt → pip install          ← cached unless requirements change
#   3. Playwright browser + OS deps download   ← cached unless playwright version changes
#   4. COPY source files                       ← cache busts only on code changes
# ============================================================

FROM --platform=linux/amd64 python:3.11-slim

# ── Minimal tools needed before pip / playwright ──────────
# wget + ca-certificates are required for downloading Chromium.
# curl is used by the healthcheck.
RUN apt-get update && apt-get install -y --no-install-recommends \
        wget \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# ── Python dependencies ────────────────────────────────────
# Copy requirements FIRST so this layer is cached separately
# from source code. Pip + Playwright only re-install when
# requirements.txt changes.
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# ── Playwright browser + all OS dependencies ───────────────
# Store browsers in a fixed path accessible to all users.
# "playwright install-deps chromium" uses apt to install every
# system library Chromium needs (fonts, glib, nss, etc.) —
# more reliable than maintaining a manual apt list.
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN apt-get update \
    && playwright install chromium \
    && playwright install-deps chromium \
    && rm -rf /var/lib/apt/lists/* \
    && chmod -R 755 /ms-playwright

# ── Copy bot source ────────────────────────────────────────
# This is intentionally LAST — source changes are frequent.
# The heavy layers above (pip, Chromium) stay cached.
# bot/.dockerignore ensures venv/, __pycache__/, logs/,
# screenshots/, downloads/ are NOT sent in the build context.
COPY . .

# ── Runtime directories (overridden by Docker volumes) ─────
RUN mkdir -p screenshots downloads logs temp

# ── Non-root user for security ────────────────────────────
RUN groupadd -r botuser && useradd -r -g botuser -u 1001 botuser \
    && chown -R botuser:botuser /app

USER botuser

# ── Health check ───────────────────────────────────────────
HEALTHCHECK --interval=60s --timeout=15s --start-period=60s --retries=3 \
  CMD python -c "import requests; requests.get('http://nginx/api/jobs.php', timeout=5)" || exit 1

CMD ["python", "-u", "bot.py"]

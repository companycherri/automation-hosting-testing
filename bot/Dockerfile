# ============================================================
# Bot Dockerfile — Python 3.11 + Playwright + Chromium
# Runs headless browser automation continuously
# ============================================================

FROM --platform=linux/amd64 python:3.11-slim

# ── Playwright/Chromium system dependencies ────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        # Chromium core libraries
        libnss3 \
        libnspr4 \
        libatk1.0-0 \
        libatk-bridge2.0-0 \
        libcups2 \
        libdrm2 \
        libdbus-1-3 \
        libxcb1 \
        libxkbcommon0 \
        libx11-6 \
        libxcomposite1 \
        libxdamage1 \
        libxext6 \
        libxfixes3 \
        libxrandr2 \
        libgbm1 \
        libpango-1.0-0 \
        libcairo2 \
        libasound2 \
        libatspi2.0-0 \
        libglib2.0-0 \
        # Font rendering
        fontconfig \
        fonts-liberation \
        fonts-noto-color-emoji \
        # File handling
        wget \
        curl \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# ── Python dependencies ────────────────────────────────────
COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# ── Install Playwright browsers (as root, before user switch)
# Store browsers in a predictable location accessible to all users
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN playwright install chromium \
    && playwright install-deps chromium \
    && chmod -R 777 /ms-playwright

# ── Copy bot source ────────────────────────────────────────
COPY . .

# ── Runtime directories (will be overridden by volumes) ───
RUN mkdir -p screenshots downloads logs temp

# ── Non-root user for security ────────────────────────────
RUN groupadd -r botuser && useradd -r -g botuser -u 1001 botuser \
    && chown -R botuser:botuser /app

USER botuser

# ── Health / meta ──────────────────────────────────────────
HEALTHCHECK --interval=60s --timeout=15s --start-period=30s --retries=3 \
  CMD python -c "import requests; requests.get('http://nginx/api/jobs.php', timeout=5)" || exit 1

CMD ["python", "-u", "bot.py"]

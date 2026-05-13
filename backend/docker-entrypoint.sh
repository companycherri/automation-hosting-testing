#!/bin/bash
# ============================================================
# Backend container entrypoint
# 1. Waits for MySQL to be ready
# 2. Runs PHP schema migration (update-schema.php)
# 3. Remaps portal URLs for Docker internal networking
# 4. Starts Apache
# ============================================================

set -e

# All values come from .env via docker-compose env_file + environment overrides
DB_HOST="${DB_HOST:-mysql}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-barcode_portal}"
DB_USER="${DB_USER:-barcode_user}"
DB_PASSWORD="${DB_PASSWORD:-}"
APP_NAME="${APP_NAME:-Barcode Portal}"
APP_URL="${APP_URL:-http://localhost}"
DEBUG_MODE="${DEBUG_MODE:-false}"

echo "═══════════════════════════════════════════════════════"
echo "  ${APP_NAME} — Backend Starting"
echo "  DB  : ${DB_USER}@${DB_HOST}:${DB_PORT}/${DB_NAME}"
echo "  URL : ${APP_URL}"
echo "  DEBUG: ${DEBUG_MODE}"
echo "═══════════════════════════════════════════════════════"

# ── Wait for MySQL ─────────────────────────────────────────
echo "⏳ Waiting for MySQL at ${DB_HOST}..."
MAX_TRIES=30
TRIES=0
until mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" -e "SELECT 1" > /dev/null 2>&1; do
    TRIES=$((TRIES + 1))
    if [ $TRIES -ge $MAX_TRIES ]; then
        echo "❌ MySQL did not become available after ${MAX_TRIES} attempts. Exiting."
        exit 1
    fi
    echo "   Attempt ${TRIES}/${MAX_TRIES} — retrying in 3s..."
    sleep 3
done
echo "✅ MySQL is ready."

# ── Run PHP migrations ─────────────────────────────────────
echo "🔧 Running schema migrations..."
php /var/www/html/update-schema.php && echo "✅ Schema up to date." || echo "⚠  Migration had warnings (check above)."

# ── Remap portal URLs for Docker networking ────────────────
# In Docker, the portals are accessed via the nginx service name.
# Original URLs point to localhost/mini-automation — remap them.
echo "🔗 Remapping portal URLs for Docker networking..."
mysql -h"${DB_HOST}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" <<-EOSQL
    UPDATE companies
    SET
        portal_url = REPLACE(portal_url, 'http://localhost/mini-automation', 'http://nginx'),
        login_url  = REPLACE(login_url,  'http://localhost/mini-automation', 'http://nginx')
    WHERE portal_url LIKE '%localhost/mini-automation%'
       OR login_url  LIKE '%localhost/mini-automation%';
EOSQL
echo "✅ Portal URLs remapped."

# ── Fix permissions on mounted volumes ────────────────────
chown -R www-data:www-data /var/www/html/uploads \
                            /var/www/html/bot \
                            /var/www/html/logs 2>/dev/null || true

echo ""
echo "🚀 Starting Apache..."
echo "═══════════════════════════════════════════════════════"

# Execute the CMD (apache2-foreground)
exec "$@"

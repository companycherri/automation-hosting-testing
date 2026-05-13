# ============================================================
# Backend Dockerfile — PHP 8.2 + Apache
# Serves: /api/ · /portals/ · /uploads/ · /bot/
# ============================================================

FROM php:8.2-apache

# ── System dependencies ────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libgd-dev \
        libcurl4-openssl-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        zip \
        unzip \
        curl \
        default-mysql-client \
    && docker-php-ext-configure gd \
          --with-freetype \
          --with-jpeg \
    && docker-php-ext-install \
          pdo_mysql \
          mysqli \
          mbstring \
          zip \
          gd \
          curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# ── PHP production settings ────────────────────────────────
RUN { \
        echo "upload_max_filesize = 50M"; \
        echo "post_max_size = 50M"; \
        echo "memory_limit = 256M"; \
        echo "max_execution_time = 120"; \
        echo "max_input_time = 120"; \
        echo "expose_php = Off"; \
        echo "display_errors = Off"; \
        echo "log_errors = On"; \
        echo "error_log = /var/log/php_errors.log"; \
    } > /usr/local/etc/php/conf.d/production.ini

# ── Apache virtual host ────────────────────────────────────
RUN { \
        echo "<VirtualHost *:80>"; \
        echo "    DocumentRoot /var/www/html"; \
        echo "    <Directory /var/www/html>"; \
        echo "        AllowOverride All"; \
        echo "        Require all granted"; \
        echo "        Options -Indexes +FollowSymLinks"; \
        echo "    </Directory>"; \
        echo "    # Deny access to config files"; \
        echo "    <FilesMatch \"\\.php$\">"; \
        echo "        SetHandler application/x-httpd-php"; \
        echo "    </FilesMatch>"; \
        echo "    ServerTokens Prod"; \
        echo "    ServerSignature Off"; \
        echo "</VirtualHost>"; \
    } > /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# ── Copy application code ─────────────────────────────────
# Build context is project root (docker-compose context: .)
COPY backend/api/       ./api/
COPY backend/config/    ./config/
COPY backend/utils/     ./utils/
COPY backend/database.sql     ./database.sql
COPY backend/init.php         ./init.php
COPY backend/update-schema.php ./update-schema.php
COPY portals/           ./portals/
COPY dummy-portal/      ./dummy-portal/

# ── Directory structure for file storage ──────────────────
RUN mkdir -p \
        uploads/job-files \
        bot/screenshots \
        bot/downloads \
        logs \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 \
        /var/www/html/uploads \
        /var/www/html/bot \
        /var/www/html/logs

# ── Entrypoint: wait for MySQL, run migrations ────────────
COPY backend/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

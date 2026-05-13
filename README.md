# 🏷️ Barcode Portal Automation System

A full-stack web automation system that:
- Accepts Excel job imports via a React dashboard
- Queues jobs in MySQL
- Runs a Playwright bot that automates portal submissions
- Captures portal errors with screenshots and structured error tracking
- Serves barcode files for download after successful submission

---

## 📦 Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | React 19 + Tailwind CSS |
| Backend API | PHP 8.2 + Apache |
| Database | MySQL 8 |
| Automation Bot | Python 3.11 + Playwright (Chromium) |
| Reverse Proxy | Nginx 1.25 |
| Container Runtime | Docker + Docker Compose |

---

## 🚀 Production Deployment (VPS)

### STEP 1 — Install Docker on Ubuntu VPS

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Docker
curl -fsSL https://get.docker.com | sudo sh

# Add your user to docker group (no sudo needed)
sudo usermod -aG docker $USER
newgrp docker

# Install Docker Compose plugin
sudo apt install -y docker-compose-plugin

# Verify
docker --version
docker compose version
```

---

### STEP 2 — Clone the Project

```bash
git clone https://github.com/YOUR_USERNAME/mini-automation.git
cd mini-automation
```

---

### STEP 3 — Configure Environment

```bash
cp .env.example .env
nano .env
```

Fill in your values:

```env
MYSQL_ROOT_PASSWORD=your_strong_root_password
MYSQL_DATABASE=barcode_portal
MYSQL_USER=barcode_user
MYSQL_PASSWORD=your_strong_db_password

APP_URL=https://yourdomain.com      # or http://YOUR_VPS_IP

BOT_POLL_INTERVAL=5
```

> ⚠️ **Never commit `.env` to git.** It contains passwords.

---

### STEP 4 — Build and Start All Containers

```bash
docker compose up -d --build
```

This single command:
1. Builds all 4 custom Docker images (frontend, backend, bot)
2. Pulls MySQL and Nginx images
3. Creates persistent volumes for data
4. Starts all 5 containers
5. Backend automatically runs schema migrations on startup
6. Bot starts polling for jobs immediately

**First build takes ~5–10 minutes** (downloads Node, PHP, Python, Chromium).
Subsequent builds are much faster due to Docker layer caching.

---

### STEP 5 — Check Containers

```bash
docker ps
```

Expected output:
```
CONTAINER ID   IMAGE                      STATUS          NAMES
xxxxxxxxxxxx   nginx:1.25-alpine          Up 2 minutes    barcode_nginx
xxxxxxxxxxxx   mini-automation-backend    Up 2 minutes    barcode_backend
xxxxxxxxxxxx   mini-automation-frontend   Up 2 minutes    barcode_frontend
xxxxxxxxxxxx   mini-automation-bot        Up 2 minutes    barcode_bot
xxxxxxxxxxxx   mysql:8.0                  Up 2 minutes    barcode_mysql
```

All should show `Up` — not `Restarting` or `Exited`.

---

### STEP 6 — View Logs

```bash
# All containers (live)
docker compose logs -f

# Specific container
docker compose logs -f python-bot
docker compose logs -f backend
docker compose logs -f mysql

# Bot log file
docker exec barcode_bot tail -100 /app/logs/bot.log
```

---

### STEP 7 — Restart Containers

```bash
# Restart all
docker compose restart

# Restart specific
docker compose restart python-bot
docker compose restart nginx
```

---

### STEP 8 — Update Project

```bash
git pull
docker compose up -d --build
```

Existing volumes (database, uploads, screenshots) are preserved.

---

## 🌐 Access the Application

| URL | Description |
|-----|-------------|
| `http://YOUR_IP/` | React dashboard |
| `http://YOUR_IP/api/jobs.php` | API health check |
| `http://YOUR_IP/portals/company-a/login.php` | Test portal A |

**Default login:** `admin@portal.com` / `admin123`

> Change the admin password after first login!

---

## 🔒 SSL / HTTPS (Recommended for Production)

```bash
# Install Certbot
sudo apt install -y certbot

# Stop nginx temporarily
docker compose stop nginx

# Get SSL certificate (free, auto-renews)
sudo certbot certonly --standalone -d yourdomain.com

# Copy certs to project
sudo cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem nginx/ssl/
sudo cp /etc/letsencrypt/live/yourdomain.com/privkey.pem  nginx/ssl/
sudo chown $USER:$USER nginx/ssl/*.pem

# Start nginx again
docker compose start nginx
```

Then uncomment the HTTPS server block in `nginx/default.conf` and:
```bash
docker compose restart nginx
```

**Auto-renewal cron** (runs at 3am daily):
```bash
crontab -e
# Add:
0 3 * * * cd /home/user/mini-automation && docker compose stop nginx && certbot renew --quiet && docker compose start nginx
```

---

## 📁 File Storage & Volumes

All files survive container restarts via Docker named volumes:

| Data | Volume | Bot path | Web path |
|------|--------|---------|---------|
| Excel uploads | `uploads_data` | `/app/uploads` | `/uploads/` |
| Bot screenshots | `screenshots_data` | `/app/screenshots` | `/bot/screenshots/` |
| Barcode downloads | `downloads_data` | `/app/downloads` | `/bot/downloads/` |
| Bot log files | `bot_logs` | `/app/logs` | — |
| MySQL data | `mysql_data` | — | — |

**How uploads work:**
1. User uploads Excel + supporting files via React dashboard
2. PHP stores files in `/var/www/html/uploads/` (= `uploads_data` volume)
3. Bot reads files from `/app/uploads/` (same volume, different mount point)
4. Files persist even if containers are rebuilt

**Where screenshots are stored:**
- Bot saves to `/app/screenshots/JOB0001_ERR_part_no_....png`
- Backend serves them at `http://YOUR_IP/bot/screenshots/JOB0001_....png`
- Visible in the Job Detail page and Bot Errors page

**Where downloads are stored:**
- Bot saves barcode files to `/app/downloads/`
- Backend serves them via `/api/download-file.php?id=123`
- Visible in the Downloads page

---

## 🤖 How the Bot Runs

The Python bot runs **continuously inside its container**:

1. Polls `GET /api/pending-job.php` every N seconds (`BOT_POLL_INTERVAL`)
2. When a job is found → locks it (status: `processing`)
3. Launches **headless Chromium** (no GUI — runs silently in Docker)
4. Navigates to company portal login URL
5. Fills each form field with the **exact Excel value** (no auto-correction)
6. After each field: checks for portal validation errors
7. If portal rejects a value → captures screenshot, saves structured error, marks job `failed`
8. If form submits → downloads barcode file, marks job `success`
9. Returns to polling loop

**Bot auto-restarts** if it crashes (`restart: unless-stopped`).

**Watch bot live:**
```bash
docker compose logs -f python-bot
```

---

## 🗄️ Database Migrations

Run schema migrations manually (also runs automatically on container start):
```bash
docker exec barcode_backend php /var/www/html/update-schema.php
```

Direct MySQL access:
```bash
docker exec -it barcode_mysql mysql -u barcode_user -p barcode_portal
```

Backup database:
```bash
docker exec barcode_mysql mysqldump \
  -u root -p${MYSQL_ROOT_PASSWORD} barcode_portal \
  > backup_$(date +%Y%m%d_%H%M%S).sql
```

---

## ⚙️ Adding a Real Company Portal

1. Add the company in the database:

```sql
INSERT INTO companies (company_name, portal_url, login_url, username, password, status)
VALUES (
  'Your Company Name',
  'https://portal.real-company.com/',
  'https://portal.real-company.com/login',
  'your_portal_username',
  'your_portal_password',
  'active'
);
```

2. Add field mappings for each form field the bot needs to fill
3. Set `portal_type` to match the portal style (`simple` / `multistep` / `modal`)

For the included **test portals** (Company A, B, C), the entrypoint script
automatically remaps their URLs to `http://nginx/portals/...` on first start.

---

## 🔧 Scaling Bot Workers

Run multiple bot instances when job volume is high:

```bash
docker compose up -d --scale python-bot=3
```

Each bot instance polls independently and jobs are claimed atomically,
so there is no double-processing.

---

## 🛠️ Troubleshooting

**Bot keeps restarting:**
```bash
docker compose logs python-bot
# Usually: backend not ready yet, or Playwright browser missing
```

**MySQL slow to start (first boot):**
```bash
docker compose logs mysql
# Wait for: "ready for connections" — first boot takes 30–60s
```

**File uploads failing:**
```bash
docker exec barcode_backend ls -la /var/www/html/uploads/
docker exec barcode_backend chown -R www-data:www-data /var/www/html/uploads
```

**Reset all failed jobs:**
```bash
curl -X POST http://YOUR_IP/api/reset-failed-jobs.php
```

**Full wipe and restart (loses all data):**
```bash
docker compose down -v && docker compose up -d --build
```

---

## 📂 Project Structure

```
mini-automation/
├── docker-compose.yml          # All 5 services defined here
├── .env                        # Your secrets (never commit this)
├── .env.example                # Template — commit this
├── .dockerignore
│
├── frontend/                   # React 19 + Tailwind
│   ├── Dockerfile              # Node build → Nginx serve (multi-stage)
│   ├── nginx.conf              # React Router SPA config
│   └── src/
│       ├── api/api.js          # Uses REACT_APP_API_URL env var
│       └── pages/              # Dashboard, Jobs, Upload, Errors...
│
├── backend/                    # PHP 8.2 API + Apache
│   ├── Dockerfile              # php:8.2-apache + pdo_mysql + gd + zip
│   ├── docker-entrypoint.sh    # Waits for MySQL, runs migrations
│   ├── config/
│   │   ├── db.php              # Reads DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
│   │   └── cors.php            # Reads APP_URL for CORS origin
│   └── api/                    # ~20 REST endpoints
│
├── bot/                        # Python 3.11 + Playwright
│   ├── Dockerfile              # + Chromium + all Linux deps
│   ├── bot.py                  # Main polling + automation loop
│   ├── db.py                   # HTTP client to PHP API
│   └── config.py               # Reads API_BASE_URL, HEADLESS, SLOW_MO
│
├── portals/                    # Dummy PHP portals for testing
│   ├── company-a/              # Simple form + quantity validation
│   ├── company-b/              # Searchable dropdowns + AJAX
│   └── company-c/              # Multi-step + React dropdowns
│
└── nginx/
    ├── default.conf            # Routes: / → frontend, /api/ → backend
    └── ssl/                    # Put SSL certs here (gitignored)
```

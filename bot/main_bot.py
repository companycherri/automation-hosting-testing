"""
main_bot.py — Multi-Company Bot Orchestrator
Polls for pending jobs and routes them to the correct company bot.
Extends the original bot.py to support all 3 portals.

Job routing is based on job["company_name"] or job["company_id"].
"""

import os
import sys
import time
import logging
import traceback
from pathlib import Path
from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

# Add parent dir so we can import db and config
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import db
from config import POLL_INTERVAL, HEADLESS, SLOW_MO

from bots.company_a_bot import CompanyABot
from bots.company_b_bot import CompanyBBot
from bots.company_c_bot import CompanyCBot

# ── Logging ────────────────────────────────────────────────
LOG_DIR = os.path.join(os.path.dirname(__file__), 'logs')
Path(LOG_DIR).mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s  %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(os.path.join(LOG_DIR, "main_bot.log"), encoding="utf-8"),
    ],
)
log = logging.getLogger(__name__)

# ── Company → Bot mapping ──────────────────────────────────
COMPANY_BOTS = {
    "Company A":            CompanyABot,
    "company_a":            CompanyABot,
    "Toyota Industries":    CompanyABot,   # existing DB companies use Company A portal

    "Company B":            CompanyBBot,
    "company_b":            CompanyBBot,
    "Honda Logistics":      CompanyBBot,

    "Company C":            CompanyBBot,
    "company_c":            CompanyCBot,
    "Suzuki Parts Co.":     CompanyABot,   # default to A for existing companies
}

DEFAULT_BOT = CompanyABot


def get_bot_class(company_name: str):
    """Return the correct bot class for a company name."""
    for key, cls in COMPANY_BOTS.items():
        if key.lower() in (company_name or "").lower():
            return cls
    log.warning("  ⚠  Unknown company '%s' → using default (Company A)", company_name)
    return DEFAULT_BOT


def run_job(playwright, job_data: dict) -> None:
    """Run a single job using the appropriate company bot."""
    job     = job_data["job"]
    company = job_data.get("company") or {}
    job_id  = int(job["id"])

    company_name = job.get("company_name", company.get("name", "Unknown"))
    bot_class    = get_bot_class(company_name)

    log.info("━" * 60)
    log.info("JOB-%04d | %s | Bot=%s | part=%s | qty=%s",
             job_id, company_name, bot_class.__name__,
             job["part_no"], job["quantity"])
    log.info("━" * 60)
    db.add_log(job_id, "BOT_START", f"Bot picked up JOB-{job_id:04d} → {bot_class.__name__}")

    browser = playwright.chromium.launch(headless=HEADLESS, slow_mo=SLOW_MO)
    context = browser.new_context(accept_downloads=True)
    page    = context.new_page()
    page.set_default_timeout(20_000)

    try:
        bot = bot_class(page, job_id)

        # Build job dict from DB fields
        job_fields = {
            "part_no":       job.get("part_no", ""),
            "quantity":      job.get("quantity", 1),
            "batch_no":      job.get("batch_no", ""),
            "vendor_code":   job.get("vendor_code", ""),
            "priority":      job.get("priority", "normal"),
            "delivery_date": job.get("delivery_date", ""),
            "notes":         job.get("notes", ""),
            "category":      job.get("category", "AUTO"),
            "vendor_codes":  [job.get("vendor_code", "VND-C-001")],
        }

        # Use company credentials from DB if provided
        if company.get("login_url"):
            log.info("  ℹ  Company has custom login_url, using original bot.py flow")

        file_path = bot.run(job_fields)

        rel_path = f"bot/downloads/{os.path.basename(file_path)}".replace("\\", "/")
        db.update_job_status(job_id, "success", file_path=rel_path)
        db.add_log(job_id, "JOB_SUCCESS", f"JOB-{job_id:04d} completed ✓")
        log.info("  ✅ JOB-%04d SUCCESS", job_id)

    except PWTimeout as exc:
        msg = f"Timeout — {exc}"
        log.error("  ❌ %s", msg)
        db.update_job_status(job_id, "failed", error_message=msg)
        db.add_log(job_id, "JOB_FAILED", msg)
        raise

    except Exception as exc:
        msg = str(exc)
        log.error("  ❌ %s\n%s", msg, traceback.format_exc())
        db.update_job_status(job_id, "failed", error_message=msg)
        db.add_log(job_id, "JOB_FAILED", msg)
        raise

    finally:
        context.close()
        browser.close()


def main():
    log.info("🤖 Multi-Company Bot | headless=%s | slow_mo=%s | poll=%ss",
             HEADLESS, SLOW_MO, POLL_INTERVAL)
    log.info("   Registered bots: A=%s, B=%s, C=%s",
             CompanyABot.__name__, CompanyBBot.__name__, CompanyCBot.__name__)

    with sync_playwright() as pw:
        while True:
            try:
                job_data = db.get_pending_job()
            except Exception as exc:
                log.error("API error: %s", exc)
                time.sleep(POLL_INTERVAL)
                continue

            if job_data is None:
                log.debug("no pending jobs — sleeping %ds", POLL_INTERVAL)
                time.sleep(POLL_INTERVAL)
                continue

            job_id = int(job_data["job"]["id"])
            log.info("📥 JOB-%04d picked up", job_id)

            for attempt in range(1, 3):
                try:
                    run_job(pw, job_data)
                    break
                except Exception:
                    if attempt == 1:
                        log.warning("  retrying JOB-%04d in 4s…", job_id)
                        db.update_job_status(job_id, "processing")
                        time.sleep(4)
                    else:
                        log.error("  JOB-%04d failed after 2 attempts", job_id)
            time.sleep(2)


if __name__ == "__main__":
    main()

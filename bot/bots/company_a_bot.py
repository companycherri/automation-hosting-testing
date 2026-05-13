"""
company_a_bot.py — Company A Portal Automation
Portal: Simple login + normal HTML form
Selectors: id-based (#username, #password, #part_no, #vendor_code, etc.)
"""
import logging
import time
from .base_bot import BaseBot

log = logging.getLogger(__name__)

BASE_URL   = "http://localhost/mini-automation/portals/company-a"
LOGIN_URL  = f"{BASE_URL}/login.php"
FORM_URL   = f"{BASE_URL}/form.php"


class CompanyABot(BaseBot):
    COMPANY = "company_a"

    def run(self, job: dict) -> str:
        """
        Run Company A automation. Returns local file path of downloaded file.
        job keys: part_no, quantity, batch_no, vendor_code, priority (optional)
        """
        p  = self.page
        jid = self.job_id

        # ── STEP 1: Login ──────────────────────────────────
        log.info("  [A] STEP 1 → Login")
        self.start_timer("login")
        p.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=30_000)
        self.assert_url_contains("login", "step1")
        self.shot("01_login_page")

        p.wait_for_selector("#username", state="visible", timeout=10_000)
        p.fill("#username", "operator_a")
        p.fill("#password", "pass_a123")
        self.shot("02_login_filled")

        p.click("#login-btn")
        p.wait_for_url("**/form.php", timeout=15_000)
        self.end_timer("login")
        self.shot("03_logged_in")
        log.info("  ✓ [A] Login successful")

        # ── STEP 2: Fill form ──────────────────────────────
        log.info("  [A] STEP 2 → Filling form")
        self.start_timer("fill_form")
        p.wait_for_selector("#part_no", state="attached", timeout=15_000)
        p.wait_for_selector("#submit-btn", state="visible", timeout=10_000)
        self.shot("04_form_ready")

        selected_part = self.select_dropdown("#part_no", str(job["part_no"]), "part_no")
        p.fill("#quantity", str(job["quantity"]))
        p.fill("#batch_no", str(job["batch_no"]))
        selected_vendor = self.select_dropdown("#vendor_code", str(job["vendor_code"]), "vendor_code")

        # Optional fields
        if job.get("delivery_date"):
            p.fill("#delivery_date", str(job["delivery_date"]))
        if job.get("notes"):
            p.fill("#notes", str(job["notes"]))
        if job.get("priority"):
            radio = f"#priority_{job['priority']}"
            if p.locator(radio).count() > 0:
                p.click(radio)

        self.end_timer("fill_form")
        self.shot("05_form_filled")
        log.info("  ✓ [A] Form filled: part=%s qty=%s vendor=%s",
                 selected_part, job["quantity"], selected_vendor)

        # ── STEP 3: Submit ─────────────────────────────────
        log.info("  [A] STEP 3 → Submit")
        self.start_timer("submit")
        p.click("#submit-btn")
        p.wait_for_url("**/generate.php", timeout=15_000)
        p.wait_for_selector("#download-btn", state="visible", timeout=10_000)
        self.end_timer("submit")
        self.shot("06_generated")
        log.info("  ✓ [A] Barcode generated")

        # ── STEP 4: Download ───────────────────────────────
        log.info("  [A] STEP 4 → Download")
        file_path = self.download_file("#download-btn")
        self.shot("07_downloaded")
        log.info("  ✅ [A] JOB-%04d complete → %s", jid, file_path)
        return file_path

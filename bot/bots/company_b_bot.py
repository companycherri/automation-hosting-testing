"""
company_b_bot.py — Company B Portal Automation
Portal: OTP login + searchable dropdown + AJAX vendor + modal confirm
Selectors: #email, #password, #otp-input, data-testid attributes
"""
import logging
import time
from .base_bot import BaseBot

log = logging.getLogger(__name__)

BASE_URL   = "http://localhost/mini-automation/portals/company-b"
LOGIN_URL  = f"{BASE_URL}/login.php"
OTP_URL    = f"{BASE_URL}/verify-otp.php"
FORM_URL   = f"{BASE_URL}/form.php"
STATIC_OTP = "123456"


class CompanyBBot(BaseBot):
    COMPANY = "company_b"

    def run(self, job: dict) -> str:
        """
        Run Company B automation.
        job keys: part_no, quantity, batch_no, vendor_code, priority (optional)
        """
        p   = self.page
        jid = self.job_id

        # ── STEP 1: Login (email + password) ───────────────
        log.info("  [B] STEP 1 → Email/password login")
        self.start_timer("login")
        p.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=30_000)
        self.shot("01_login")

        p.wait_for_selector("#email", state="visible", timeout=10_000)
        p.fill("#email", "b.operator@company.com")
        p.fill("#password", "Bpass@2024")
        self.shot("02_login_filled")

        p.click("#login-btn")
        p.wait_for_url("**/verify-otp.php", timeout=15_000)
        self.shot("03_otp_page")
        log.info("  ✓ [B] Credentials accepted, OTP page loaded")

        # ── STEP 2: OTP verification ────────────────────────
        log.info("  [B] STEP 2 → Entering OTP: %s", STATIC_OTP)
        self.enter_otp("#otp-input", STATIC_OTP, "#verify-btn")
        p.wait_for_url("**/form.php", timeout=15_000)
        self.end_timer("login")
        self.shot("04_logged_in")
        log.info("  ✓ [B] OTP verified, form loaded")

        # ── STEP 3: Searchable part dropdown ────────────────
        log.info("  [B] STEP 3 → Searchable dropdown for part: %s", job["part_no"])
        p.wait_for_selector("[data-testid='part-search']", state="visible", timeout=15_000)

        # Type first few chars to filter
        search_text = str(job["part_no"])[:3]  # e.g. "ENG" from "ENG-001"
        self.fill_searchable_dropdown(
            search_input_selector="[data-testid='part-search-input']",
            option_selector="[data-testid='part-option']",
            search_text=search_text,
            option_value=str(job["part_no"]),
            field="part_no"
        )
        time.sleep(0.3)
        self.shot("05_part_selected")

        # ── STEP 4: Fill text fields ────────────────────────
        log.info("  [B] STEP 4 → Quantity and batch")
        p.fill("#quantity", str(job["quantity"]))
        p.fill("#batch_no",  str(job["batch_no"]))

        # Priority radio
        if job.get("priority"):
            opts = p.query_selector_all(".radio-opt")
            for opt in opts:
                radio = opt.query_selector("input[type=radio]")
                if radio and radio.get_attribute("value") == job.get("priority", "normal"):
                    opt.click()
                    break

        # ── STEP 5: Wait for AJAX vendor dropdown ───────────
        log.info("  [B] STEP 5 → Waiting for AJAX vendor dropdown (1.5s delay)...")
        self.wait_for_ajax_dropdown("[data-testid='vendor-select']", timeout=30_000, field="vendor")
        self.select_dropdown("[data-testid='vendor-select']", str(job["vendor_code"]), "vendor_code")
        self.shot("06_form_filled")

        # ── STEP 6: Fill iframe contact fields ─────────────
        log.info("  [B] STEP 6 → Filling iframe contact fields")
        try:
            iframe = self.get_iframe("#contact-iframe")
            iframe.locator("[data-testid='iframe-contact-name']").fill("Automation Bot")
            iframe.locator("[data-testid='iframe-contact-phone']").fill("012-0000000")
            log.info("  ✓ [B] Iframe fields filled")
        except Exception as e:
            log.warning("  ⚠  Iframe fill skipped: %s", e)

        # ── STEP 7: Modal confirmation ─────────────────────
        log.info("  [B] STEP 7 → Submit button → Modal popup")
        self.handle_modal(
            trigger_selector="[data-testid='submit-form']",
            modal_selector="[data-testid='confirm-order']",
            confirm_selector="[data-testid='confirm-order']",
        )

        # Wait for form to actually submit
        p.wait_for_url("**/generate.php", timeout=20_000)
        p.wait_for_selector("#download-btn", state="visible", timeout=10_000)
        self.shot("07_generated")
        log.info("  ✓ [B] Barcode generated")

        # ── STEP 8: Download ───────────────────────────────
        log.info("  [B] STEP 8 → Download")
        file_path = self.download_file("#download-btn")
        self.shot("08_downloaded")
        log.info("  ✅ [B] JOB-%04d complete → %s", jid, file_path)
        return file_path

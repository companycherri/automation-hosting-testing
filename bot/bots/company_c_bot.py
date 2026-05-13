"""
company_c_bot.py — Company C Portal Automation
Portal: Delayed login + multi-step form + React-style dropdown + dependent AJAX + multi-select
Selectors: data-testid, class names, name attributes
"""
import logging
import time
from .base_bot import BaseBot

log = logging.getLogger(__name__)

BASE_URL  = "http://localhost/mini-automation/portals/company-c"
LOGIN_URL = f"{BASE_URL}/login.php"
FORM_URL  = f"{BASE_URL}/form.php"


class CompanyCBot(BaseBot):
    COMPANY = "company_c"

    def run(self, job: dict) -> str:
        """
        Run Company C automation.
        job keys: category, part_no, quantity, batch_no, vendor_codes (list), priority, notes
        """
        p   = self.page
        jid = self.job_id

        # ── STEP 1: Login (with spinner delay) ─────────────
        log.info("  [C] STEP 1 → Login with delayed spinner")
        self.start_timer("login")
        p.goto(LOGIN_URL, wait_until="domcontentloaded", timeout=30_000)
        self.shot("01_login")

        p.wait_for_selector("input[name='username']", state="visible", timeout=10_000)
        p.fill("input[name='username']", "admin")
        p.fill("input[name='password']", "CompanyC#123")
        self.shot("02_login_filled")

        p.click("button.login-submit")

        # Wait for spinner then redirect (2s delay in portal)
        log.info("  [C] Waiting for spinner + redirect (≈2s)...")
        p.wait_for_url("**/form.php", timeout=20_000)
        self.end_timer("login")
        self.shot("03_logged_in")
        log.info("  ✓ [C] Login successful")

        # ── STEP 2: Multi-step form — Step 1 ───────────────
        log.info("  [C] STEP 2 → Step 1: Category + Part + Batch")
        p.wait_for_selector("[data-testid='step-1']", state="visible", timeout=15_000)
        self.shot("04_step1")

        # React-style category dropdown
        category = str(job.get("category", "AUTO"))
        self.fill_react_dropdown(
            control_selector="#cat-control",
            option_selector="[data-testid='category-option']",
            option_value=category,
            search_selector="#cat-search",
            field="category"
        )
        time.sleep(0.5)
        self.shot("05_category_selected")

        # Wait for AJAX subcategories to load
        log.info("  [C] Waiting for dependent AJAX subcategory dropdown...")
        self.wait_for_ajax_dropdown(
            "#sub-select",
            timeout=15_000,
            field="subcategory"
        )
        self.select_dropdown("#sub-select", str(job["part_no"]), "part_no")
        time.sleep(0.2)

        # Batch number
        p.fill("[data-testid='batch-input']", str(job["batch_no"]))
        self.shot("06_step1_filled")

        # Click Next → Step 2
        p.click("[data-testid='next-step-1']")
        p.wait_for_selector("[data-testid='step-2']", state="visible", timeout=10_000)
        log.info("  ✓ [C] Step 1 complete")

        # ── STEP 3: Multi-step form — Step 2 ───────────────
        log.info("  [C] STEP 3 → Step 2: Quantity, Date, Priority, Notes")
        self.shot("07_step2")

        p.wait_for_selector("[data-testid='quantity-input']", state="visible")
        p.fill("[data-testid='quantity-input']", str(job["quantity"]))

        if job.get("delivery_date"):
            p.fill("[data-testid='delivery-date']", str(job["delivery_date"]))

        # Priority radio pill
        priority = job.get("priority", "normal")
        radio_testid = f"[data-testid='priority-{priority}']"
        if p.locator(radio_testid).count() > 0:
            # Click the parent radio-pill label
            p.evaluate(f"""
                () => {{
                    const el = document.querySelector("[data-testid='priority-{priority}']");
                    if (el) el.click();
                }}
            """)

        if job.get("notes"):
            p.fill("[data-testid='notes-textarea']", str(job["notes"]))

        self.shot("08_step2_filled")
        p.click("[data-testid='next-step-2']")
        p.wait_for_selector("[data-testid='step-3']", state="visible", timeout=10_000)
        log.info("  ✓ [C] Step 2 complete")

        # ── STEP 4: Multi-step form — Step 3 ───────────────
        log.info("  [C] STEP 4 → Step 3: Vendors + Terms + Submit")
        self.shot("09_step3")

        # Wait for vendor options to load
        p.wait_for_selector("[data-testid='vendor-option']", state="visible", timeout=15_000)

        # Multi-select vendors
        vendor_codes = job.get("vendor_codes", ["VND-C-001"])
        if isinstance(vendor_codes, str):
            vendor_codes = [vendor_codes]

        selected = self.select_multi(
            option_selector="[data-testid='vendor-option']",
            values=vendor_codes,
            field="vendors"
        )
        log.info("  ✓ [C] Vendors selected: %s", selected)
        self.shot("10_vendors_selected")

        # Check terms checkbox
        p.check("[data-testid='terms-checkbox']")
        time.sleep(0.3)

        # Final submit
        p.wait_for_selector("[data-testid='final-submit']", state="visible")
        p.click("[data-testid='final-submit']")
        p.wait_for_url("**/generate.php", timeout=20_000)
        p.wait_for_selector("#download-btn", state="visible", timeout=10_000)
        self.shot("11_generated")
        log.info("  ✓ [C] Barcode generated")

        # ── STEP 5: Download ───────────────────────────────
        log.info("  [C] STEP 5 → Download")
        file_path = self.download_file("#download-btn")
        self.shot("12_downloaded")
        log.info("  ✅ [C] JOB-%04d complete → %s", jid, file_path)
        return file_path

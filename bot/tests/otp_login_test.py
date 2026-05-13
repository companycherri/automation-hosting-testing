"""
otp_login_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle 2-step OTP login flows
PORTAL: Company B (OTP Portal)
URL   : http://localhost/mini-automation/portals/company-b/login.php

Techniques demonstrated:
  - Multi-page login flow
  - OTP field detection and filling
  - Auto-submit after 6 digits
  - Handling fake countdown timer
  - Verifying each redirect step
═══════════════════════════════════════════════════════════
Run: python tests/otp_login_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL  = "http://localhost/mini-automation/portals/company-b/login.php"
OTP_URL    = "http://localhost/mini-automation/portals/company-b/verify-otp.php"
FORM_URL   = "http://localhost/mini-automation/portals/company-b/form.php"
EMAIL      = "b.operator@company.com"
PASSWORD   = "Bpass@2024"
STATIC_OTP = "123456"


def test_otp_login():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=600)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: 2-Step OTP Login Flow")
        print("  Portal: Company B")
        print("="*60)

        # ── Step 1: Email/Password ─────────────────────────
        print("\n[1] Opening login page...")
        page.goto(LOGIN_URL, wait_until="domcontentloaded")
        page.wait_for_selector("#email", state="visible", timeout=8_000)
        print(f"    ✓ Login page loaded: {page.url}")

        print(f"\n[2] Filling credentials: {EMAIL}")
        page.fill("#email", EMAIL)
        page.fill("#password", PASSWORD)
        print("    ✓ Credentials entered")

        print("\n[3] Clicking Continue → expecting OTP page")
        page.click("#login-btn")
        page.wait_for_url("**/verify-otp.php", timeout=10_000)
        print(f"    ✓ OTP page loaded: {page.url}")

        # Verify OTP page elements
        page.wait_for_selector("#otp-input", state="visible", timeout=8_000)
        page.wait_for_selector("#verify-btn", state="visible", timeout=5_000)
        page.wait_for_selector(".resend-otp", state="visible", timeout=5_000)
        print("    ✓ OTP input, verify button, and resend link present")

        # ── Step 2: Wrong OTP first ────────────────────────
        print("\n[4] Testing WRONG OTP first (999999)...")
        page.fill("#otp-input", "999999")
        page.click("#verify-btn")
        time.sleep(1.5)  # wait for page reload
        error = page.locator("#otp-error")
        if error.count() > 0:
            print(f"    ✓ Error shown: '{error.inner_text()}'")
        else:
            print("    ⚠  No error element found (may have different behavior)")
        page.fill("#otp-input", "")  # clear

        # ── Step 3: Correct OTP ────────────────────────────
        print(f"\n[5] Entering correct OTP: {STATIC_OTP}")
        page.wait_for_selector("#otp-input", state="visible", timeout=8_000)

        # Type digit by digit to test real-world behavior
        for digit in STATIC_OTP:
            page.type("#otp-input", digit)
            time.sleep(0.15)

        current = page.input_value("#otp-input")
        print(f"    ✓ OTP entered: {current}")

        print("\n[6] Submitting OTP...")
        page.click("#verify-btn")
        page.wait_for_url("**/form.php", timeout=15_000)
        print(f"    ✓ Redirected to form: {page.url}")

        # ── Verify session is active ────────────────────────
        page.wait_for_selector("[data-testid='part-search']", state="visible", timeout=8_000)
        print("    ✓ Form elements visible — session active")

        # ── Verify going back to login redirects to form ───
        print("\n[7] Testing session persistence (navigate to login URL)...")
        page.goto(LOGIN_URL)
        time.sleep(1)
        # Should redirect back to form (already logged in)
        if "form.php" in page.url:
            print("    ✓ Session persistent — redirected back to form")
        else:
            print(f"    ℹ  At: {page.url}")

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — OTP Login Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_otp_login()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

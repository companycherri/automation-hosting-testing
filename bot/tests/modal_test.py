"""
modal_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle modal popups before form submission
PORTAL: Company B
URL   : http://localhost/mini-automation/portals/company-b/form.php

Techniques demonstrated:
  - Detecting modal appearance
  - Reading modal content for verification
  - Confirming vs cancelling modal
  - Handling modal with animation delay
  - Multiple modal interactions
═══════════════════════════════════════════════════════════
Run: python tests/modal_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-b/login.php"


def quick_login(page):
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.fill("#email", "b.operator@company.com")
    page.fill("#password", "Bpass@2024")
    page.click("#login-btn")
    page.wait_for_url("**/verify-otp.php", timeout=10_000)
    page.fill("#otp-input", "123456")
    page.click("#verify-btn")
    page.wait_for_url("**/form.php", timeout=15_000)
    print("    ✓ Logged in")


def fill_required_fields(page):
    """Fill enough fields to enable the submit button."""
    # Select part via searchable dropdown
    page.wait_for_selector("[data-testid='part-search-input']", state="visible")
    page.click("[data-testid='part-search-input']")
    time.sleep(0.4)
    page.locator("[data-testid='part-option']").first.click()
    time.sleep(0.3)

    # Fill text fields
    page.fill("#quantity", "100")
    page.fill("#batch_no", "BATCH-MODAL-TEST")

    # Wait for vendor AJAX to load and select
    page.wait_for_selector("#vendor-loading", state="hidden", timeout=10_000)
    page.wait_for_function("document.getElementById('vendor-select').options.length > 1", timeout=5_000)
    options = page.evaluate("""
        () => Array.from(document.getElementById('vendor-select').options)
                   .filter(o => o.value !== '').map(o => o.value)
    """)
    if options:
        page.select_option("[data-testid='vendor-select']", value=options[0])
    print("    ✓ Required fields filled, submit button should be enabled")


def test_modal():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=600)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: Modal Popup Handling")
        print("  Portal: Company B")
        print("="*60)

        print("\n[1] Logging in...")
        quick_login(page)

        print("\n[2] Filling required fields...")
        fill_required_fields(page)

        # ── Test 1: Verify submit button state ────────────
        print("\n[3] Checking submit button state...")
        btn_disabled = page.evaluate(
            "document.querySelector('[data-testid=\"submit-form\"]').disabled"
        )
        print(f"    Submit button disabled: {btn_disabled}")
        if btn_disabled:
            print("    ✗ Button still disabled — check required fields")

        # ── Test 2: Open modal ─────────────────────────────
        print("\n[4] Clicking submit → modal should appear...")
        page.click("[data-testid='submit-form']")
        time.sleep(0.5)  # animation

        modal = page.locator(".modal-overlay")
        assert modal.is_visible(), "Modal should be visible"
        print("    ✓ Modal is visible!")

        # Read modal summary content
        summary = page.locator("#modal-summary").inner_text()
        print(f"    📋 Modal summary:\n{summary}")

        # ── Test 3: Cancel modal ──────────────────────────
        print("\n[5] Clicking CANCEL on modal...")
        page.click("[data-testid='cancel-order']")
        time.sleep(0.4)
        assert not modal.is_visible(), "Modal should be hidden after cancel"
        assert "form.php" in page.url, "Should remain on form page after cancel"
        print("    ✓ Modal closed, still on form page")

        # ── Test 4: Open modal again ───────────────────────
        print("\n[6] Opening modal again...")
        page.click("[data-testid='submit-form']")
        time.sleep(0.5)
        assert modal.is_visible(), "Modal should be visible again"

        # Verify confirm button is present
        confirm_btn = page.locator("[data-testid='confirm-order']")
        assert confirm_btn.is_visible(), "Confirm button should be visible in modal"
        print("    ✓ Modal reopened, confirm button present")

        # ── Test 5: Confirm modal → form submits ──────────
        print("\n[7] Clicking CONFIRM → form should submit...")
        page.click("[data-testid='confirm-order']")

        # Wait for redirect to generate page
        page.wait_for_url("**/generate.php", timeout=20_000)
        page.wait_for_selector("#download-btn", state="visible", timeout=10_000)
        print(f"    ✓ Form submitted! Redirected to: {page.url}")
        print("    ✓ Download button visible — barcode generated")

        # Download and clean up
        with page.expect_download(timeout=15_000) as dl:
            page.click("#download-btn")
        d = dl.value
        print(f"    ✓ File downloaded: {d.suggested_filename}")
        d.delete()

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — Modal Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_modal()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

"""
normal_dropdown_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle normal HTML <select> dropdowns
PORTAL: Company A (Simple Portal)
URL   : http://localhost/mini-automation/portals/company-a/login.php

Techniques demonstrated:
  - page.select_option(selector, value=...)
  - Reading available options via JavaScript
  - Fallback to first option when value not found
  - Verifying selection was applied
═══════════════════════════════════════════════════════════
Run: python tests/normal_dropdown_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-a/login.php"
USERNAME  = "operator_a"
PASSWORD  = "pass_a123"

# Test data
TEST_PART   = "BRK-003"
TEST_QTY    = "150"
TEST_BATCH  = "BATCH-A-2024-TEST"
TEST_VENDOR = "VND-A-002"


def test_normal_dropdown():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=500)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: Normal HTML <select> Dropdown")
        print("  Portal: Company A")
        print("="*60)

        # ── Login ──────────────────────────────────────────
        print("\n[1] Opening login page...")
        page.goto(LOGIN_URL, wait_until="domcontentloaded")
        page.wait_for_selector("#username", state="visible", timeout=8_000)
        print("    ✓ Login page loaded")

        page.fill("#username", USERNAME)
        page.fill("#password", PASSWORD)
        page.click("#login-btn")
        page.wait_for_url("**/form.php", timeout=10_000)
        print("    ✓ Logged in successfully")

        # ── Read available options ─────────────────────────
        print("\n[2] Reading dropdown options...")
        page.wait_for_selector("#part_no", state="attached", timeout=8_000)

        part_options = page.evaluate("""
            () => Array.from(document.querySelectorAll('#part_no option'))
                       .map(o => ({value: o.value, text: o.text}))
                       .filter(o => o.value !== '')
        """)
        print(f"    Part options ({len(part_options)}):")
        for o in part_options:
            print(f"      • {o['value']:10s} — {o['text']}")

        vendor_options = page.evaluate("""
            () => Array.from(document.querySelectorAll('#vendor_code option'))
                       .map(o => ({value: o.value, text: o.text}))
                       .filter(o => o.value !== '')
        """)
        print(f"\n    Vendor options ({len(vendor_options)}):")
        for o in vendor_options:
            print(f"      • {o['value']}")

        # ── Select part ────────────────────────────────────
        print(f"\n[3] Selecting part: {TEST_PART}")

        # Method 1: Direct value selection
        page.select_option("#part_no", value=TEST_PART)
        chosen = page.evaluate("document.getElementById('part_no').value")
        assert chosen == TEST_PART, f"Expected {TEST_PART} but got {chosen}"
        print(f"    ✓ Part selected: {chosen}")

        # ── Fill other fields ──────────────────────────────
        print(f"\n[4] Filling quantity: {TEST_QTY}")
        page.fill("#quantity", TEST_QTY)
        print(f"    ✓ Quantity: {page.input_value('#quantity')}")

        print(f"\n[5] Filling batch number: {TEST_BATCH}")
        page.fill("#batch_no", TEST_BATCH)

        print(f"\n[6] Filling delivery date: 2024-12-31")
        page.fill("#delivery_date", "2024-12-31")

        print(f"\n[7] Filling notes")
        page.fill("#notes", "Automated test — normal dropdown flow")

        # ── Select vendor ──────────────────────────────────
        print(f"\n[8] Selecting vendor: {TEST_VENDOR}")
        page.select_option("#vendor_code", value=TEST_VENDOR)
        chosen_v = page.evaluate("document.getElementById('vendor_code').value")
        assert chosen_v == TEST_VENDOR, f"Expected {TEST_VENDOR} but got {chosen_v}"
        print(f"    ✓ Vendor selected: {chosen_v}")

        # ── Fallback test ──────────────────────────────────
        print(f"\n[9] Testing fallback — selecting nonexistent value 'FAKE-999'")
        all_parts = [o['value'] for o in part_options]
        target = "FAKE-999" if "FAKE-999" not in all_parts else all_parts[0]
        if target not in all_parts:
            target = all_parts[0]
            print(f"    ⚠  'FAKE-999' not found → fallback to first: '{target}'")
        page.select_option("#part_no", value=target)
        print(f"    ✓ Fallback selected: {page.evaluate('document.getElementById(\"part_no\").value')}")
        # Re-select correct value
        page.select_option("#part_no", value=TEST_PART)

        # ── Radio button selection ─────────────────────────
        print("\n[10] Selecting 'urgent' radio button")
        page.click("#priority_urgent")
        val = page.evaluate("document.querySelector('input[name=priority]:checked').value")
        print(f"     ✓ Priority: {val}")

        # ── Submit ─────────────────────────────────────────
        print("\n[11] Submitting form...")
        page.click("#submit-btn")
        page.wait_for_url("**/generate.php", timeout=12_000)
        page.wait_for_selector("#download-btn", state="visible", timeout=8_000)
        print("     ✓ Barcode generated page loaded!")

        # ── Download ───────────────────────────────────────
        print("\n[12] Downloading barcode file...")
        with page.expect_download(timeout=15_000) as dl:
            page.click("#download-btn")
        d = dl.value
        print(f"     ✓ Downloaded: {d.suggested_filename}")
        d.delete()

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — Normal Dropdown Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_normal_dropdown()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

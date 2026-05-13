"""
ajax_dropdown_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle AJAX-loaded dropdowns
PORTAL: Company B
URL   : http://localhost/mini-automation/portals/company-b/form.php

Techniques demonstrated:
  - Detecting when a loading spinner disappears
  - Polling for dropdown options to populate
  - Using network request interception to track AJAX
  - Timeout strategies for slow API calls
  - Verifying option count before selecting
═══════════════════════════════════════════════════════════
Run: python tests/ajax_dropdown_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-b/login.php"


def login_with_otp(page):
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.wait_for_selector("#email", state="visible")
    page.fill("#email", "b.operator@company.com")
    page.fill("#password", "Bpass@2024")
    page.click("#login-btn")
    page.wait_for_url("**/verify-otp.php", timeout=10_000)
    page.fill("#otp-input", "123456")
    page.click("#verify-btn")
    page.wait_for_url("**/form.php", timeout=15_000)
    print("    ✓ Logged in")


def test_ajax_dropdown():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=400)
        page    = browser.new_page()

        # Track network requests to the vendors API
        api_called = []
        page.on("request",  lambda r: api_called.append(r.url) if "vendors" in r.url else None)
        page.on("response", lambda r: print(f"    📡 API response: {r.url} → {r.status}") if "vendors" in r.url else None)

        print("\n" + "="*60)
        print("  TEST: AJAX-Loaded Dropdown")
        print("  Portal: Company B (vendors load with 1.5s delay)")
        print("="*60)

        print("\n[1] Logging in...")
        login_with_otp(page)

        # ── Method 1: Wait for spinner to disappear ────────
        print("\n[2] Checking loading spinner is visible...")
        spinner = page.locator("#vendor-loading")
        if spinner.is_visible():
            print("    ✓ Loading spinner is visible")

        print("\n[3] Waiting for spinner to disappear (Method 1: wait_for_hidden)...")
        t_start = time.time()
        page.wait_for_selector("#vendor-loading", state="hidden", timeout=10_000)
        elapsed = time.time() - t_start
        print(f"    ✓ Spinner disappeared after {elapsed:.1f}s")

        # ── Method 2: Poll for option count ───────────────
        print("\n[4] Verifying dropdown is populated (Method 2: poll option count)...")
        max_wait = 5.0
        t_start  = time.time()
        while time.time() - t_start < max_wait:
            count = page.evaluate("""
                () => {
                    const el = document.getElementById('vendor-select');
                    return el ? el.options.length : 0;
                }
            """)
            if count > 1:
                print(f"    ✓ Dropdown has {count} options (including placeholder)")
                break
            time.sleep(0.3)
        else:
            print("    ✗ Dropdown did not populate in time!")

        # ── Method 3: wait_for_function ───────────────────
        print("\n[5] Verifying with wait_for_function (Method 3)...")
        page.wait_for_function("""
            () => {
                const sel = document.getElementById('vendor-select');
                return sel && sel.options.length > 1 && sel.classList.contains('loaded');
            }
        """, timeout=5_000)
        print("    ✓ wait_for_function confirmed dropdown is ready")

        # ── Select a vendor ────────────────────────────────
        print("\n[6] Selecting a vendor...")
        page.wait_for_selector("#vendor-select", state="visible", timeout=5_000)
        options = page.evaluate("""
            () => Array.from(document.getElementById('vendor-select').options)
                       .map(o => ({value: o.value, text: o.text}))
                       .filter(o => o.value !== '')
        """)
        print(f"    Available vendors:")
        for o in options:
            print(f"      • {o['value']:12s} — {o['text']}")

        if options:
            target = options[0]['value']
            page.select_option("[data-testid='vendor-select']", value=target)
            chosen = page.evaluate("document.getElementById('vendor-select').value")
            print(f"\n    ✓ Selected: {chosen}")

        # ── Check API was called ───────────────────────────
        print(f"\n[7] API calls intercepted: {len(api_called)}")
        for url in api_called:
            print(f"      📡 {url}")

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — AJAX Dropdown Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_ajax_dropdown()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

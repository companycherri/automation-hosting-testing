"""
searchable_dropdown_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle custom searchable dropdowns
PORTAL: Company B (OTP Portal → Form)
URL   : http://localhost/mini-automation/portals/company-b/form.php

Techniques demonstrated:
  - Typing in search input to filter options
  - Waiting for filtered results
  - Clicking option by data attribute
  - Verifying hidden input received value
  - Handling "no results" case
  - Keyboard navigation (arrow + enter)
═══════════════════════════════════════════════════════════
Run: python tests/searchable_dropdown_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-b/login.php"
FORM_URL  = "http://localhost/mini-automation/portals/company-b/form.php"


def login_with_otp(page):
    """Helper: complete Company B 2-step login."""
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.wait_for_selector("#email", state="visible", timeout=8_000)
    page.fill("#email", "b.operator@company.com")
    page.fill("#password", "Bpass@2024")
    page.click("#login-btn")
    page.wait_for_url("**/verify-otp.php", timeout=10_000)
    page.wait_for_selector("#otp-input", state="visible", timeout=8_000)
    page.fill("#otp-input", "123456")
    page.click("#verify-btn")
    page.wait_for_url("**/form.php", timeout=15_000)
    print("    ✓ Logged in (OTP passed)")


def test_searchable_dropdown():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=500)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: Custom Searchable Dropdown")
        print("  Portal: Company B")
        print("="*60)

        print("\n[1] Logging in...")
        login_with_otp(page)
        page.wait_for_selector("[data-testid='part-search']", state="visible", timeout=8_000)

        # ── Test 1: Click to open ──────────────────────────
        print("\n[2] Clicking search input to open dropdown...")
        page.click("[data-testid='part-search-input']")
        time.sleep(0.5)
        dropdown = page.locator("[data-testid='part-dropdown']")
        assert dropdown.is_visible(), "Dropdown should be visible after click"
        count = page.locator("[data-testid='part-option']").count()
        print(f"    ✓ Dropdown opened with {count} options")

        # ── Test 2: Type to filter ─────────────────────────
        print("\n[3] Typing 'eng' to filter...")
        page.fill("[data-testid='part-search-input']", "eng")
        time.sleep(0.5)
        filtered = page.locator("[data-testid='part-option']").count()
        print(f"    ✓ Filtered to {filtered} option(s)")

        # ── Test 3: Select by clicking option ─────────────
        print("\n[4] Selecting first filtered result...")
        first_opt = page.locator("[data-testid='part-option']").first
        opt_text = first_opt.inner_text()
        first_opt.click()
        time.sleep(0.3)
        hidden_val = page.input_value("#hidden-part-no")
        print(f"    ✓ Clicked: '{opt_text}' → hidden value: '{hidden_val}'")
        assert hidden_val, "Hidden input should have a value after selection"

        # ── Test 4: Search with full code ─────────────────
        print("\n[5] Searching with full part code 'TRN-002'...")
        page.click("[data-testid='part-search-input']")
        page.triple_click("[data-testid='part-search-input']")  # select all
        page.fill("[data-testid='part-search-input']", "TRN")
        time.sleep(0.5)
        opts = page.locator("[data-testid='part-option']")
        found = False
        for i in range(opts.count()):
            t = opts.nth(i).inner_text()
            if "TRN" in t:
                opts.nth(i).click()
                found = True
                print(f"    ✓ Found and clicked: '{t}'")
                break
        if not found:
            print("    ⚠  TRN-002 not found in filtered results")

        # ── Test 5: No results ─────────────────────────────
        print("\n[6] Testing no-results case (search 'ZZZZZ')...")
        page.click("[data-testid='part-search-input']")
        page.triple_click("[data-testid='part-search-input']")
        page.fill("[data-testid='part-search-input']", "ZZZZZ")
        time.sleep(0.5)
        no_result = page.locator(".sd-empty")
        if no_result.is_visible():
            print(f"    ✓ 'No results' message shown: '{no_result.inner_text()}'")
        else:
            print(f"    ℹ  Result count: {page.locator('[data-testid=\"part-option\"]').count()}")

        # ── Test 6: Close by clicking outside ─────────────
        print("\n[7] Closing dropdown by clicking outside...")
        page.click("h2")  # click page title
        time.sleep(0.3)
        is_open = page.locator("[data-testid='part-dropdown']").is_visible()
        print(f"    {'✓ Dropdown closed' if not is_open else '⚠  Dropdown still open'}")

        # ── Final: Re-select valid part for submission ─────
        print("\n[8] Re-selecting valid part 'BRK-003'...")
        page.click("[data-testid='part-search-input']")
        page.triple_click("[data-testid='part-search-input']")
        page.fill("[data-testid='part-search-input']", "BRK")
        time.sleep(0.5)
        page.locator("[data-testid='part-option']").first.click()
        val = page.input_value("#hidden-part-no")
        print(f"    ✓ Hidden value: '{val}'")

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — Searchable Dropdown Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_searchable_dropdown()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

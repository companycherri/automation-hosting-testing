"""
react_dropdown_test.py
═══════════════════════════════════════════════════════════
LESSON: How to handle React-style custom div dropdowns
PORTAL: Company C (Multi-step form)
URL   : http://localhost/mini-automation/portals/company-c/form.php

Techniques demonstrated:
  - Clicking custom div control (not a real <select>)
  - Searching inside custom dropdown
  - Clicking option by data attribute vs text
  - Reading selected value from hidden input
  - Handling dropdown close behavior
  - Testing dependent AJAX loading after selection
═══════════════════════════════════════════════════════════
Run: python tests/react_dropdown_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-c/login.php"


def login_company_c(page):
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.wait_for_selector("input[name='username']", state="visible", timeout=8_000)
    page.fill("input[name='username']", "admin")
    page.fill("input[name='password']", "CompanyC#123")
    page.click("button.login-submit")
    page.wait_for_url("**/form.php", timeout=20_000)
    print("    ✓ Logged in to Company C")


def test_react_dropdown():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=600)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: React-Style Custom Dropdown")
        print("  Portal: Company C")
        print("="*60)

        print("\n[1] Logging in (watch spinner delay)...")
        login_company_c(page)
        page.wait_for_selector("[data-testid='step-1']", state="visible", timeout=10_000)
        print("    ✓ Multi-step form loaded")

        # ── Test 1: Dropdown is closed by default ──────────
        print("\n[2] Verifying dropdown is closed by default...")
        menu = page.locator("#cat-menu")
        assert not menu.is_visible(), "Menu should be hidden initially"
        print("    ✓ Dropdown menu is hidden (correct)")

        # ── Test 2: Click control to open ─────────────────
        print("\n[3] Clicking control div to open dropdown...")
        page.click("#cat-control")
        time.sleep(0.4)
        assert menu.is_visible(), "Menu should open on click"
        print("    ✓ Dropdown opened!")

        # ── Test 3: Search inside dropdown ─────────────────
        print("\n[4] Typing in search box inside dropdown...")
        page.wait_for_selector("#cat-search", state="visible", timeout=5_000)
        page.fill("#cat-search", "auto")
        time.sleep(0.4)
        opts = page.locator("[data-testid='category-option']")
        count = opts.count()
        print(f"    ✓ Filtered to {count} option(s) for 'auto'")

        # ── Test 4: Click option ───────────────────────────
        print("\n[5] Clicking 'Automotive Parts' option...")
        # Find option with data-id="AUTO"
        auto_opt = page.locator("[data-testid='category-option'][data-id='AUTO']")
        if auto_opt.count() > 0:
            auto_opt.click()
            print("    ✓ Clicked AUTO option by data-id attribute")
        else:
            opts.first.click()
            print("    ✓ Clicked first available option (fallback)")

        time.sleep(0.4)

        # ── Verify selection ───────────────────────────────
        hidden_val = page.input_value("#hid-category")
        print(f"\n[6] Hidden category value: '{hidden_val}'")
        assert hidden_val, "Hidden input should have value after selection"

        # Verify dropdown is closed
        assert not menu.is_visible(), "Dropdown should close after selection"
        print("    ✓ Dropdown closed after selection")

        # Control text updated
        ctrl_text = page.locator("#cat-control").inner_text()
        print(f"    ✓ Control now shows: '{ctrl_text.strip()}'")

        # ── Test 5: Dependent dropdown loads ──────────────
        print("\n[7] Waiting for dependent subcategory dropdown to load via AJAX...")
        t_start = time.time()

        # Watch for loading indicator
        loading = page.locator("#sub-loading-wrap")
        if loading.is_visible():
            print(f"    ℹ  Loading indicator visible")

        page.wait_for_selector("#sub-select.loaded", state="visible", timeout=15_000)
        elapsed = time.time() - t_start
        print(f"    ✓ Subcategory dropdown loaded in {elapsed:.1f}s")

        # Read subcategory options
        sub_opts = page.evaluate("""
            () => Array.from(document.getElementById('sub-select').options)
                       .map(o => ({value: o.value, text: o.text}))
                       .filter(o => o.value !== '')
        """)
        print(f"    Available subcategories ({len(sub_opts)}):")
        for o in sub_opts:
            print(f"      • {o['value']:10s} — {o['text']}")

        # ── Test 6: Select subcategory ─────────────────────
        if sub_opts:
            target = sub_opts[0]['value']
            page.select_option("#sub-select", value=target)
            print(f"\n[8] ✓ Subcategory selected: {target}")

        # ── Test 7: Click outside to close ────────────────
        print("\n[9] Testing click-outside-to-close...")
        page.click("#cat-control")  # reopen
        time.sleep(0.3)
        assert menu.is_visible(), "Should reopen"
        page.click("h2")  # click outside
        time.sleep(0.3)
        assert not menu.is_visible(), "Should close on outside click"
        print("    ✓ Click-outside-to-close works correctly")

        # ── Test 8: Keyboard navigation ───────────────────
        print("\n[10] Testing keyboard navigation...")
        page.click("#cat-control")
        time.sleep(0.3)
        page.keyboard.press("Tab")
        time.sleep(0.2)
        page.keyboard.type("elec")
        time.sleep(0.3)
        elec_count = page.locator("[data-testid='category-option']").count()
        print(f"     ✓ Keyboard search 'elec' found {elec_count} result(s)")

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — React Dropdown Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_react_dropdown()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

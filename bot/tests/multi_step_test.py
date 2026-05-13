"""
multi_step_test.py
═══════════════════════════════════════════════════════════
LESSON: How to automate multi-step forms with validation
PORTAL: Company C (3-step form)
URL   : http://localhost/mini-automation/portals/company-c/form.php

Techniques demonstrated:
  - Progress indicator tracking
  - Step-by-step navigation
  - React-style custom dropdown (Step 1)
  - AJAX dependent dropdown (Step 1)
  - Radio pill selection (Step 2)
  - Multi-select with tags (Step 3)
  - Checkbox handling (Step 3)
  - Collecting values across steps
  - File upload simulation
  - Submit button state management
═══════════════════════════════════════════════════════════
Run: python tests/multi_step_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-c/login.php"


def login(page):
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.wait_for_selector("input[name='username']", state="visible")
    page.fill("input[name='username']", "admin")
    page.fill("input[name='password']", "CompanyC#123")
    page.click("button.login-submit")
    page.wait_for_url("**/form.php", timeout=20_000)
    print("    ✓ Logged in to Company C")


def test_multi_step_form():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=500)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: Multi-Step Form Automation")
        print("  Portal: Company C (3-step workflow)")
        print("="*60)

        print("\n[1] Logging in (spinner delay ~2s)...")
        login(page)
        page.wait_for_selector("[data-testid='step-1']", state="visible", timeout=10_000)
        print("    ✓ Form loaded on Step 1")

        # ══ STEP 1 ═════════════════════════════════════════
        print("\n" + "─"*40)
        print("  STEP 1: Category + Part + Batch")
        print("─"*40)

        # React-style dropdown — click control
        print("\n[2] Opening React-style category dropdown...")
        page.click("#cat-control")
        time.sleep(0.4)
        assert page.locator("#cat-menu").is_visible()
        print("    ✓ Dropdown opened")

        # Search for MECH
        print("[3] Searching for 'Mechanical'...")
        page.fill("#cat-search", "mech")
        time.sleep(0.3)
        count = page.locator("[data-testid='category-option']").count()
        print(f"    ✓ Found {count} matching options")

        # Click option
        mech_opt = page.locator("[data-testid='category-option'][data-id='MECH']")
        if mech_opt.count() > 0:
            mech_opt.click()
        else:
            page.locator("[data-testid='category-option']").first.click()
        time.sleep(0.3)

        cat_val = page.input_value("#hid-category")
        print(f"    ✓ Category selected: '{cat_val}'")

        # Wait for dependent subcategory dropdown
        print("\n[4] Waiting for dependent subcategory AJAX...")
        page.wait_for_selector("#sub-select.loaded", state="visible", timeout=12_000)
        sub_opts = page.evaluate("""
            () => Array.from(document.getElementById('sub-select').options)
                       .filter(o => o.value !== '').map(o => o.value)
        """)
        print(f"    ✓ Subcategory loaded: {sub_opts}")

        # Select subcategory
        if sub_opts:
            page.select_option("#sub-select", value=sub_opts[1] if len(sub_opts) > 1 else sub_opts[0])
            print(f"    ✓ Part selected: {page.input_value('#sub-select')}")

        # Batch number
        print("\n[5] Entering batch number...")
        page.fill("[data-testid='batch-input']", "BATCH-MS-2024-001")
        print("    ✓ Batch: BATCH-MS-2024-001")

        # Click Next
        print("\n[6] Clicking Next → Step 2...")
        page.click("[data-testid='next-step-1']")
        page.wait_for_selector("[data-testid='step-2']", state="visible", timeout=8_000)
        print("    ✓ Moved to Step 2")

        # Check progress indicator
        done_circles = page.locator(".step-circle.done").count()
        active_circles = page.locator(".step-circle.active").count()
        print(f"    Progress: {done_circles} done, {active_circles} active")

        # ══ STEP 2 ═════════════════════════════════════════
        print("\n" + "─"*40)
        print("  STEP 2: Quantity, Date, Priority, Notes")
        print("─"*40)

        print("\n[7] Filling quantity...")
        page.fill("[data-testid='quantity-input']", "750")
        print("    ✓ Quantity: 750")

        print("[8] Filling delivery date...")
        page.fill("[data-testid='delivery-date']", "2024-12-31")
        print("    ✓ Date: 2024-12-31")

        print("[9] Selecting 'High' priority radio pill...")
        page.evaluate("""
            () => { document.querySelector("[data-testid='priority-high']").click(); }
        """)
        selected_pri = page.evaluate("""
            () => { const c = document.querySelector("input[name='priority']:checked");
                    return c ? c.value : null; }
        """)
        print(f"    ✓ Priority: {selected_pri}")

        print("[10] Filling notes...")
        page.fill("[data-testid='notes-textarea']", "Multi-step form automation test run")
        print("     ✓ Notes filled")

        # Click Next → Step 3
        print("\n[11] Clicking Next → Step 3...")
        page.click("[data-testid='next-step-2']")
        page.wait_for_selector("[data-testid='step-3']", state="visible", timeout=8_000)
        print("     ✓ Moved to Step 3")

        # ══ STEP 3 ═════════════════════════════════════════
        print("\n" + "─"*40)
        print("  STEP 3: Vendors + Terms + Submit")
        print("─"*40)

        print("\n[12] Waiting for vendor options to load...")
        page.wait_for_selector("[data-testid='vendor-option']", state="visible", timeout=10_000)
        vendor_count = page.locator("[data-testid='vendor-option']").count()
        print(f"     ✓ {vendor_count} vendor options available")

        # Multi-select: pick 2 vendors
        print("[13] Selecting multiple vendors...")
        vendors_to_select = page.locator("[data-testid='vendor-option']")
        selected_count = 0
        for i in range(min(2, vendors_to_select.count())):
            vendors_to_select.nth(i).click()
            time.sleep(0.2)
            selected_count += 1
            code = vendors_to_select.nth(i).get_attribute("data-code")
            print(f"     ✓ Selected vendor: {code}")

        # Verify tags appeared
        tags = page.locator(".ms-tag").count()
        print(f"     ✓ {tags} vendor tag(s) visible")

        # Verify submit is still disabled (no terms yet)
        print("[14] Checking submit is disabled before terms...")
        is_disabled = page.evaluate(
            "document.querySelector('[data-testid=\"final-submit\"]').disabled"
        )
        print(f"     Submit disabled (before terms): {is_disabled}")

        # Check terms
        print("[15] Checking terms checkbox...")
        page.check("[data-testid='terms-checkbox']")
        time.sleep(0.3)
        is_disabled_after = page.evaluate(
            "document.querySelector('[data-testid=\"final-submit\"]').disabled"
        )
        print(f"     Submit disabled (after terms + vendors): {is_disabled_after}")
        assert not is_disabled_after, "Submit should be enabled now!"
        print("     ✓ Submit button enabled!")

        # Final submit
        print("\n[16] Submitting multi-step form...")
        page.click("[data-testid='final-submit']")
        page.wait_for_url("**/generate.php", timeout=20_000)
        page.wait_for_selector("#download-btn", state="visible", timeout=10_000)
        print(f"     ✓ Submitted! URL: {page.url}")

        # Download
        with page.expect_download(timeout=15_000) as dl:
            page.click("#download-btn")
        d = dl.value
        print(f"     ✓ Downloaded: {d.suggested_filename}")
        d.delete()

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — Multi-Step Form Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_multi_step_form()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

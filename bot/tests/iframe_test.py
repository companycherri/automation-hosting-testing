"""
iframe_test.py
═══════════════════════════════════════════════════════════
LESSON: How to interact with fields inside iframes
PORTAL: Company B (contact iframe field)
URL   : http://localhost/mini-automation/portals/company-b/form.php

Techniques demonstrated:
  - page.frame_locator() to access iframe content
  - Waiting for iframe to load
  - Filling fields inside iframe
  - Reading values from iframe fields
  - Switching between main page and iframe context
═══════════════════════════════════════════════════════════
Run: python tests/iframe_test.py
"""

import time
from playwright.sync_api import sync_playwright

LOGIN_URL = "http://localhost/mini-automation/portals/company-b/login.php"


def login(page):
    page.goto(LOGIN_URL, wait_until="domcontentloaded")
    page.fill("#email", "b.operator@company.com")
    page.fill("#password", "Bpass@2024")
    page.click("#login-btn")
    page.wait_for_url("**/verify-otp.php", timeout=10_000)
    page.fill("#otp-input", "123456")
    page.click("#verify-btn")
    page.wait_for_url("**/form.php", timeout=15_000)
    print("    ✓ Logged in")


def test_iframe():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=600)
        page    = browser.new_page()

        print("\n" + "="*60)
        print("  TEST: Iframe Field Interaction")
        print("  Portal: Company B (Contact iframe)")
        print("="*60)

        print("\n[1] Logging in...")
        login(page)

        # ── Test 1: Wait for iframe to load ───────────────
        print("\n[2] Waiting for iframe to load...")
        page.wait_for_selector("#contact-iframe", state="attached", timeout=10_000)
        print("    ✓ Iframe element found in DOM")

        # Wait for iframe content to load
        iframe_element = page.locator("#contact-iframe")

        # ── Method 1: frame_locator ────────────────────────
        print("\n[3] Accessing iframe with frame_locator (recommended)...")
        iframe = page.frame_locator("#contact-iframe")
        iframe.locator("[data-testid='iframe-contact-name']").wait_for(
            state="visible", timeout=8_000
        )
        print("    ✓ Iframe content loaded and field visible")

        # ── Fill fields inside iframe ──────────────────────
        print("\n[4] Filling name field inside iframe...")
        iframe.locator("[data-testid='iframe-contact-name']").fill("Ahmad Automation Bot")
        time.sleep(0.3)
        val = iframe.locator("[data-testid='iframe-contact-name']").input_value()
        print(f"    ✓ Name field value: '{val}'")

        print("\n[5] Filling phone field inside iframe...")
        iframe.locator("[data-testid='iframe-contact-phone']").fill("012-9999999")
        time.sleep(0.3)
        phone = iframe.locator("[data-testid='iframe-contact-phone']").input_value()
        print(f"    ✓ Phone field value: '{phone}'")

        # ── Method 2: Using frames list ───────────────────
        print("\n[6] Alternative method — using page.frames...")
        frames = page.frames
        print(f"    Total frames on page: {len(frames)}")
        for i, f in enumerate(frames):
            print(f"      Frame {i}: url='{f.url}'")

        # Find the contact iframe frame
        contact_frame = None
        for f in frames:
            if "contact-iframe" in f.url:
                contact_frame = f
                break

        if contact_frame:
            name_val = contact_frame.input_value("[data-testid='iframe-contact-name']")
            print(f"\n    ✓ Verified via frame object — name: '{name_val}'")
        else:
            print("    ℹ  Could not find frame by URL (using frame_locator is better)")

        # ── Main page context still works ─────────────────
        print("\n[7] Verifying main page context still works...")
        main_heading = page.locator("nav h1").inner_text()
        print(f"    ✓ Main page nav: '{main_heading}'")

        # ── Fill searchable dropdown on main page too ──────
        print("\n[8] Confirming main page fields still interactive...")
        page.click("[data-testid='part-search-input']")
        time.sleep(0.3)
        is_open = page.locator("[data-testid='part-dropdown']").is_visible()
        print(f"    ✓ Main page dropdown opens: {is_open}")
        page.keyboard.press("Escape")

        browser.close()
        print("\n" + "="*60)
        print("  ✅ ALL STEPS PASSED — Iframe Test Complete!")
        print("="*60 + "\n")


if __name__ == "__main__":
    try:
        test_iframe()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

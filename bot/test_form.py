"""
test_form.py — Quick test that verifies the dummy portal form
can be filled and submitted successfully.

Run:  python test_form.py
"""

from playwright.sync_api import sync_playwright

LOGIN_URL  = "http://localhost/mini-automation/dummy-portal/login.php"
USERNAME   = "admin"
PASSWORD   = "123456"
TEST_PART  = "ENG-001"
TEST_QTY   = "50"
TEST_BATCH = "BATCH-TEST-01"
TEST_VENDOR= "VND-001"


def test():
    with sync_playwright() as pw:
        browser = pw.chromium.launch(headless=False, slow_mo=600)
        page    = browser.new_page()

        print("\n[1] Opening login page…")
        page.goto(LOGIN_URL, wait_until="domcontentloaded")
        page.wait_for_selector("#username", state="visible", timeout=8000)
        print("    ✓ login page loaded")

        print("[2] Logging in…")
        page.fill("#username", USERNAME)
        page.fill("#password", PASSWORD)
        page.click("#login-btn")
        page.wait_for_url("**/barcode-form.php", timeout=10000)
        print("    ✓ logged in")

        print("[3] Waiting for form fields…")
        page.wait_for_selector("#part_no",     state="attached", timeout=8000)
        page.wait_for_selector("#vendor_code", state="attached", timeout=8000)
        print("    ✓ form ready")

        # Print available options so we can see what values are present
        part_options = page.evaluate("""
            () => Array.from(document.querySelectorAll('#part_no option'))
                       .map(o => ({value: o.value, text: o.text}))
        """)
        print("    part_no options:", part_options)

        vendor_options = page.evaluate("""
            () => Array.from(document.querySelectorAll('#vendor_code option'))
                       .map(o => ({value: o.value, text: o.text}))
        """)
        print("    vendor_code options:", vendor_options)

        print(f"[4] Selecting part_no = {TEST_PART}…")
        page.select_option("#part_no", value=TEST_PART)
        chosen = page.evaluate("document.getElementById('part_no').value")
        print(f"    ✓ part_no value now: {chosen}")

        print(f"[5] Filling quantity = {TEST_QTY}…")
        page.fill("#quantity", TEST_QTY)

        print(f"[6] Filling batch_no = {TEST_BATCH}…")
        page.fill("#batch_no", TEST_BATCH)

        print(f"[7] Selecting vendor_code = {TEST_VENDOR}…")
        page.select_option("#vendor_code", value=TEST_VENDOR)
        chosen_v = page.evaluate("document.getElementById('vendor_code').value")
        print(f"    ✓ vendor_code value now: {chosen_v}")

        print("[8] Submitting form…")
        page.click("#submit-btn")
        page.wait_for_url("**/generate-barcode.php", timeout=12000)
        page.wait_for_selector("#download-btn", state="visible", timeout=8000)
        print("    ✓ barcode generated page loaded!")

        print("[9] Downloading file…")
        with page.expect_download(timeout=15000) as dl:
            page.click("#download-btn")
        d = dl.value
        print(f"    ✓ downloaded: {d.suggested_filename}")
        d.delete()

        browser.close()
        print("\n✅ ALL STEPS PASSED — portal form works correctly!\n")


if __name__ == "__main__":
    try:
        test()
    except Exception as e:
        import traceback
        print(f"\n❌ TEST FAILED: {e}")
        traceback.print_exc()

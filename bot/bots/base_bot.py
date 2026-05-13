"""
base_bot.py — Shared bot utilities
All company bots inherit from BaseBot.
"""
import os
import time
import logging
import traceback
from pathlib import Path
from playwright.sync_api import TimeoutError as PWTimeout

log = logging.getLogger(__name__)

SCREENSHOT_DIR = os.path.join(os.path.dirname(__file__), '..', 'screenshots')
DOWNLOAD_DIR   = os.path.join(os.path.dirname(__file__), '..', 'downloads')
LOG_DIR        = os.path.join(os.path.dirname(__file__), '..', 'logs')

Path(SCREENSHOT_DIR).mkdir(parents=True, exist_ok=True)
Path(DOWNLOAD_DIR).mkdir(parents=True, exist_ok=True)
Path(LOG_DIR).mkdir(parents=True, exist_ok=True)


class BaseBot:
    """Base class with shared Playwright helpers for all portal bots."""

    COMPANY = "base"

    def __init__(self, page, job_id: int):
        self.page   = page
        self.job_id = job_id
        self.timings = {}

    # ─── Screenshots ────────────────────────────────────────
    def shot(self, label: str) -> None:
        ts   = time.strftime("%Y%m%d_%H%M%S")
        path = os.path.join(SCREENSHOT_DIR,
                            f"{self.COMPANY}_JOB{self.job_id:04d}_{label}_{ts}.png")
        try:
            self.page.screenshot(path=path, full_page=True)
            log.info("  📸 %s", path)
        except Exception as e:
            log.warning("  screenshot failed: %s", e)

    # ─── Timing ─────────────────────────────────────────────
    def start_timer(self, label: str):
        self.timings[label] = time.time()

    def end_timer(self, label: str) -> float:
        elapsed = time.time() - self.timings.get(label, time.time())
        log.info("  ⏱  %s took %.2fs", label, elapsed)
        return elapsed

    # ─── URL helpers ────────────────────────────────────────
    def url(self) -> str:
        try:
            return self.page.url
        except Exception:
            return "unknown"

    def assert_url_contains(self, substr: str, label: str = ""):
        if substr not in self.url():
            self.shot(f"ERR_wrong_url_{label}")
            raise RuntimeError(
                f"Expected URL to contain '{substr}' but got: {self.url()}"
            )

    # ─── Wait helpers ────────────────────────────────────────
    def wait_and_click(self, selector: str, timeout: int = 15_000) -> None:
        self.page.wait_for_selector(selector, state="visible", timeout=timeout)
        self.page.click(selector)

    def wait_and_fill(self, selector: str, value: str, timeout: int = 10_000) -> None:
        self.page.wait_for_selector(selector, state="visible", timeout=timeout)
        self.page.fill(selector, str(value))

    # ─── Normal HTML select ──────────────────────────────────
    def select_dropdown(self, selector: str, value: str, field: str = "") -> str:
        """Select from a plain <select>. Falls back to first option."""
        self.page.wait_for_selector(selector, state="attached", timeout=15_000)
        options = self.page.evaluate(f"""
            () => {{
                const el = document.querySelector('{selector}');
                if (!el) return [];
                return Array.from(el.options)
                    .map(o => o.value).filter(v => v.trim() !== '');
            }}
        """)
        log.info("  [%s] options: %s", field or selector, options)
        if not options:
            self.shot(f"NO_OPTIONS_{field}")
            raise RuntimeError(f"No options in {selector}")
        target = value if value in options else options[0]
        if target != value:
            log.warning("  ⚠  '%s' not in %s → fallback to '%s'", value, field, target)
        self.page.select_option(selector, value=target)
        log.info("  ✓ [%s] → %s", field or selector, target)
        return target

    # ─── Searchable dropdown (Company B style) ───────────────
    def fill_searchable_dropdown(
        self,
        search_input_selector: str,
        option_selector: str,
        search_text: str,
        option_value: str,
        field: str = ""
    ) -> None:
        """
        Handle a custom searchable dropdown:
        1. Click search input to open dropdown
        2. Type search text
        3. Wait for options to appear
        4. Click matching option
        """
        log.info("  [%s] searchable dropdown → '%s'", field, search_text)
        self.page.wait_for_selector(search_input_selector, state="visible", timeout=15_000)
        self.page.click(search_input_selector)
        time.sleep(0.3)
        self.page.fill(search_input_selector, search_text)
        time.sleep(0.5)  # allow filtering

        # Wait for at least one option to appear
        self.page.wait_for_selector(option_selector, state="visible", timeout=10_000)

        # Find the option matching our value
        options = self.page.query_selector_all(option_selector)
        for opt in options:
            code = opt.get_attribute("data-code") or ""
            if option_value in code or option_value in (opt.inner_text() or ""):
                opt.click()
                log.info("  ✓ [%s] selected: %s", field, option_value)
                return

        # Fallback: click first visible option
        if options:
            options[0].click()
            log.warning("  ⚠  '%s' not found in searchable dropdown, clicked first option", option_value)
        else:
            raise RuntimeError(f"No options found in searchable dropdown for {field}")

    # ─── React-style custom dropdown (Company C style) ───────
    def fill_react_dropdown(
        self,
        control_selector: str,
        option_selector: str,
        option_value: str,
        search_selector: str = None,
        field: str = ""
    ) -> None:
        """
        Handle a React-style custom div dropdown:
        1. Click the control to open
        2. Optionally type to search
        3. Click matching option
        """
        log.info("  [%s] react dropdown → '%s'", field, option_value)
        self.page.wait_for_selector(control_selector, state="visible", timeout=15_000)
        self.page.click(control_selector)
        time.sleep(0.4)

        if search_selector:
            try:
                self.page.wait_for_selector(search_selector, state="visible", timeout=3_000)
                self.page.fill(search_selector, option_value)
                time.sleep(0.3)
            except PWTimeout:
                pass

        self.page.wait_for_selector(option_selector, state="visible", timeout=10_000)

        options = self.page.query_selector_all(option_selector)
        for opt in options:
            data_id = opt.get_attribute("data-id") or ""
            text    = opt.inner_text() or ""
            if option_value in data_id or option_value in text:
                opt.click()
                log.info("  ✓ [%s] selected: %s", field, option_value)
                return

        if options:
            options[0].click()
            log.warning("  ⚠  '%s' not found in react dropdown, clicked first", option_value)
        else:
            raise RuntimeError(f"No options in react dropdown for {field}")

    # ─── AJAX dropdown (wait for load) ───────────────────────
    def wait_for_ajax_dropdown(
        self,
        selector: str,
        timeout: int = 30_000,
        field: str = ""
    ) -> None:
        """Wait for an AJAX-loaded dropdown to become populated."""
        log.info("  [%s] waiting for AJAX dropdown...", field)
        start = time.time()
        while time.time() - start < timeout / 1000:
            count = self.page.evaluate(f"""
                () => {{
                    const el = document.querySelector('{selector}');
                    if (!el) return 0;
                    return el.options.length;
                }}
            """)
            if count > 1:
                log.info("  ✓ [%s] AJAX dropdown loaded (%d options) in %.1fs",
                         field, count, time.time() - start)
                return
            time.sleep(0.5)
        raise RuntimeError(f"AJAX dropdown '{selector}' did not load within {timeout}ms")

    # ─── Modal handling ──────────────────────────────────────
    def handle_modal(
        self,
        trigger_selector: str,
        modal_selector: str,
        confirm_selector: str,
        timeout: int = 10_000
    ) -> None:
        """Click trigger, wait for modal, click confirm."""
        log.info("  Handling modal popup...")
        self.page.click(trigger_selector)
        self.page.wait_for_selector(modal_selector, state="visible", timeout=timeout)
        time.sleep(0.5)  # allow modal animation
        self.shot("modal_open")
        self.page.click(confirm_selector)
        log.info("  ✓ Modal confirmed")

    # ─── Multi-select ────────────────────────────────────────
    def select_multi(
        self,
        option_selector: str,
        values: list,
        field: str = ""
    ) -> list:
        """Click multiple options in a custom multi-select component."""
        log.info("  [%s] multi-select → %s", field, values)
        self.page.wait_for_selector(option_selector, state="visible", timeout=15_000)
        options = self.page.query_selector_all(option_selector)
        selected = []
        for opt in options:
            code = opt.get_attribute("data-code") or ""
            text = opt.inner_text() or ""
            for v in values:
                if v in code or v in text:
                    opt.click()
                    selected.append(v)
                    time.sleep(0.2)
                    break
        log.info("  ✓ [%s] selected: %s", field, selected)
        return selected

    # ─── OTP ────────────────────────────────────────────────
    def enter_otp(self, otp_selector: str, otp: str, submit_selector: str) -> None:
        """Fill OTP field and submit."""
        log.info("  Entering OTP: %s", otp)
        self.page.wait_for_selector(otp_selector, state="visible", timeout=10_000)
        self.page.fill(otp_selector, otp)
        time.sleep(0.3)
        self.page.click(submit_selector)

    # ─── Download ────────────────────────────────────────────
    def download_file(self, button_selector: str, timeout: int = 25_000) -> str:
        """Click download button and save file. Returns absolute path."""
        log.info("  Downloading file...")
        with self.page.expect_download(timeout=timeout) as dl_info:
            self.page.click(button_selector)
        download  = dl_info.value
        save_path = os.path.join(DOWNLOAD_DIR, download.suggested_filename)
        download.save_as(save_path)
        abs_path  = os.path.abspath(save_path)
        log.info("  ✓ Downloaded → %s", abs_path)
        return abs_path

    # ─── Iframe ──────────────────────────────────────────────
    def get_iframe(self, iframe_selector: str, timeout: int = 10_000):
        """Return the FrameLocator for an iframe."""
        self.page.wait_for_selector(iframe_selector, state="attached", timeout=timeout)
        return self.page.frame_locator(iframe_selector)

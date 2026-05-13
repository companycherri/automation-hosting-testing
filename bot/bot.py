"""
bot.py — Universal Mapping-Driven Automation Bot
═══════════════════════════════════════════════════════════
Run: python bot.py

Flow:
  1. Poll /api/pending-job.php every POLL_INTERVAL seconds
  2. Get job + company config + field mappings
  3. Login to the correct portal based on company config
  4. Fill form fields dynamically from field_mappings
  5. Handle each dropdown type (normal/searchable/ajax/react/multi)
  6. Submit form (with modal if portal_type = 'modal')
  7. Download barcode file
  8. Update job status to success/failed

No hardcoded portal logic — everything comes from the database.
═══════════════════════════════════════════════════════════
"""

import os
import re as _re
import sys
import json
import time
import logging
import traceback
from pathlib import Path
from playwright.sync_api import sync_playwright, TimeoutError as PWTimeout

import db
from config import POLL_INTERVAL, DOWNLOAD_DIR, SCREENSHOT_DIR, HEADLESS, SLOW_MO, LOG_DIR

# ── Ensure runtime directories exist ───────────────────────
Path(DOWNLOAD_DIR).mkdir(parents=True, exist_ok=True)
Path(SCREENSHOT_DIR).mkdir(parents=True, exist_ok=True)
Path(LOG_DIR).mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s  %(levelname)-8s  %(message)s",
    datefmt="%H:%M:%S",
    handlers=[
        logging.StreamHandler(sys.stdout),
        logging.FileHandler(os.path.join(LOG_DIR, "bot.log"), encoding="utf-8"),
    ],
)
log = logging.getLogger(__name__)


# ════════════════════════════════════════════════════════════
# PORTAL ERROR DETECTION
# ════════════════════════════════════════════════════════════

class PortalFieldError(Exception):
    """
    Raised when the portal shows a visible validation error for a filled field,
    or the submit button is disabled due to form validation failure.

    Carries structured data that gets saved to bot_error_logs via db.save_bot_error().
    The bot enters Excel values exactly — it never auto-corrects them.
    """
    def __init__(self, field: str, value: str, portal_message: str,
                 selector: str = "", step: str = "form_fill",
                 error_type: str = "field_validation_error",
                 screenshot: str = ""):
        super().__init__(f"Portal error [{field}]: {portal_message}")
        self.field          = field
        self.value          = value          # exact Excel value that was entered
        self.portal_message = portal_message # text the portal displayed
        self.selector       = selector
        self.step           = step
        self.error_type     = error_type
        self.screenshot     = screenshot     # rel path set after shot() call


def check_field_error(page, selector: str) -> str:
    """
    After filling a field, scan the portal for any visible validation errors
    associated with that field.

    Checks (in order):
      1. aria-invalid="true" on the element
      2. HTML5 browser validationMessage
      3. is-invalid / has-error CSS class on the element
      4. Nearest parent container → .invalid-feedback, .field-error,
         .text-danger, .error, [role="alert"], [data-testid*="error"]
      5. Page-level .alert-danger / .alert-error / .notification-error
      6. Any visible [role="alert"] on the page

    Returns the first error text found, or "" if the field is valid.
    """
    if not selector:
        return ""

    safe = selector.replace("'", "\\'").replace('"', '\\"')
    try:
        msg = page.evaluate(f"""
            () => {{
                const found = [];
                const el = document.querySelector('{safe}');

                if (el) {{
                    // 1. aria-invalid attribute
                    if (el.getAttribute('aria-invalid') === 'true') {{
                        const vm = el.validationMessage;
                        found.push(vm && vm.trim() ? vm.trim() : 'Field marked invalid (aria-invalid)');
                    }}
                    // 2. HTML5 browser validationMessage (set after reportValidity)
                    if (!found.length && el.validationMessage) {{
                        found.push(el.validationMessage.trim());
                    }}
                    // 3. Obvious error classes on the element itself
                    const cls = el.className || '';
                    if (!found.length && /\\b(is-invalid|has-error)\\b/.test(cls)) {{
                        found.push('Field has validation error class');
                    }}
                    // 4. Scan the nearest container for error children
                    const container = (
                        el.closest('.form-group, .grid2 > div, .field-wrap, .field') ||
                        el.parentElement
                    );
                    if (container && !found.length) {{
                        const errSelectors = [
                            '.invalid-feedback', '.field-error', '.error-message',
                            '.text-danger', '.text-error',
                            '[role="alert"]',
                            '[data-testid$="-error"]',
                            '[id$="-error"]',
                            'small.error', 'span.error', 'p.error', 'div.error'
                        ];
                        for (const s of errSelectors) {{
                            const errEl = container.querySelector(s);
                            if (errEl && errEl.offsetParent !== null) {{
                                const t = (errEl.textContent || '').trim();
                                if (t) {{ found.push(t); break; }}
                            }}
                        }}
                    }}
                }}

                // 5. Page-level danger alerts
                if (!found.length) {{
                    for (const s of ['.alert-danger', '.alert-error', '.notification-error',
                                     '.toast-error', '.toast-danger']) {{
                        const a = document.querySelector(s);
                        if (a && a.offsetParent !== null) {{
                            const t = (a.textContent || '').trim();
                            if (t) {{ found.push(t); break; }}
                        }}
                    }}
                }}

                // 6. Any visible [role="alert"] on the page
                if (!found.length) {{
                    document.querySelectorAll('[role="alert"]').forEach(a => {{
                        if (!found.length && a.offsetParent !== null) {{
                            const t = (a.textContent || '').trim();
                            if (t) found.push(t);
                        }}
                    }});
                }}

                return found.length ? found[0] : '';
            }}
        """)
        return (msg or "").strip()
    except Exception as e:
        log.warning("  check_field_error exception (selector=%s): %s", selector, e)
        return ""


def check_submit_disabled(page, submit_sel: str) -> str:
    """
    Before clicking submit, check whether the button is disabled.
    If disabled, scan all visible field-level error messages to explain why.

    Returns error string (non-empty) if submit is disabled, or "" if ready.
    """
    safe = submit_sel.replace("'", "\\'")
    try:
        result = page.evaluate(f"""
            () => {{
                const btn = document.querySelector('{safe}');
                if (!btn || !btn.disabled) return '';

                // Collect all visible error messages on the page
                const errors = [];
                const errSelectors = [
                    '.invalid-feedback', '.field-error', '.error-message',
                    '.text-danger', '.text-error', '[role="alert"]',
                    '[data-testid$="-error"]', '[id$="-error"]',
                    'small.error', 'span.error', 'div.error'
                ];
                for (const s of errSelectors) {{
                    document.querySelectorAll(s).forEach(el => {{
                        if (el.offsetParent !== null) {{
                            const t = (el.textContent || '').trim();
                            if (t && !errors.includes(t)) errors.push(t);
                        }}
                    }});
                }}
                return errors.length
                    ? 'Submit disabled — ' + errors.join('; ')
                    : 'Submit button is disabled (form validation failed)';
            }}
        """)
        return (result or "").strip()
    except Exception as e:
        log.warning("  check_submit_disabled exception: %s", e)
        return ""


# ════════════════════════════════════════════════════════════
# FIELD FILL HANDLERS
# ════════════════════════════════════════════════════════════

def current_url(page) -> str:
    try:    return page.url
    except: return "unknown"


def shot(page, job_id: int, label: str) -> None:
    ts   = time.strftime("%Y%m%d_%H%M%S")
    path = os.path.join(SCREENSHOT_DIR, f"JOB{job_id:04d}_{label}_{ts}.png")
    try:
        page.screenshot(path=path, full_page=True)
        log.info("  📸 %s", path)
        return path
    except Exception as e:
        log.warning("  screenshot failed: %s", e)
        return ""


def select_with_fallback(page, selector: str, value: str, field: str = "") -> str:
    """Select from a plain <select>. Falls back to first available option."""
    page.wait_for_selector(selector, state="attached", timeout=15_000)
    options = page.evaluate(f"""
        () => {{
            const el = document.querySelector('{selector.replace("'", "\\'")}');
            if (!el) return [];
            return Array.from(el.options).map(o => o.value).filter(v => v.trim() !== '');
        }}
    """)
    log.info("  [%s] options: %s", field or selector, options[:5])
    if not options:
        raise RuntimeError(f"No options in select '{selector}'")
    target = value if value in options else options[0]
    if target != value:
        log.warning("  ⚠  '%s' not found in %s → fallback to '%s'", value, field or selector, target)
    page.select_option(selector, value=target)
    log.info("  ✓ [%s] = %s", field or selector, target)
    return target


def fill_searchable_dropdown(page, extra: dict, value: str, field: str = "") -> None:
    """Handle custom searchable dropdown (Company B style)."""
    search_input = extra.get('search_input', '')
    option_sel   = extra.get('option', '')
    hidden_input = extra.get('hidden', '')

    log.info("  [%s] searchable → '%s'", field, value)
    page.wait_for_selector(search_input, state="visible", timeout=15_000)
    page.click(search_input)
    time.sleep(0.3)

    # Type first 3 chars to filter
    search_text = value[:3] if len(value) >= 3 else value
    page.fill(search_input, search_text)
    time.sleep(0.6)

    # Wait for options to appear. If the portal returns no results (e.g. "No parts
    # found"), the option selector never becomes visible → treat as a portal-level
    # rejection of the value, NOT a generic timeout.
    # Leave screenshot="" so run_job()'s PortalFieldError handler takes it via shot().
    try:
        page.wait_for_selector(option_sel, state="visible", timeout=10_000)
    except PWTimeout:
        raise PortalFieldError(
            field          = field,
            value          = value,
            portal_message = (f"No matching option found in dropdown for value '{value}'. "
                              f"The portal returned no results when searching for this value."),
            selector       = option_sel,
            step           = "fill_field",
            error_type     = "dropdown_option_error",
            screenshot     = "",   # run_job() PortalFieldError handler calls shot() when empty
        )

    options = page.query_selector_all(option_sel)
    found = False
    for opt in options:
        code = opt.get_attribute("data-code") or ""
        text = opt.inner_text() or ""
        if value in code or value in text:
            opt.click()
            found = True
            log.info("  ✓ [%s] clicked option: %s", field, value)
            break

    if not found and options:
        options[0].click()
        log.warning("  ⚠  '%s' not found in searchable → clicked first option", value)
    elif not found:
        raise RuntimeError(f"No options in searchable dropdown for '{field}'")

    time.sleep(0.3)


def fill_react_dropdown(page, extra: dict, value: str, field: str = "") -> None:
    """
    Handle React-style custom div dropdown (Company C category style).

    Strategy:
      1. Click the control to open the menu
      2. Wait for options to render (fetched via AJAX on page load)
      3. Try DIRECT attribute match first: [data-id="AUTO"]
      4. If that fails, type in the search box (fires oninput) and retry
      5. If still not found, click the first visible option as fallback
    """
    control = extra.get('control', '')
    search  = extra.get('search', '')
    option  = extra.get('option', '')
    attr    = extra.get('attr', 'data-id')

    log.info("  [%s] react dropdown → '%s'", field, value)

    # Open the dropdown
    page.wait_for_selector(control, state="visible", timeout=15_000)
    page.click(control)
    time.sleep(0.5)

    # Wait for at least one option to be visible in the open menu
    page.wait_for_selector(option, state="visible", timeout=12_000)

    # ── Strategy 1: direct attribute selector ────────────────
    direct_sel = f"{option}[{attr}='{value}']"
    if page.locator(direct_sel).count() > 0:
        page.click(direct_sel)
        log.info("  ✓ [%s] direct attr match: [%s='%s']", field, attr, value)
        time.sleep(0.4)
        return

    # ── Strategy 2: case-insensitive text/attr scan ──────────
    options = page.query_selector_all(option)
    for opt in options:
        attr_val = opt.get_attribute(attr) or ""
        text     = opt.inner_text() or ""
        if (value.upper() == attr_val.upper() or
                value.lower() in text.lower()):
            opt.click()
            log.info("  ✓ [%s] text match: '%s'", field, text.strip())
            time.sleep(0.4)
            return

    # ── Strategy 3: type in search box to filter, then retry ─
    if search:
        try:
            page.wait_for_selector(search, state="visible", timeout=3_000)
            # Use type() instead of fill() to properly fire oninput events
            page.triple_click(search)
            page.type(search, value[:4], delay=80)
            time.sleep(0.5)
            options = page.query_selector_all(option)
            for opt in options:
                attr_val = opt.get_attribute(attr) or ""
                text     = opt.inner_text() or ""
                if value.upper() in attr_val.upper() or value.lower() in text.lower():
                    opt.click()
                    log.info("  ✓ [%s] search+click: '%s'", field, text.strip())
                    time.sleep(0.4)
                    return
        except PWTimeout:
            log.warning("  ⚠  search input not found for [%s]", field)

    # ── Fallback: click first option ─────────────────────────
    options = page.query_selector_all(option)
    if options:
        text = options[0].inner_text() or "?"
        options[0].click()
        log.warning("  ⚠  '%s' not found → clicked first: '%s'", value, text.strip())
        time.sleep(0.4)
    else:
        raise RuntimeError(f"React dropdown '{field}': no options found after open")


def wait_for_ajax_select(page, selector: str, loading_selector: str = "", field: str = "") -> None:
    """Wait for an AJAX-loaded select to be populated."""
    if loading_selector:
        try:
            page.wait_for_selector(loading_selector, state="hidden", timeout=30_000)
        except PWTimeout:
            log.warning("  ⚠  AJAX loading indicator timeout for %s", field)

    # Also poll for options
    deadline = time.time() + 30
    while time.time() < deadline:
        count = page.evaluate(f"""
            () => {{ const el = document.querySelector('{selector.replace("'","\\'")}');
                     return el ? el.options.length : 0; }}
        """)
        if count > 1:
            log.info("  ✓ [%s] AJAX dropdown loaded (%d opts)", field, count)
            return
        time.sleep(0.5)
    raise RuntimeError(f"AJAX dropdown '{selector}' did not load in 30s")


def fill_multi_select(page, extra: dict, values_str: str, field: str = "") -> list:
    """Handle custom multi-select component (Company C vendors)."""
    option_sel = extra.get('option', '')
    attr       = extra.get('attr', 'data-code')
    values     = [v.strip() for v in values_str.split(',') if v.strip()]

    log.info("  [%s] multi-select → %s", field, values)
    page.wait_for_selector(option_sel, state="visible", timeout=15_000)
    options = page.query_selector_all(option_sel)

    selected = []
    for opt in options:
        attr_val = opt.get_attribute(attr) or ""
        for v in values:
            if v in attr_val:
                opt.click()
                selected.append(v)
                time.sleep(0.2)
                break

    if not selected and options:
        options[0].click()
        log.warning("  ⚠  No vendor matched %s → clicked first", values)
        selected = ['first_option']

    log.info("  ✓ [%s] selected %d vendors", field, len(selected))
    return selected


def resolve_value(field_key: str, mapping: dict, job: dict) -> str:
    """
    Get the value for a field.
    If the job has no value for field_key, check extra_config for a
    'derive_from' instruction to compute the value from another field.

    Supported transforms:
      split_prefix  — take the part before the first separator
                      e.g. part_no="AUTO-ENG-01" → "AUTO"
      split_suffix  — take everything after the first separator
      value         — use source field value as-is
    """
    value = str(job.get(field_key, '') or '').strip()
    if value:
        return value

    # Try derive_from in extra_config
    extra_raw = mapping.get('extra_config', '') or ''
    try:
        extra = json.loads(extra_raw) if extra_raw.strip() else {}
    except Exception:
        extra = {}

    derive_from = extra.get('derive_from', '')
    if not derive_from:
        return value

    source = str(job.get(derive_from, '') or '').strip()
    transform = extra.get('transform', 'value')
    sep       = extra.get('separator', '-')

    if transform == 'split_prefix':
        prefix     = source.split(sep)[0] if source else ''
        value_map  = extra.get('value_map', {})
        # value_map translates prefix → canonical value (e.g. "GER" → "MECH")
        value = value_map.get(prefix, prefix) if value_map else prefix
    elif transform == 'split_suffix':
        parts = source.split(sep, 1)
        value = parts[1] if len(parts) > 1 else source
    else:
        value = source

    if value:
        log.info("  [%s] derived from '%s' → '%s'", field_key, derive_from, value)
    return value


def fill_field(page, job_id: int, mapping: dict, value: str) -> None:
    """Dispatch field fill based on field_type / dropdown_type."""
    field_key     = mapping.get('field_key', '')
    field_type    = mapping.get('field_type', 'text')
    dropdown_type = mapping.get('dropdown_type', '') or ''
    selector      = mapping.get('selector', '') or ''
    required      = int(mapping.get('required', 0))
    extra_raw     = mapping.get('extra_config', '') or ''

    try:
        extra = json.loads(extra_raw) if extra_raw.strip() else {}
    except Exception:
        extra = {}

    if not value and not required:
        return  # Skip optional empty fields
    if not value and required:
        log.warning("  ⚠  Required field '%s' is empty — skipping", field_key)
        return

    log.info("  Filling [%s] type=%s val='%s'", field_key, dropdown_type or field_type, value[:50])

    try:
        # Text / Number / Textarea
        if field_type in ('text', 'number', 'textarea'):
            page.wait_for_selector(selector, state="visible", timeout=10_000)
            page.fill(selector, str(value))

        # Date
        elif field_type == 'date':
            page.wait_for_selector(selector, state="visible", timeout=10_000)
            page.fill(selector, str(value))

        # Normal HTML select
        elif field_type == 'select' and dropdown_type == 'normal_select':
            select_with_fallback(page, selector, str(value), field_key)

        # Searchable dropdown
        elif dropdown_type == 'searchable_dropdown':
            fill_searchable_dropdown(page, extra, str(value), field_key)

        # AJAX select (waits for load, then selects)
        elif dropdown_type == 'ajax_select':
            loading = extra.get('loading', '')
            wait_for_ajax_select(page, selector, loading, field_key)
            select_with_fallback(page, selector, str(value), field_key)

        # Dependent select (AJAX triggered by previous field)
        elif dropdown_type == 'dependent_select':
            # Wait for the select element itself to appear and become visible
            page.wait_for_selector(selector, state="visible", timeout=15_000)

            # Wait for the .loaded class (set by JS after AJAX completes)
            wait_sel = extra.get('wait_selector', '')
            if wait_sel:
                try:
                    page.wait_for_selector(wait_sel, state="attached", timeout=15_000)
                    log.info("  ✓ [%s] .loaded class detected", field_key)
                except PWTimeout:
                    log.warning("  ⚠  .loaded class timeout for [%s] — continuing anyway", field_key)

            # Also wait for options to be populated (> 1 means past the placeholder)
            try:
                page.wait_for_function(
                    f"() => {{ const el = document.querySelector('{selector.replace(chr(39), chr(92)+chr(39))}'); return el && el.options.length > 1; }}",
                    timeout=15_000
                )
            except PWTimeout:
                log.warning("  ⚠  Dependent select options never loaded for [%s]", field_key)

            select_with_fallback(page, selector, str(value), field_key)

        # React-style custom dropdown
        elif dropdown_type == 'react_dropdown':
            fill_react_dropdown(page, extra, str(value), field_key)

        # Multi-select
        elif dropdown_type == 'multi_select':
            fill_multi_select(page, extra, str(value), field_key)

        # File upload — Playwright set_input_files from server path
        elif field_type == 'file_upload':
            file_path = os.path.normpath(value)
            fname     = os.path.basename(file_path)

            # ── 1. Verify file exists on disk ─────────────────
            log.info("  [%s] file_upload → checking path: %s", field_key, file_path)
            if not os.path.exists(file_path):
                db.add_log(job_id, "FILE_UPLOAD_FAILED",
                           f"File not found on disk: {value}")
                raise RuntimeError(f"File not found: {value}")

            file_size = os.path.getsize(file_path)
            db.add_log(job_id, "FILE_PATH_FOUND",
                       f"File verified: {fname} ({file_size} bytes) at {file_path}")
            log.info("  ✓ File exists: %s (%d bytes)", fname, file_size)

            # ── 2. Verify selector exists ──────────────────────
            log.info("  Waiting for file input: %s", selector)
            try:
                page.wait_for_selector(selector, state="attached", timeout=10_000)
                db.add_log(job_id, "FILE_SELECTOR_FOUND",
                           f"Input selector ready: {selector}")
                log.info("  ✓ File input selector found: %s", selector)
            except PWTimeout:
                db.add_log(job_id, "FILE_UPLOAD_FAILED",
                           f"Input selector not found: {selector}")
                raise RuntimeError(f"File input selector not found: {selector}")

            # ── 3. Upload via Playwright ───────────────────────
            db.add_log(job_id, "FILE_UPLOADING",
                       f"Uploading '{fname}' → portal input '{selector}'")
            log.info("  Uploading '%s' into portal via set_input_files…", fname)
            page.set_input_files(selector, file_path)
            time.sleep(0.4)

            # ── 4. JS verification: confirm files[0].name ─────
            safe_sel = selector.replace("'", "\\'")
            verified_name = page.evaluate(f"""
                () => {{
                    const el = document.querySelector('{safe_sel}');
                    return el && el.files && el.files.length > 0 ? el.files[0].name : '';
                }}
            """)

            if verified_name:
                db.add_log(job_id, "FILE_UPLOAD_CONFIRMED",
                           f"Portal input confirmed: files[0].name = '{verified_name}'")
                log.info("  ✓ [%s] portal confirmed: files[0].name = '%s'",
                         field_key, verified_name)
            else:
                db.add_log(job_id, "FILE_UPLOAD_WARNING",
                           f"JS check: files[0].name empty after set_input_files "
                           f"(selector={selector}) — upload may still succeed")
                log.warning("  ⚠  [%s] JS files[0].name empty — proceeding anyway",
                            field_key)

            # ── 5. Also check for visible filename label in portal ──
            label_sel = selector.replace(
                'data-testid="upload-file-', 'id="upload-label-'
            ).rstrip(']') + ']'
            # Simpler: try to find an element with id=upload-label-N
            slot_match = None
            m = _re.search(r'upload-file-(\d)', selector)
            if m:
                label_id  = f"#upload-label-{m.group(1)}"
                label_txt = ""
                try:
                    if page.locator(label_id).count() > 0:
                        label_txt = page.text_content(label_id, timeout=2_000) or ""
                except Exception:
                    pass
                if fname.lower() in label_txt.lower():
                    db.add_log(job_id, "FILE_NAME_VISIBLE",
                               f"Portal label shows: '{label_txt.strip()}'")
                    log.info("  ✓ [%s] filename visible in portal: '%s'",
                             field_key, label_txt.strip())

            # ── 6. Screenshot after file is set ───────────────
            shot(page, job_id, f"file_upload_{field_key}")

            db.add_log(job_id, "FILE_UPLOADED",
                       f"Complete: '{fname}' → '{selector}'")

        # Checkbox (terms etc.)
        elif field_type == 'checkbox':
            if str(value).lower() not in ('0', 'false', 'no', ''):
                page.check(selector)

        # Radio
        elif field_type == 'radio':
            radio_sel = f"{selector}[value='{value}']"
            if page.locator(radio_sel).count() > 0:
                page.click(radio_sel)

        else:
            log.warning("  Unknown field_type=%s dropdown_type=%s for '%s'", field_type, dropdown_type, field_key)

    except PortalFieldError:
        raise  # already structured — let run_job() handle it
    except PWTimeout:
        raise  # propagates as PWTimeout → caught by run_job's except PWTimeout as timeout_error
    except Exception as e:
        log.warning("  Field '%s' fill error: %s", field_key, e)
        if required:
            raise


# ════════════════════════════════════════════════════════════
# LOGIN HANDLERS
# ════════════════════════════════════════════════════════════

def do_login(page, job_id: int, company: dict) -> None:
    """Login to portal based on company config."""
    login_url     = company.get('login_url', '')
    form_url_hint = company.get('form_url', '').split('/')[-1]  # e.g. "form.php"
    user_sel      = company.get('login_username_selector', '#username')
    pass_sel      = company.get('login_password_selector', '#password')
    submit_sel    = company.get('login_submit_selector', '#login-btn')
    username      = company.get('username', '')
    password      = company.get('password', '')
    login_type    = company.get('login_type', 'simple')

    log.info("  LOGIN → %s [type=%s]", login_url, login_type)
    db.add_log(job_id, "LOGIN_START", f"Opening {login_url}")

    page.goto(login_url, wait_until="domcontentloaded", timeout=30_000)
    shot(page, job_id, "01_login_page")

    page.wait_for_selector(user_sel, state="visible", timeout=10_000)
    page.fill(user_sel, username)
    page.fill(pass_sel, password)
    shot(page, job_id, "02_login_filled")

    page.click(submit_sel)

    # Wait for redirect to form page
    timeout = 20_000 if login_type == 'spinner' else 15_000
    page.wait_for_url(f"**/{form_url_hint}", timeout=timeout)

    if "login" in current_url(page).lower():
        shot(page, job_id, "03_login_STUCK")
        raise RuntimeError(f"Login failed — stuck at: {current_url(page)}")

    log.info("  ✓ LOGIN OK → %s", current_url(page))
    db.add_log(job_id, "LOGIN_SUCCESS", f"Redirected to {current_url(page)}")
    shot(page, job_id, "03_logged_in")


# ════════════════════════════════════════════════════════════
# FORM FILL — SIMPLE PORTAL
# ════════════════════════════════════════════════════════════

def _check_and_raise_field_error(page, job_id: int, field_key: str,
                                  value: str, selector: str,
                                  field_type: str, step: str) -> None:
    """
    After filling a field, check whether the portal shows any validation error.
    Skips: file_upload fields and fields with empty selectors (searchable/react/multi).
    If an error is found, takes a screenshot and raises PortalFieldError.
    """
    # Skip types that don't have a simple selector to check against
    skip_types = {'file_upload', 'searchable_dropdown', 'react_dropdown', 'multi_select'}
    if not selector or field_type in skip_types:
        return

    db.add_log(job_id, "FIELD_ERROR_CHECK_STARTED",
               f"Checking portal for errors on field '{field_key}'")

    portal_err = check_field_error(page, selector)
    if not portal_err:
        db.add_log(job_id, "FIELD_ENTRY_DONE",
                   f"'{field_key}' entered OK, no portal errors detected")
        return

    # Error found — take screenshot, then raise
    log.warning("  ❌ FIELD_ERROR_FOUND [%s]: %s", field_key, portal_err)
    db.add_log(job_id, "FIELD_ERROR_FOUND",
               f"field='{field_key}' excel_value='{value}' error='{portal_err}'")

    shot_path = shot(page, job_id, f"ERR_field_{field_key}")
    shot_rel  = (f"bot/{shot_path}".replace("\\", "/")
                 if shot_path and not shot_path.startswith("bot/") else shot_path)

    raise PortalFieldError(
        field=field_key,
        value=str(value),
        portal_message=portal_err,
        selector=selector,
        step=step,
        error_type="field_validation_error",
        screenshot=shot_rel or "",
    )


def fill_simple_form(page, job_id: int, job: dict, mappings: list, company: dict) -> None:
    """Fill form on a single-page portal (Company A / Company B)."""
    submit_sel    = company.get('form_submit_selector', '#submit-btn')
    portal_type   = company.get('portal_type', 'simple')
    extra_config  = json.loads(company.get('extra_config') or '{}')

    # Wait for form to be ready
    page.wait_for_selector(submit_sel, state="visible", timeout=15_000)
    shot(page, job_id, "04_form_ready")
    db.add_log(job_id, "FORM_OPEN", "Form page loaded")

    for mapping in mappings:
        field_key  = mapping.get('field_key', '')
        field_type = mapping.get('field_type', 'text')
        selector   = mapping.get('selector', '')
        value      = resolve_value(field_key, mapping, job)

        db.add_log(job_id, "FIELD_ENTRY_STARTED",
                   f"Entering '{field_key}' = '{str(value)[:80]}'")
        fill_field(page, job_id, mapping, value)

        # After entry: check portal for visible validation errors
        _check_and_raise_field_error(
            page, job_id, field_key, str(value), selector, field_type, "form_fill"
        )
        db.add_log(job_id, "FIELD_ENTRY_DONE", f"'{field_key}' accepted by portal")

    shot(page, job_id, "05_form_filled")
    db.add_log(job_id, "FORM_FILLED", "All fields entered — checking submit readiness")

    # ── Check submit button state before clicking ─────────
    db.add_log(job_id, "SUBMIT_CHECK", f"Checking if '{submit_sel}' is enabled")
    sub_err = check_submit_disabled(page, submit_sel)
    if sub_err:
        log.warning("  ❌ SUBMIT_DISABLED: %s", sub_err)
        db.add_log(job_id, "SUBMIT_DISABLED_FOUND", sub_err)
        err_shot     = shot(page, job_id, "ERR_submit_disabled")
        err_shot_rel = (f"bot/{err_shot}".replace("\\", "/")
                        if err_shot and not err_shot.startswith("bot/") else err_shot)
        raise PortalFieldError(
            field="submit",
            value="",
            portal_message=sub_err,
            selector=submit_sel,
            step="form_submit",
            error_type="submit_disabled",
            screenshot=err_shot_rel or "",
        )

    # ── Submit the form ────────────────────────────────────
    if portal_type == 'modal':
        modal_cfg = extra_config.get('modal', {})
        overlay   = modal_cfg.get('overlay', '.modal-overlay')
        confirm   = modal_cfg.get('confirm', '[data-testid="confirm-order"]')

        log.info("  Modal portal → clicking submit, waiting for modal")
        db.add_log(job_id, "MODAL_TRIGGER", "Clicking submit to open modal")
        page.click(submit_sel)
        page.wait_for_selector(overlay, state="visible", timeout=10_000)
        time.sleep(0.5)
        shot(page, job_id, "05b_modal_open")
        page.click(confirm)
        db.add_log(job_id, "MODAL_CONFIRMED", "Modal confirmed")
    else:
        log.info("  Submitting form → %s", submit_sel)
        page.click(submit_sel)


# ════════════════════════════════════════════════════════════
# FORM FILL — MULTI-STEP PORTAL (Company C)
# ════════════════════════════════════════════════════════════

def fill_multistep_form(page, job_id: int, job: dict, mappings: list, company: dict) -> None:
    """Fill multi-step form (Company C). Steps driven by extra_config."""
    extra_config = json.loads(company.get('extra_config') or '{}')
    steps_config = extra_config.get('steps', [])
    submit_sel   = company.get('form_submit_selector', '[data-testid="final-submit"]')
    terms_sel    = extra_config.get('terms_selector', '')

    # Group mappings by step
    step_mappings = {}
    for m in mappings:
        s = int(m.get('step_no', 1))
        step_mappings.setdefault(s, []).append(m)

    db.add_log(job_id, "FORM_OPEN", "Multi-step form loaded")
    shot(page, job_id, "04_form_step1")

    for step_cfg in steps_config:
        step_no   = step_cfg.get('step', 1)
        panel_sel = step_cfg.get('panel', '')
        next_sel  = step_cfg.get('next', '')

        # Wait for this step's panel
        if panel_sel:
            page.wait_for_selector(panel_sel, state="visible", timeout=10_000)

        log.info("  STEP %d: filling %d fields", step_no, len(step_mappings.get(step_no, [])))
        db.add_log(job_id, f"STEP_{step_no}_START", f"Filling step {step_no}")

        for mapping in step_mappings.get(step_no, []):
            field_key  = mapping.get('field_key', '')
            field_type = mapping.get('field_type', 'text')
            selector   = mapping.get('selector', '')
            value      = resolve_value(field_key, mapping, job)

            db.add_log(job_id, "FIELD_ENTRY_STARTED",
                       f"step{step_no} '{field_key}' = '{str(value)[:80]}'")
            fill_field(page, job_id, mapping, value)

            # After entry: check portal for visible validation errors
            _check_and_raise_field_error(
                page, job_id, field_key, str(value), selector,
                field_type, f"step_{step_no}"
            )
            db.add_log(job_id, "FIELD_ENTRY_DONE",
                       f"step{step_no} '{field_key}' accepted by portal")

        shot(page, job_id, f"0{3+step_no}_step{step_no}_filled")

        # Check terms on last step
        if not next_sel and terms_sel:
            try:
                page.check(terms_sel)
                db.add_log(job_id, "TERMS_CHECKED", "Terms checkbox checked")
            except Exception as e:
                log.warning("  Terms checkbox: %s", e)

        # Click next / submit
        if next_sel:
            page.click(next_sel)
            time.sleep(0.5)
            db.add_log(job_id, f"STEP_{step_no}_NEXT", f"Clicked Next on step {step_no}")
        elif step_cfg.get('submit'):
            # Wait for submit button to become enabled (terms must be checked)
            safe_sub = submit_sel.replace("'", "\\'")
            try:
                page.wait_for_function(
                    f"() => !document.querySelector('{safe_sub}')?.disabled",
                    timeout=8_000
                )
            except PWTimeout:
                log.warning("  Submit button still disabled after terms check")

            # Check if submit is still disabled (indicates a field error)
            db.add_log(job_id, "SUBMIT_CHECK",
                       f"Checking if '{submit_sel}' is enabled before final submit")
            sub_err = check_submit_disabled(page, submit_sel)
            if sub_err:
                log.warning("  ❌ SUBMIT_DISABLED_FOUND (multistep): %s", sub_err)
                db.add_log(job_id, "SUBMIT_DISABLED_FOUND", sub_err)
                err_shot     = shot(page, job_id, "ERR_submit_disabled")
                err_shot_rel = (f"bot/{err_shot}".replace("\\", "/")
                                if err_shot and not err_shot.startswith("bot/") else err_shot)
                raise PortalFieldError(
                    field="submit", value="",
                    portal_message=sub_err,
                    selector=submit_sel,
                    step=f"step_{step_no}_submit",
                    error_type="submit_disabled",
                    screenshot=err_shot_rel or "",
                )

            log.info("  Submitting final step")
            page.click(submit_sel)
            db.add_log(job_id, "FORM_SUBMIT", "Final submit clicked")


# ════════════════════════════════════════════════════════════
# MAIN JOB RUNNER
# ════════════════════════════════════════════════════════════

def run_job(playwright, job_data: dict) -> None:
    job      = job_data["job"]
    company  = job_data.get("company") or {}
    mappings = job_data.get("mappings") or []
    job_id   = int(job["id"])
    cname    = job.get("company_name", "Unknown")

    log.info("━" * 60)
    log.info("JOB-%04d | %s | part=%s | qty=%s | vendor=%s",
             job_id, cname, job["part_no"], job["quantity"], job["vendor_code"])
    log.info("━" * 60)
    db.add_log(job_id, "BOT_START", f"Bot picked up JOB-{job_id:04d}")

    # If no company config in DB, record a structured bot error and abort
    if not company:
        log.warning("  ⚠  No company config found for '%s' — aborting", cname)
        portal_msg = (
            f"No portal configuration exists for company '{cname}'. "
            f"Add this company to the companies table and run update-schema.php."
        )
        db.save_bot_error(
            job_id       = job_id,
            company_name = cname,
            step         = "company_lookup",
            field_key    = "company_name",
            excel_value  = cname,
            portal_error = portal_msg,
            error_type   = "company_not_found",
        )
        db.add_log(job_id, "JOB_FAILED_WITH_PORTAL_ERROR",
                   f"company_not_found: {cname}")
        return

    portal_type = company.get('portal_type', 'simple')
    form_success = company.get('form_success_url', '**/generate.php')
    dl_selector  = company.get('download_selector', '#download-btn')

    browser = playwright.chromium.launch(headless=HEADLESS, slow_mo=SLOW_MO)
    context = browser.new_context(accept_downloads=True)
    page    = context.new_page()
    page.set_default_timeout(15_000)

    try:
        # ── Login ──────────────────────────────────────────
        do_login(page, job_id, company)

        # ── Fill form ──────────────────────────────────────
        db.add_log(job_id, "FORM_START", f"portal_type={portal_type}, fields={len(mappings)}")

        if portal_type == 'multistep':
            fill_multistep_form(page, job_id, job, mappings, company)
        else:
            fill_simple_form(page, job_id, job, mappings, company)

        # ── Wait for success page ──────────────────────────
        log.info("  Waiting for success page: %s", form_success)
        page.wait_for_url(form_success, timeout=20_000)
        log.info("  URL after submit: %s", current_url(page))

        page.wait_for_selector(dl_selector, state="visible", timeout=15_000)
        db.add_log(job_id, "BARCODE_GENERATED", f"Success page: {current_url(page)}")
        shot(page, job_id, "07_generated")
        log.info("  ✓ Barcode generated")

        # ── Download ───────────────────────────────────────
        db.add_log(job_id, "DOWNLOAD_START", "Downloading file")
        with page.expect_download(timeout=25_000) as dl_info:
            page.click(dl_selector)

        download  = dl_info.value
        save_path = os.path.join(DOWNLOAD_DIR, download.suggested_filename)
        download.save_as(save_path)
        abs_path  = os.path.abspath(save_path)
        rel_path  = f"bot/{save_path}".replace("\\", "/")

        log.info("  ✓ Downloaded → %s", abs_path)
        db.add_log(job_id, "DOWNLOAD_DONE", f"Saved: {abs_path}")
        final_shot = shot(page, job_id, "08_downloaded")
        shot_rel   = f"bot/{final_shot}".replace("\\", "/") if final_shot else ""

        # ── Mark success ───────────────────────────────────
        db.update_job_status(job_id, "success", file_path=rel_path, screenshot=shot_rel)
        db.add_log(job_id, "JOB_SUCCESS", f"JOB-{job_id:04d} completed ✓")
        log.info("  ✅ JOB-%04d SUCCESS", job_id)

    except PortalFieldError as exc:
        # ── Portal showed a visible validation error for a field ──
        # The bot entered the Excel value exactly; the portal rejected it.
        # save_bot_error writes to bot_error_logs AND marks job as failed.
        log.error("  ❌ PORTAL_FIELD_ERROR [%s]: %s", exc.field, exc.portal_message)

        # Use screenshot already taken during detection, or take a fresh one
        spath_rel = exc.screenshot
        if not spath_rel:
            raw = shot(page, job_id, f"ERR_portal_{exc.field}")
            spath_rel = (f"bot/{raw}".replace("\\", "/")
                         if raw and not raw.startswith("bot/") else raw or "")

        db.save_bot_error(
            job_id=job_id,
            company_name=cname,
            step=exc.step,
            field_key=exc.field,
            excel_value=exc.value,
            portal_error=exc.portal_message,
            error_type=exc.error_type,
            selector=exc.selector,
            screenshot=spath_rel,
            page_url=current_url(page),
        )
        db.add_log(job_id, "JOB_FAILED_WITH_PORTAL_ERROR",
                   f"step='{exc.step}' field='{exc.field}' "
                   f"excel='{exc.value}' portal_msg='{exc.portal_message}'")
        log.error("  ❌ JOB-%04d FAILED — portal error on field '%s'",
                  job_id, exc.field)
        # Do NOT re-raise — save_bot_error already marked job failed.
        # The finally block still runs to close browser.

    except PWTimeout as exc:
        msg = f"Timeout — URL={current_url(page)} — {exc}"
        log.error("  ❌ %s", msg)
        spath = shot(page, job_id, "ERR_timeout")
        spath_rel = f"bot/{spath}".replace("\\", "/") if spath else ""
        db.save_bot_error(
            job_id=job_id, company_name=cname,
            step="timeout", field_key="", excel_value="",
            portal_error=msg, error_type="timeout_error",
            screenshot=spath_rel, page_url=current_url(page),
        )
        db.add_log(job_id, "JOB_FAILED", msg)

    except Exception as exc:
        msg = str(exc)
        log.error("  ❌ %s\n%s", msg, traceback.format_exc())
        spath = shot(page, job_id, "ERR_exception")
        spath_rel = f"bot/{spath}".replace("\\", "/") if spath else ""
        db.save_bot_error(
            job_id=job_id, company_name=cname,
            step="unknown", field_key="", excel_value="",
            portal_error=msg, error_type="unknown_error",
            screenshot=spath_rel, page_url=current_url(page),
        )
        db.add_log(job_id, "JOB_FAILED", msg)

    finally:
        context.close()
        browser.close()


# ════════════════════════════════════════════════════════════
# POLLING LOOP
# ════════════════════════════════════════════════════════════

def main():
    log.info("🤖 Universal Bot | headless=%s | slow_mo=%s | poll=%ss",
             HEADLESS, SLOW_MO, POLL_INTERVAL)
    log.info("   downloads  → %s", os.path.abspath(DOWNLOAD_DIR))
    log.info("   screenshots → %s", os.path.abspath(SCREENSHOT_DIR))
    log.info("   Waiting for pending jobs...")

    with sync_playwright() as pw:
        while True:
            try:
                job_data = db.get_pending_job()
            except Exception as exc:
                log.error("API poll error: %s", exc)
                time.sleep(POLL_INTERVAL)
                continue

            if job_data is None:
                log.debug("no pending jobs — sleeping %ds", POLL_INTERVAL)
                time.sleep(POLL_INTERVAL)
                continue

            job_id = int(job_data["job"]["id"])
            cname  = job_data["job"].get("company_name", "?")
            log.info("📥 JOB-%04d picked up [%s]", job_id, cname)

            for attempt in range(1, 3):
                try:
                    run_job(pw, job_data)
                    break
                except Exception:
                    if attempt == 1:
                        log.warning("  retrying JOB-%04d in 5s…", job_id)
                        db.update_job_status(job_id, "processing")
                        time.sleep(5)
                    else:
                        log.error("  JOB-%04d failed after 2 attempts", job_id)

            time.sleep(2)


if __name__ == "__main__":
    main()

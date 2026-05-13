"""
db.py — Thin HTTP wrapper around the PHP API.
The bot never touches MySQL directly; it communicates
through the REST API so all business logic stays in PHP.
"""

import requests
import logging
from config import API_BASE_URL

log = logging.getLogger(__name__)


def _url(endpoint: str) -> str:
    return f"{API_BASE_URL}/{endpoint}"


def get_pending_job() -> dict | None:
    """
    Ask the API to atomically claim the next pending job.
    Returns the job dict (with 'company' key) or None.
    """
    try:
        resp = requests.get(_url("pending-job.php"), timeout=10)
        data = resp.json()
        if data.get("success"):
            return data
        return None
    except Exception as exc:
        log.error("get_pending_job error: %s", exc)
        return None


def update_job_status(job_id: int, status: str, error_message: str = "",
                      file_path: str = "", screenshot: str = "") -> bool:
    """Update a job's status, optional error message, file path, and screenshot."""
    try:
        payload = {
            "id":                 job_id,
            "status":             status,
            "error_message":      error_message,
            "barcode_file_path":  file_path,
            "screenshot_path":    screenshot,
        }
        resp = requests.post(_url("update-job-status.php"), json=payload, timeout=10)
        return resp.json().get("success", False)
    except Exception as exc:
        log.error("update_job_status error: %s", exc)
        return False


def add_log(job_id: int, action: str, message: str) -> None:
    """Write an activity log entry for the job."""
    try:
        payload = {"job_id": job_id, "action": action, "message": message}
        requests.post(_url("add-log.php"), json=payload, timeout=10)
    except Exception as exc:
        log.warning("add_log error: %s", exc)


def save_bot_error(job_id: int, company_name: str, step: str,
                   field_key: str, excel_value: str,
                   portal_error: str, error_type: str,
                   selector: str = "", screenshot: str = "",
                   page_url: str = "") -> bool:
    """
    Save a structured bot error to bot_error_logs and mark the job failed.

    Also updates barcode_jobs.processing_error with a short summary like
    "quantity: Quantity must be minimum 100" for the dashboard table column.
    """
    try:
        payload = {
            "job_id":                job_id,
            "company_name":          company_name,
            "step_name":             step,
            "field_key":             field_key,
            "excel_value":           str(excel_value),
            "portal_error_message":  portal_error,
            "error_type":            error_type,
            "selector":              selector,
            "screenshot_path":       screenshot,
            "page_url":              page_url,
        }
        resp = requests.post(_url("save-bot-error.php"), json=payload, timeout=10)
        data = resp.json()
        if data.get("success"):
            log.info("  ✓ bot error saved (log_id=%s): %s",
                     data.get("error_log_id"), data.get("processing_error"))
            return True
        log.warning("  save_bot_error non-success: %s", data)
        return False
    except Exception as exc:
        log.error("save_bot_error HTTP error: %s", exc)
        return False

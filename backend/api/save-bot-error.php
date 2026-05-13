<?php
// ============================================================
// POST /api/save-bot-error.php
// Called by the bot when a portal-level field/step error is detected.
//
// Body (JSON):
//   job_id, company_name, step_name, field_key, excel_value,
//   portal_error_message, error_type, selector, screenshot_path, page_url
//
// Actions:
//   1. INSERT into bot_error_logs
//   2. UPDATE barcode_jobs with structured error fields + status=failed
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

$job_id       = intval($body['job_id']               ?? 0);
$company_name = trim($body['company_name']            ?? '');
$step_name    = trim($body['step_name']               ?? '');
$field_key    = trim($body['field_key']               ?? '');
$excel_value  = trim($body['excel_value']             ?? '');
$portal_error = trim($body['portal_error_message']    ?? '');
$error_type   = trim($body['error_type']              ?? 'unknown_error');
$selector     = trim($body['selector']                ?? '');
$screenshot   = trim($body['screenshot_path']         ?? '');
$page_url     = trim($body['page_url']                ?? '');

$allowed_error_types = [
    'login_error', 'field_validation_error', 'dropdown_option_error',
    'file_upload_error', 'submit_disabled', 'submit_error',
    'download_error', 'portal_alert_error', 'timeout_error',
    'company_not_found', 'unknown_error',
];

if ($job_id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid job_id is required.']);
    exit;
}

if (!in_array($error_type, $allowed_error_types)) {
    $error_type = 'unknown_error';
}

$db = getDB();

// ── 1. Insert into bot_error_logs ─────────────────────────
$ins = $db->prepare("
    INSERT INTO bot_error_logs
        (job_id, company_name, step_name, field_key, excel_value,
         portal_error_message, error_type, selector, screenshot_path, page_url)
    VALUES (?,?,?,?,?,?,?,?,?,?)
");
$ins->execute([
    $job_id, $company_name, $step_name, $field_key, $excel_value,
    $portal_error, $error_type, $selector, $screenshot, $page_url,
]);
$error_log_id = $db->lastInsertId();

// ── 2. Build short one-line processing_error ──────────────
// Format: "field_key: portal error message"  (shown in job list table)
if ($field_key && $portal_error) {
    $processing_error = "{$field_key}: {$portal_error}";
} elseif ($portal_error) {
    $processing_error = $portal_error;
} else {
    $processing_error = $error_type;
}
// Keep it under 200 chars for the table column
if (strlen($processing_error) > 200) {
    $processing_error = substr($processing_error, 0, 197) . '…';
}

// ── 3. Update barcode_jobs ────────────────────────────────
$upd = $db->prepare("
    UPDATE barcode_jobs SET
        status                  = 'failed',
        processing_error        = ?,
        bot_error_field         = ?,
        bot_error_type          = ?,
        bot_error_step          = ?,
        bot_excel_value         = ?,
        bot_portal_error_message = ?,
        screenshot_path         = COALESCE(NULLIF(?, ''), screenshot_path),
        failed_at               = NOW(),
        updated_at              = NOW()
    WHERE id = ?
");
$upd->execute([
    $processing_error,
    $field_key   ?: null,
    $error_type,
    $step_name   ?: null,
    $excel_value ?: null,
    $portal_error ?: null,
    $screenshot  ?: null,
    $job_id,
]);

echo json_encode([
    'success'          => true,
    'error_log_id'     => (int) $error_log_id,
    'processing_error' => $processing_error,
    'message'          => "Bot error saved for job #{$job_id}.",
]);

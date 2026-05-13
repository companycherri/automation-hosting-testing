<?php
// ============================================================
// GET /api/bot-errors.php
// Returns bot_error_logs rows with optional filters.
//
// Query params (all optional):
//   company_name, field_key, error_type, date_from (YYYY-MM-DD),
//   date_to (YYYY-MM-DD), job_id, limit (default 200)
//
// Returns: { success, errors[], summary{}, filters{} }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db = getDB();

$company   = trim($_GET['company_name'] ?? '');
$field     = trim($_GET['field_key']    ?? '');
$errType   = trim($_GET['error_type']   ?? '');
$dateFrom  = trim($_GET['date_from']    ?? '');
$dateTo    = trim($_GET['date_to']      ?? '');
$jobIdF    = intval($_GET['job_id']     ?? 0);
$limit     = min(500, max(1, intval($_GET['limit'] ?? 200)));

// ── Build WHERE clause ─────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($company)  { $where[] = 'bel.company_name = ?'; $params[] = $company; }
if ($field)    { $where[] = 'bel.field_key = ?';    $params[] = $field; }
if ($errType)  { $where[] = 'bel.error_type = ?';   $params[] = $errType; }
if ($dateFrom) { $where[] = 'DATE(bel.created_at) >= ?'; $params[] = $dateFrom; }
if ($dateTo)   { $where[] = 'DATE(bel.created_at) <= ?'; $params[] = $dateTo; }
if ($jobIdF)   { $where[] = 'bel.job_id = ?'; $params[] = $jobIdF; }

$whereStr = implode(' AND ', $where);

// ── Main query ────────────────────────────────────────────
$sql = "
    SELECT
        bel.id,
        bel.job_id,
        bel.company_name,
        bel.step_name,
        bel.field_key,
        bel.excel_value,
        bel.portal_error_message,
        bel.error_type,
        bel.selector,
        bel.screenshot_path,
        bel.page_url,
        bel.created_at,
        bj.status      AS job_status,
        bj.part_no,
        bj.quantity,
        bj.batch_no,
        bj.processing_error
    FROM bot_error_logs bel
    LEFT JOIN barcode_jobs bj ON bj.id = bel.job_id
    WHERE {$whereStr}
    ORDER BY bel.created_at DESC
    LIMIT {$limit}
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$errors = $stmt->fetchAll();

// ── Summary counts (unfiltered — totals across all time) ──
$sumRow = $db->query("
    SELECT
        COUNT(*)                                              AS total,
        SUM(error_type = 'field_validation_error')           AS field_validation,
        SUM(error_type = 'submit_disabled')                  AS submit_disabled,
        SUM(error_type = 'login_error')                      AS login_errors,
        SUM(error_type = 'dropdown_option_error')            AS dropdown_errors,
        SUM(error_type = 'file_upload_error')                AS file_upload_errors,
        SUM(error_type = 'download_error')                   AS download_errors,
        SUM(error_type = 'timeout_error')                    AS timeouts,
        SUM(error_type = 'unknown_error')                    AS unknown_errors
    FROM bot_error_logs
")->fetch();

foreach ($sumRow as $k => $v) { $sumRow[$k] = (int)$v; }

// ── Distinct filter options (for dropdowns in UI) ─────────
$companies = $db->query(
    "SELECT DISTINCT company_name FROM bot_error_logs WHERE company_name != '' ORDER BY company_name"
)->fetchAll(PDO::FETCH_COLUMN);

$fields = $db->query(
    "SELECT DISTINCT field_key FROM bot_error_logs WHERE field_key != '' ORDER BY field_key"
)->fetchAll(PDO::FETCH_COLUMN);

$errTypes = $db->query(
    "SELECT DISTINCT error_type FROM bot_error_logs ORDER BY error_type"
)->fetchAll(PDO::FETCH_COLUMN);

echo json_encode([
    'success' => true,
    'errors'  => $errors,
    'summary' => $sumRow,
    'filters' => [
        'companies'   => $companies,
        'fields'      => $fields,
        'error_types' => $errTypes,
    ],
    'count'   => count($errors),
]);

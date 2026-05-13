<?php
// ============================================================
// POST /api/upload-excel.php
// Excel/CSV import for job queue creation.
//
// action=preview  → parse file, return rows + validation
// action=import   → receive JSON rows + batch_id, map file
//                   names to server paths, create jobs in DB
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/xlsx-reader.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Detect action ──────────────────────────────────────────
$rawInput = file_get_contents('php://input');
$jsonBody = json_decode($rawInput, true) ?: [];
$action   = $_POST['action'] ?? $_GET['action'] ?? $jsonBody['action'] ?? 'preview';

// ════════════════════════════════════════════════════════════
// IMPORT  — receive validated rows + optional batch_id
// ════════════════════════════════════════════════════════════
if ($action === 'import') {
    $body     = $jsonBody ?: [];
    $rows     = $body['rows']     ?? [];
    $batchId  = trim($body['batch_id'] ?? '');

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'No rows to import.']);
        exit;
    }

    // ── Build file lookup from batch folder ────────────────
    // key: lowercase filename → absolute server path
    $fileLookup = [];
    if ($batchId) {
        $batchDir = realpath(__DIR__ . '/../uploads/job-files/' . $batchId);
        if ($batchDir && is_dir($batchDir)) {
            foreach (scandir($batchDir) as $entry) {
                if ($entry === '.' || $entry === '..') continue;
                $fullPath = $batchDir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($fullPath)) {
                    $fileLookup[strtolower($entry)] = str_replace('\\', '/', $fullPath);
                }
            }
        }
    }

    // ── INSERT statement ───────────────────────────────────
    $db   = getDB();
    $stmt = $db->prepare("
        INSERT INTO barcode_jobs
            (company_name, part_no, quantity, batch_no, vendor_code,
             delivery_date, priority, notes,
             upload_file_1_name, upload_file_1_path,
             upload_file_2_name, upload_file_2_path,
             upload_file_3_name, upload_file_3_path,
             status, error_message,
             attempt_count, created_at, updated_at)
        VALUES
            (:company_name, :part_no, :quantity, :batch_no, :vendor_code,
             :delivery_date, :priority, :notes,
             :upload_file_1_name, :upload_file_1_path,
             :upload_file_2_name, :upload_file_2_path,
             :upload_file_3_name, :upload_file_3_path,
             :status, :error_message,
             0, NOW(), NOW())
    ");

    $created = 0;
    $errors  = [];

    foreach ($rows as $i => $row) {
        // ── Resolve each upload slot ───────────────────────
        $fileParams   = [];
        $missingFiles = [];

        for ($slot = 1; $slot <= 3; $slot++) {
            // Excel column: upload_file_1, upload_file_2, upload_file_3
            $fname = trim($row["upload_file_{$slot}"] ?? '');
            $name_key = "upload_file_{$slot}_name";
            $path_key = "upload_file_{$slot}_path";

            if ($fname === '') {
                $fileParams[$name_key] = '';
                $fileParams[$path_key] = '';
                continue;
            }

            // Look up in batch
            $lookupKey = strtolower($fname);
            if (isset($fileLookup[$lookupKey])) {
                $fileParams[$name_key] = $fname;
                $fileParams[$path_key] = $fileLookup[$lookupKey];
            } else {
                $fileParams[$name_key] = $fname;
                $fileParams[$path_key] = '';
                $missingFiles[]        = "upload_file_{$slot}: {$fname}";
            }
        }

        // ── Determine job status based on file availability ─
        if (!empty($missingFiles)) {
            $jobStatus    = 'failed';
            $errorMessage = 'File not found: ' . implode('; ', $missingFiles);
        } else {
            $jobStatus    = 'pending';
            $errorMessage = '';
        }

        try {
            $stmt->execute(array_merge([
                'company_name'  => trim($row['company_name']  ?? ''),
                'part_no'       => trim($row['part_no']       ?? ''),
                'quantity'      => (int)($row['quantity']     ?? 0),
                'batch_no'      => trim($row['batch_no']      ?? ''),
                'vendor_code'   => trim($row['vendor_code']   ?? ''),
                'delivery_date' => !empty($row['delivery_date']) ? $row['delivery_date'] : null,
                'priority'      => trim($row['priority']      ?? 'normal'),
                'notes'         => trim($row['notes']         ?? ''),
                'status'        => $jobStatus,
                'error_message' => $errorMessage,
            ], $fileParams));
            $created++;
        } catch (PDOException $e) {
            $errors[] = 'Row ' . ($i + 2) . ': ' . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'created' => $created,
        'errors'  => $errors,
        'message' => "Imported {$created} job(s). " .
                     (count($errors) > 0 ? count($errors) . ' error(s).' : ''),
    ]);
    exit;
}

// ════════════════════════════════════════════════════════════
// PREVIEW — parse uploaded Excel/CSV file
// ════════════════════════════════════════════════════════════
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errCode = $_FILES['file']['error'] ?? 'NO_FILE';
    echo json_encode(['success' => false, 'message' => "File upload error: {$errCode}"]);
    exit;
}

$file     = $_FILES['file'];
$origName = $file['name'];
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$tmpPath  = $file['tmp_name'];

if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
    echo json_encode(['success' => false, 'message' => 'Only .xlsx, .xls, and .csv files are allowed.']);
    exit;
}
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 5 MB).']);
    exit;
}

try {
    $parsed = SimpleXlsxReader::readAs($tmpPath, $ext);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'File read error: ' . $e->getMessage()]);
    exit;
}

// ── Header alias map ───────────────────────────────────────
// Maps any recognised column name variant → canonical field key
// Upload columns: Excel contains ONLY the filename (e.g. invoice_001.pdf)
$header_aliases = [
    'company_name'  => ['company_name', 'company', 'company name', 'company_nm'],
    'part_no'       => ['part_no', 'part_number', 'partno', 'part no', 'part', 'part_code'],
    'quantity'      => ['quantity', 'qty', 'count', 'amount'],
    'batch_no'      => ['batch_no', 'batch_number', 'batchno', 'batch no', 'batch', 'batch_id'],
    'vendor_code'   => ['vendor_code', 'vendor', 'vendor code', 'vendorcode', 'vendor_id'],
    'delivery_date' => ['delivery_date', 'delivery', 'due_date', 'due date', 'deliver_date'],
    'priority'      => ['priority', 'urgency', 'level'],
    'notes'         => ['notes', 'note', 'remarks', 'comment', 'comments', 'description'],
    // Upload columns contain only the file NAME, not a full path
    'upload_file_1' => ['upload_file_1', 'upload_file_1_path', 'file_1', 'file1', 'upload1', 'attachment_1'],
    'upload_file_2' => ['upload_file_2', 'upload_file_2_path', 'file_2', 'file2', 'upload2', 'attachment_2'],
    'upload_file_3' => ['upload_file_3', 'upload_file_3_path', 'file_3', 'file3', 'upload3', 'attachment_3'],
];

function resolveHeaders(array $rawHeaders, array $aliases): array {
    $resolved = [];
    foreach ($rawHeaders as $h) {
        $norm = strtolower(trim($h));
        foreach ($aliases as $canonical => $aliasList) {
            if (in_array($norm, $aliasList)) {
                $resolved[$norm] = $canonical;
                break;
            }
        }
        if (!isset($resolved[$norm])) {
            $resolved[$norm] = $norm;
        }
    }
    return $resolved;
}

$headerMap     = resolveHeaders($parsed['headers'], $header_aliases);
$required_cols = ['company_name', 'part_no', 'quantity', 'batch_no', 'vendor_code'];

$coveredFields = array_values($headerMap);
$missing       = array_diff($required_cols, $coveredFields);
if (!empty($missing)) {
    echo json_encode([
        'success'       => false,
        'message'       => 'Missing required columns: ' . implode(', ', $missing),
        'found_headers' => $parsed['headers'],
    ]);
    exit;
}

// ── Validate each row ──────────────────────────────────────
// Only check that required fields are non-empty.
// We do NOT validate company names, part numbers, vendor codes etc.
// against any whitelist — the bot enters values exactly as given and
// the portal itself is the source of truth for what is valid.
$valid_rows   = [];
$invalid_rows = [];

foreach ($parsed['rows'] as $i => $row) {
    $r = [];
    foreach ($row as $rawKey => $v) {
        $norm      = strtolower(trim($rawKey));
        $canonical = $headerMap[$norm] ?? $norm;
        $r[$canonical] = trim((string)$v);
    }

    $rowErrors = [];
    $rowNum    = $i + 2;

    if (empty($r['company_name']))  $rowErrors[] = 'company_name is required';
    if (empty($r['part_no']))       $rowErrors[] = 'part_no is required';
    if (!isset($r['quantity']) || $r['quantity'] === '') $rowErrors[] = 'quantity is required';
    if (empty($r['batch_no']))      $rowErrors[] = 'batch_no is required';
    if (empty($r['vendor_code']))   $rowErrors[] = 'vendor_code is required';

    // Normalise optional + upload filename fields
    $r['delivery_date'] = !empty($r['delivery_date']) ? $r['delivery_date'] : '';
    $r['priority']      = !empty($r['priority'])      ? strtolower($r['priority']) : 'normal';
    $r['notes']         = $r['notes']         ?? '';
    $r['upload_file_1'] = basename($r['upload_file_1'] ?? ''); // keep only filename
    $r['upload_file_2'] = basename($r['upload_file_2'] ?? '');
    $r['upload_file_3'] = basename($r['upload_file_3'] ?? '');
    $r['row_num']       = $rowNum;

    if (empty($rowErrors)) {
        $r['_valid']  = true;
        $r['_errors'] = [];
        $valid_rows[] = $r;
    } else {
        $r['_valid']  = false;
        $r['_errors'] = $rowErrors;
        $invalid_rows[] = $r;
    }
}

echo json_encode([
    'success'       => true,
    'total_rows'    => count($parsed['rows']),
    'valid_count'   => count($valid_rows),
    'invalid_count' => count($invalid_rows),
    'headers'       => $parsed['headers'],
    'valid_rows'    => $valid_rows,
    'invalid_rows'  => $invalid_rows,
    'all_rows'      => array_merge($valid_rows, $invalid_rows),
]);

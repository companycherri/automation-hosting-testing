<?php
// ============================================================
// GET /api/downloads.php
// Returns list of jobs that have downloaded barcode files.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$db = getDB();

$stmt = $db->query("
    SELECT id, company_name, part_no, batch_no, vendor_code,
           delivery_date, priority, notes,
           barcode_file_path, status, created_at, updated_at
    FROM barcode_jobs
    WHERE barcode_file_path IS NOT NULL AND barcode_file_path != ''
      AND status = 'success'
    ORDER BY updated_at DESC
    LIMIT 200
");

$downloads = $stmt->fetchAll();

// Add download URL for each
foreach ($downloads as &$d) {
    $d['download_url'] = "http://localhost/mini-automation/backend/api/download-file.php?id=" . $d['id'];
}
unset($d);

echo json_encode([
    'success'   => true,
    'count'     => count($downloads),
    'downloads' => $downloads,
]);

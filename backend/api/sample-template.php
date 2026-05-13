<?php
// ============================================================
// GET /api/sample-template.php
// Downloads a sample CSV that users can open in Excel,
// fill with their data, and re-save as .xlsx for upload.
// ============================================================

require_once __DIR__ . '/../config/cors.php';

// Override content-type set by cors.php
header('Content-Type: text/csv', true);
header('Content-Disposition: attachment; filename="barcode_upload_template.csv"');
header('Cache-Control: no-cache');

$rows = [
    ['company_name', 'part_no', 'quantity', 'batch_no', 'vendor_code'],
    ['Demo Company', 'PN-1001', '100', 'BATCH-2024-01', 'VND-001'],
    ['Demo Company', 'PN-1002', '200', 'BATCH-2024-02', 'VND-002'],
    ['Demo Company', 'PN-1003', '150', 'BATCH-2024-03', 'VND-003'],
];

$out = fopen('php://output', 'w');
foreach ($rows as $row) {
    fputcsv($out, $row);
}
fclose($out);
exit;

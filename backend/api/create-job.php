<?php
// ============================================================
// POST /api/create-job.php
// Body: { company_name, part_no, quantity, batch_no, vendor_code }
// Returns: { success, job_id }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$company_name = trim($body['company_name'] ?? '');
$part_no      = trim($body['part_no']      ?? '');
$quantity     = intval($body['quantity']   ?? 0);
$batch_no     = trim($body['batch_no']     ?? '');
$vendor_code  = trim($body['vendor_code']  ?? '');

// Validation
if (empty($company_name) || empty($part_no) || $quantity <= 0 || empty($batch_no) || empty($vendor_code)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'All fields are required and quantity must be > 0.']);
    exit;
}

$db   = getDB();

// Insert job into queue with "pending" status
$stmt = $db->prepare(
    'INSERT INTO barcode_jobs (company_name, part_no, quantity, batch_no, vendor_code, status)
     VALUES (?, ?, ?, ?, ?, "pending")'
);
$stmt->execute([$company_name, $part_no, $quantity, $batch_no, $vendor_code]);
$jobId = $db->lastInsertId();

// Log job creation
$log = $db->prepare('INSERT INTO activity_logs (job_id, action, message) VALUES (?, ?, ?)');
$log->execute([$jobId, 'JOB_CREATED', "Job #{$jobId} created for company: {$company_name}, Part: {$part_no}"]);

echo json_encode([
    'success' => true,
    'message' => 'Job created successfully.',
    'job_id'  => (int) $jobId,
]);

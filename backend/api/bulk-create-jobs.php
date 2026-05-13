<?php
// ============================================================
// POST /api/bulk-create-jobs.php
// Body: { jobs: [ { company_name, part_no, quantity, batch_no, vendor_code }, ... ] }
// Returns: { success, created, failed, results[] }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
$jobs = $body['jobs'] ?? [];

if (empty($jobs) || !is_array($jobs)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'No jobs provided.']);
    exit;
}

if (count($jobs) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Maximum 500 jobs per upload.']);
    exit;
}

$db = getDB();

$insert = $db->prepare(
    'INSERT INTO barcode_jobs (company_name, part_no, quantity, batch_no, vendor_code, status)
     VALUES (?, ?, ?, ?, ?, "pending")'
);
$logStmt = $db->prepare(
    'INSERT INTO activity_logs (job_id, action, message) VALUES (?, ?, ?)'
);

$created = 0;
$failed  = 0;
$results = [];

foreach ($jobs as $index => $job) {
    $row = $index + 1;

    // Sanitize
    $company_name = trim($job['company_name'] ?? '');
    $part_no      = trim($job['part_no']      ?? '');
    $quantity     = intval($job['quantity']    ?? 0);
    $batch_no     = trim($job['batch_no']      ?? '');
    $vendor_code  = trim($job['vendor_code']   ?? '');

    // Validate row
    if (empty($company_name) || empty($part_no) || $quantity <= 0 || empty($batch_no) || empty($vendor_code)) {
        $results[] = [
            'row'     => $row,
            'status'  => 'failed',
            'message' => 'Missing or invalid fields.',
            'data'    => $job,
        ];
        $failed++;
        continue;
    }

    try {
        $insert->execute([$company_name, $part_no, $quantity, $batch_no, $vendor_code]);
        $jobId = $db->lastInsertId();

        $logStmt->execute([
            $jobId,
            'JOB_CREATED',
            "Job #{$jobId} created via Excel upload — Row {$row}: {$company_name} / {$part_no}",
        ]);

        $results[] = [
            'row'     => $row,
            'status'  => 'created',
            'job_id'  => (int) $jobId,
            'message' => "Job #{$jobId} created.",
            'data'    => $job,
        ];
        $created++;

    } catch (PDOException $e) {
        $results[] = [
            'row'     => $row,
            'status'  => 'failed',
            'message' => 'Database error: ' . $e->getMessage(),
            'data'    => $job,
        ];
        $failed++;
    }
}

echo json_encode([
    'success' => true,
    'created' => $created,
    'failed'  => $failed,
    'total'   => count($jobs),
    'results' => $results,
]);

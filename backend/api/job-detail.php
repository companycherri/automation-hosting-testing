<?php
// ============================================================
// GET /api/job-detail.php?id=<job_id>
// Returns: { success, job, logs }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid job ID is required.']);
    exit;
}

$db = getDB();

// Fetch job
$stmt = $db->prepare('SELECT * FROM barcode_jobs WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$job = $stmt->fetch();

if (!$job) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Job not found.']);
    exit;
}

// Fetch activity logs for this job
$log_stmt = $db->prepare('SELECT * FROM activity_logs WHERE job_id = ? ORDER BY created_at ASC');
$log_stmt->execute([$id]);
$logs = $log_stmt->fetchAll();

echo json_encode([
    'success' => true,
    'job'     => $job,
    'logs'    => $logs,
]);

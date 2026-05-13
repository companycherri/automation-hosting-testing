<?php
// ============================================================
// GET /api/jobs.php
// Query params: ?status=pending|processing|success|failed (optional)
// Returns: { success, jobs, summary }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db = getDB();

// Build query with optional status filter
$allowed_statuses = ['pending', 'processing', 'success', 'failed'];
$status = $_GET['status'] ?? '';

if (!empty($status) && in_array($status, $allowed_statuses)) {
    $stmt = $db->prepare('SELECT * FROM barcode_jobs WHERE status = ? ORDER BY created_at DESC');
    $stmt->execute([$status]);
} else {
    $stmt = $db->prepare('SELECT * FROM barcode_jobs ORDER BY created_at DESC');
    $stmt->execute();
}

$jobs = $stmt->fetchAll();

// Summary counts for dashboard widgets
$summary_stmt = $db->query(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "pending")    AS pending,
        SUM(status = "processing") AS processing,
        SUM(status = "success")    AS success,
        SUM(status = "failed")     AS failed
     FROM barcode_jobs'
);
$summary = $summary_stmt->fetch();

// Cast numbers to int
foreach ($summary as $key => $val) {
    $summary[$key] = (int) $val;
}

echo json_encode([
    'success' => true,
    'jobs'    => $jobs,
    'summary' => $summary,
]);

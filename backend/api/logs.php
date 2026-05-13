<?php
// ============================================================
// GET /api/logs.php
// Query params: ?job_id=<id> (optional filter)
// Returns: { success, logs }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db     = getDB();
$job_id = intval($_GET['job_id'] ?? 0);

if ($job_id > 0) {
    $stmt = $db->prepare(
        'SELECT al.*, bj.part_no, bj.company_name
         FROM activity_logs al
         LEFT JOIN barcode_jobs bj ON al.job_id = bj.id
         WHERE al.job_id = ?
         ORDER BY al.created_at DESC'
    );
    $stmt->execute([$job_id]);
} else {
    $stmt = $db->prepare(
        'SELECT al.*, bj.part_no, bj.company_name
         FROM activity_logs al
         LEFT JOIN barcode_jobs bj ON al.job_id = bj.id
         ORDER BY al.created_at DESC
         LIMIT 200'
    );
    $stmt->execute();
}

$logs = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'logs'    => $logs,
]);

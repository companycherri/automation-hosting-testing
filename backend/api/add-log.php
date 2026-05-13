<?php
// ============================================================
// POST /api/add-log.php
// Body: { job_id, action, message }
// Used by Python bot to write activity logs
// Returns: { success }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body    = json_decode(file_get_contents('php://input'), true);
$job_id  = intval($body['job_id']  ?? 0);
$action  = trim($body['action']    ?? '');
$message = trim($body['message']   ?? '');

if ($job_id <= 0 || empty($action) || empty($message)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'job_id, action and message are required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('INSERT INTO activity_logs (job_id, action, message) VALUES (?, ?, ?)');
$stmt->execute([$job_id, $action, $message]);

echo json_encode(['success' => true]);

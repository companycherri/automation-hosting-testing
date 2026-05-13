<?php
// ============================================================
// POST /api/update-job-status.php
// Body: { id, status, error_message?, barcode_file_path? }
// Used by the Python bot to report progress
// Returns: { success, message }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$id                = intval($body['id']                ?? 0);
$status            = trim($body['status']              ?? '');
$error_message     = trim($body['error_message']       ?? '');
$barcode_file_path = trim($body['barcode_file_path']   ?? '');
$screenshot_path   = trim($body['screenshot_path']     ?? '');

$allowed_statuses = ['pending', 'processing', 'success', 'failed'];

if ($id <= 0 || !in_array($status, $allowed_statuses)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Valid ID and status are required.']);
    exit;
}

$db = getDB();

// Increment attempt_count only when moving to processing
if ($status === 'processing') {
    $stmt = $db->prepare(
        'UPDATE barcode_jobs
         SET status = ?, attempt_count = attempt_count + 1, updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$status, $id]);
} else {
    $stmt = $db->prepare(
        'UPDATE barcode_jobs
         SET status = ?, error_message = ?, barcode_file_path = ?,
             screenshot_path = COALESCE(NULLIF(?, ""), screenshot_path), updated_at = NOW()
         WHERE id = ?'
    );
    $stmt->execute([$status, $error_message ?: null, $barcode_file_path ?: null,
                    $screenshot_path ?: null, $id]);
}

echo json_encode([
    'success' => true,
    'message' => "Job #{$id} updated to {$status}.",
]);

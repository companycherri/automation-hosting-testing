<?php
// ============================================================
// POST /api/reset-failed-jobs.php
// Resets all failed jobs back to "pending" so the bot retries them.
// Body: {} (no params needed — resets ALL failed jobs)
// Returns: { success, reset_count }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db = getDB();

// Reset failed → pending and clear old error messages
$stmt = $db->prepare("
    UPDATE barcode_jobs
    SET status = 'pending', error_message = NULL, updated_at = NOW()
    WHERE status = 'failed'
");
$stmt->execute();
$count = $stmt->rowCount();

// Log the reset
if ($count > 0) {
    $log = $db->prepare("INSERT INTO activity_logs (job_id, action, message) VALUES (NULL, 'JOBS_RESET', ?)");
    $log->execute(["$count failed job(s) reset to pending by admin"]);
}

echo json_encode([
    'success'     => true,
    'reset_count' => $count,
    'message'     => "$count failed job(s) reset to pending.",
]);

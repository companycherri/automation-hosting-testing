<?php
// ============================================================
// GET /api/download.php?id=<job_id>
// Streams the barcode file to the browser
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
    echo json_encode(['success' => false, 'message' => 'Valid job ID required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT barcode_file_path, part_no FROM barcode_jobs WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$job  = $stmt->fetch();

if (!$job || empty($job['barcode_file_path'])) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'No file available for this job.']);
    exit;
}

$file_path = $job['barcode_file_path'];

// Resolve relative paths stored by the bot
if (!file_exists($file_path)) {
    // Try relative to project root
    $alt = __DIR__ . '/../../' . ltrim($file_path, '/\\');
    if (file_exists($alt)) {
        $file_path = $alt;
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'File not found on disk.']);
        exit;
    }
}

$filename = basename($file_path);
$mime     = mime_content_type($file_path) ?: 'application/octet-stream';

// Override content-type header set by cors.php
header('Content-Type: ' . $mime, true);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache');

readfile($file_path);
exit;

<?php
// GET /api/download-file.php?id=<job_id>
// Serves the barcode file for a completed job.
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); die('Job ID required.'); }

$db   = getDB();
$stmt = $db->prepare("SELECT barcode_file_path FROM barcode_jobs WHERE id = ? AND status = 'success'");
$stmt->execute([$id]);
$job  = $stmt->fetch();

if (!$job || empty($job['barcode_file_path'])) {
    http_response_code(404); die('File not found or job not completed.');
}

// barcode_file_path is stored relative to project root e.g. "bot/downloads/filename.txt"
// __DIR__ = backend/api  →  dirname×2 = project root (mini-automation/)
$rel  = $job['barcode_file_path'];
$base = dirname(dirname(__DIR__)); // C:/.../mini-automation
$path = $base . '/' . ltrim(str_replace('\\', '/', $rel), '/');

if (!file_exists($path)) {
    http_response_code(404); die("File missing on disk: $path");
}

$filename = basename($path);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-cache');
readfile($path);
exit;

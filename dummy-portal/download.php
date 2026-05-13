<?php
// ============================================================
// Dummy Customer Portal — File Download
// GET: ?file=<filename>
// Serves the generated barcode text file
// ============================================================

// ── Same session fix as login.php ─────────────────────────
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('PORTAL_SID');
session_start();

// Accept EITHER session OR the backup cookie
$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['portal_auth'] ?? '') === 'ok';

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

$file = basename($_GET['file'] ?? '');  // basename strips directory traversal

if (empty($file)) {
    die('No file specified.');
}

$filepath = __DIR__ . '/generated/' . $file;

if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found.');
}

// Stream file to browser
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');
readfile($filepath);
exit;

<?php
// ============================================================
// CORS Headers
// In Docker production: frontend + API share the same origin
// (both behind nginx) so CORS is not required, but we keep
// it for flexibility and local XAMPP dev.
// ============================================================

$allowed_origins = [
    'http://localhost:3000',   // React dev server (XAMPP)
    'http://localhost',        // Docker / nginx
    'http://localhost:80',
];

// Also allow the configured APP_URL
$app_url = rtrim(getenv('APP_URL') ?: '', '/');
if ($app_url && !in_array($app_url, $allowed_origins)) {
    $allowed_origins[] = $app_url;
}

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Fallback: allow localhost:3000 for local dev
    header('Access-Control-Allow-Origin: http://localhost:3000');
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

// Pre-flight request (browser sends OPTIONS before POST)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

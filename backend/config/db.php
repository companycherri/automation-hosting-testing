<?php
// ============================================================
// Backend Configuration
// All values read from environment variables.
// Safe local defaults allow the same code to run in
// XAMPP (no env vars set) and Docker/VPS (env vars set).
//
// Set these in .env (Docker) or your web server config (VPS).
// ============================================================

// ── Database ───────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT')     ?: '3306');
define('DB_NAME',    getenv('DB_NAME')     ?: 'barcode_portal');
define('DB_USER',    getenv('DB_USER')     ?: 'root');
define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ── Application ────────────────────────────────────────────
define('APP_NAME',   getenv('APP_NAME')    ?: 'Barcode Portal');
define('APP_URL',    rtrim(getenv('APP_URL') ?: 'http://localhost', '/'));
define('DEBUG_MODE', filter_var(getenv('DEBUG_MODE') ?: 'false', FILTER_VALIDATE_BOOLEAN));

// ── File storage paths ─────────────────────────────────────
// Docker  : /var/www/html/uploads   (volume-mounted)
// XAMPP   : auto-detect relative to this file
$_default_upload = realpath(__DIR__ . '/../../backend/uploads')
               ?: realpath(__DIR__ . '/../uploads')
               ?: sys_get_temp_dir() . '/barcode_uploads';

define('UPLOAD_PATH', rtrim(getenv('UPLOAD_PATH') ?: $_default_upload,  '/'));
define('LOG_PATH',    rtrim(getenv('LOG_PATH')    ?: sys_get_temp_dir(), '/'));

// ── Debug / error display ──────────────────────────────────
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(E_ERROR | E_PARSE);
}


// ============================================================
// Database connection
// ============================================================

/**
 * Returns a singleton PDO connection.
 * On failure returns a JSON error response and exits.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 10,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = DEBUG_MODE
                ? 'Database connection failed: ' . $e->getMessage()
                : 'Database connection failed.';
            echo json_encode(['success' => false, 'message' => $msg]);
            exit;
        }
    }

    return $pdo;
}

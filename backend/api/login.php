<?php
// ============================================================
// POST /api/login.php
// Body: { email, password }
// Returns: { success, token, user }
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

$email    = trim($body['email']    ?? '');
$password = trim($body['password'] ?? '');

// Basic validation
if (empty($email) || empty($password)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Email and password are required.']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verify password with bcrypt
if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid credentials.']);
    exit;
}

// Create a simple session token (for demo — use JWT in production)
$token = bin2hex(random_bytes(32));

// Remove password from response
unset($user['password']);

echo json_encode([
    'success' => true,
    'token'   => $token,
    'user'    => $user,
]);

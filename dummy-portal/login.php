<?php
// ── Fix Windows XAMPP session issue: set explicit save path ──
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('PORTAL_SID');
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === 'admin' && $password === '123456') {
        // Set session
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $username;

        // Also set a plain cookie as backup (no session dependency)
        setcookie('portal_auth', 'ok', time() + 3600, '/');

        header('Location: barcode-form.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Demo Portal — Login</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:Arial,sans-serif;background:#f0f2f5;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .card{background:#fff;padding:40px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.1);width:380px}
        h2{text-align:center;margin-bottom:24px;color:#1a202c}
        label{display:block;margin-bottom:6px;font-size:14px;color:#4a5568}
        input{width:100%;padding:10px 14px;border:1px solid #cbd5e0;border-radius:6px;font-size:15px;margin-bottom:16px}
        input:focus{outline:none;border-color:#4299e1;box-shadow:0 0 0 3px rgba(66,153,225,.2)}
        button{width:100%;padding:12px;background:#4299e1;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer}
        button:hover{background:#3182ce}
        .error{background:#fed7d7;color:#c53030;padding:10px 14px;border-radius:6px;margin-bottom:16px;font-size:14px}
        .hint{margin-top:16px;text-align:center;font-size:13px;color:#718096}
    </style>
</head>
<body>
<div class="card">
    <h2>🏭 Demo Barcode Portal</h2>
    <?php if ($error): ?>
        <div class="error" id="login-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" id="login-form">
        <label>Username</label>
        <input type="text" name="username" id="username" placeholder="admin" autocomplete="off">
        <label>Password</label>
        <input type="password" name="password" id="password" placeholder="123456">
        <button type="submit" id="login-btn">Login</button>
    </form>
    <p class="hint">Credentials: <strong>admin</strong> / <strong>123456</strong></p>
</div>
</body>
</html>

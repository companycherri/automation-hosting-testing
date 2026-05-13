<?php
// ============================================================
// Company A Portal — Simple Login
// URL: /portals/company-a/login.php
// Credentials: operator_a / pass_a123
// Selectors: #username, #password, #login-btn
// Type: Standard username/password — no OTP, no delay
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_A_SID');
session_start();

if (!empty($_SESSION['logged_in'])) {
    header('Location: form.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === 'operator_a' && $p === 'pass_a123') {
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $u;
        $_SESSION['company']   = 'Company A';
        setcookie('co_a_auth', 'ok', time() + 3600, '/');
        header('Location: form.php'); exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company A — Login</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;background:#1a365d;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#fff;padding:44px;border-radius:12px;box-shadow:0 8px 40px rgba(0,0,0,.35);width:400px}
  .logo{text-align:center;margin-bottom:30px}
  .logo .icon{font-size:48px;line-height:1}
  .logo h1{color:#2b6cb0;font-size:22px;margin-top:8px}
  .logo p{color:#718096;font-size:12px;margin-top:4px}
  label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#4a5568}
  input{width:100%;padding:11px 14px;border:1.5px solid #cbd5e0;border-radius:7px;font-size:15px;margin-bottom:18px}
  input:focus{outline:none;border-color:#4299e1;box-shadow:0 0 0 3px rgba(66,153,225,.2)}
  #login-btn{width:100%;padding:13px;background:#2b6cb0;color:#fff;border:none;border-radius:7px;font-size:15px;cursor:pointer;font-weight:700}
  #login-btn:hover{background:#2c5282}
  .error{background:#fed7d7;color:#c53030;padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:14px}
  .hint{background:#ebf8ff;color:#2b6cb0;padding:10px 14px;border-radius:6px;margin-top:18px;font-size:12px;text-align:center}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="icon">🏢</div>
    <h1>Company A Portal</h1>
    <p>Barcode Generation System — Standard Login</p>
  </div>
  <?php if ($error): ?>
  <div class="error" id="login-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" id="login-form">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" placeholder="operator_a" autocomplete="off">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" placeholder="••••••••">
    <button type="submit" id="login-btn">Login →</button>
  </form>
  <div class="hint">🔑 <strong>operator_a</strong> / <strong>pass_a123</strong></div>
</div>
</body>
</html>

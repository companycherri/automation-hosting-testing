<?php
// ============================================================
// Company B Portal — Simple Login (NO OTP)
// URL: /portals/company-b/login.php
// Credentials: b.operator@company.com / Bpass@2024
// Selectors: #email, #password, #login-btn
// After login → form.php directly (OTP removed)
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_B_SID');
session_start();

if (!empty($_SESSION['logged_in'])) {
    header('Location: form.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']    ?? '');
    $pass  = trim($_POST['password'] ?? '');
    if ($email === 'b.operator@company.com' && $pass === 'Bpass@2024') {
        $_SESSION['logged_in'] = true;
        $_SESSION['email']     = $email;
        $_SESSION['company']   = 'Company B';
        setcookie('co_b_auth', 'ok', time() + 3600, '/');
        header('Location: form.php'); exit;
    }
    $error = 'Invalid email or password.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company B — Login</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,#1a202c 0%,#2d3748 100%);display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#fff;padding:44px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);width:420px}
  .logo{text-align:center;margin-bottom:32px}
  .logo .badge{background:#e9d8fd;color:#553c9a;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;display:inline-block;margin-bottom:12px;letter-spacing:.5px}
  .logo h1{color:#1a202c;font-size:22px}
  .logo p{color:#718096;font-size:13px;margin-top:6px}
  label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#4a5568}
  input{width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:15px;margin-bottom:18px}
  input:focus{outline:none;border-color:#805ad5;box-shadow:0 0 0 3px rgba(128,90,213,.2)}
  #login-btn{width:100%;padding:13px;background:#805ad5;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;font-weight:700}
  #login-btn:hover{background:#6b46c1}
  .error{background:#fed7d7;color:#c53030;padding:10px 14px;border-radius:8px;margin-bottom:18px;font-size:14px}
  .hint{background:#faf5ff;color:#553c9a;padding:10px 14px;border-radius:8px;margin-top:18px;font-size:12px;text-align:center}
</style>
</head>
<body>
<div class="card">
  <div class="logo">
    <div class="badge">🏭 COMPANY B PORTAL</div>
    <h1>Advanced Manufacturing</h1>
    <p>Barcode Generation System</p>
  </div>
  <?php if ($error): ?>
  <div class="error" id="login-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" id="login-form">
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" placeholder="b.operator@company.com" autocomplete="off">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" placeholder="••••••••">
    <button type="submit" id="login-btn">Login →</button>
  </form>
  <div class="hint">🔑 <strong>b.operator@company.com</strong> / <strong>Bpass@2024</strong></div>
</div>
</body>
</html>

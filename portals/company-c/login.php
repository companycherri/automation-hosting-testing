<?php
// ============================================================
// Company C Portal — Login with Loading Spinner (Delayed)
// URL: /portals/company-c/login.php
// Credentials: admin / CompanyC#123
// Selectors: input[name="username"], input[name="password"], button.login-submit
// Feature: Shows spinner + "Authenticating..." for 2 seconds before redirect
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_C_SID');
session_start();

if (!empty($_SESSION['logged_in'])) { header('Location: form.php'); exit; }

$auth_ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === 'admin' && $p === 'CompanyC#123') {
        $auth_ok = true;
        $_SESSION['logged_in'] = true;
        $_SESSION['username']  = $u;
        $_SESSION['company']   = 'Company C';
        setcookie('co_c_auth', 'ok', time() + 3600, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company C — Login</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#1e293b;border:1px solid #334155;padding:48px;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.5);width:420px}
  .logo{text-align:center;margin-bottom:36px}
  .logo .c-badge{background:linear-gradient(135deg,#06b6d4,#3b82f6);color:#fff;font-size:13px;font-weight:700;padding:5px 16px;border-radius:20px;display:inline-block;margin-bottom:14px;letter-spacing:.5px}
  .logo h1{color:#f1f5f9;font-size:22px;font-weight:700}
  .logo p{color:#64748b;font-size:13px;margin-top:6px}
  label{display:block;margin-bottom:6px;font-size:12px;font-weight:700;color:#94a3b8;letter-spacing:.3px;text-transform:uppercase}
  input{
    width:100%;padding:12px 16px;background:#0f172a;border:1.5px solid #334155;
    border-radius:10px;font-size:15px;margin-bottom:20px;color:#f1f5f9;
  }
  input:focus{outline:none;border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.2)}
  input::placeholder{color:#475569}
  button.login-submit{
    width:100%;padding:14px;background:linear-gradient(135deg,#06b6d4,#3b82f6);
    color:#fff;border:none;border-radius:10px;font-size:15px;cursor:pointer;font-weight:700;
    position:relative;overflow:hidden;
  }
  button.login-submit:hover{opacity:.9}
  button.login-submit:disabled{opacity:.6;cursor:not-allowed}
  .error{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3);padding:10px 14px;border-radius:8px;margin-bottom:20px;font-size:14px}
  .hint{background:rgba(59,130,246,.1);color:#93c5fd;border:1px solid rgba(59,130,246,.2);padding:10px 14px;border-radius:8px;margin-top:18px;font-size:12px;text-align:center}

  /* Spinner overlay */
  .spinner-overlay{position:fixed;inset:0;background:#0f172a;z-index:999;display:flex;flex-direction:column;align-items:center;justify-content:center;display:none}
  .spinner-overlay.show{display:flex}
  .big-spinner{width:56px;height:56px;border:4px solid #1e293b;border-top:4px solid #06b6d4;border-radius:50%;animation:sp .8s linear infinite;margin-bottom:20px}
  @keyframes sp{to{transform:rotate(360deg)}}
  .spinner-overlay h2{color:#f1f5f9;font-size:18px;margin-bottom:6px}
  .spinner-overlay p{color:#64748b;font-size:13px}
  .progress-bar{width:280px;height:4px;background:#1e293b;border-radius:2px;margin-top:20px;overflow:hidden}
  .progress-fill{height:100%;background:linear-gradient(90deg,#06b6d4,#3b82f6);width:0;animation:prog 2s ease forwards}
  @keyframes prog{to{width:100%}}
</style>
</head>
<body>

<!-- Loading overlay (shown after successful auth) -->
<div class="spinner-overlay" id="spinner-overlay">
  <div class="big-spinner"></div>
  <h2>Authenticating...</h2>
  <p>Setting up your workspace</p>
  <div class="progress-bar"><div class="progress-fill"></div></div>
</div>

<div class="card" id="login-card">
  <div class="logo">
    <div class="c-badge">⚡ COMPANY C PORTAL</div>
    <h1>Advanced Management System</h1>
    <p>Multi-step workflow with React-style UI</p>
  </div>
  <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$auth_ok): ?>
  <div class="error" id="login-error">Invalid username or password.</div>
  <?php endif; ?>
  <form method="POST" id="login-form">
    <label for="username">Username</label>
    <input type="text" name="username" id="username" placeholder="admin" autocomplete="off">
    <label for="password">Password</label>
    <input type="password" name="password" id="password" placeholder="••••••••">
    <button type="submit" class="login-submit" id="login-btn">Sign In →</button>
  </form>
  <div class="hint">🔑 <strong>admin</strong> / <strong>CompanyC#123</strong></div>
</div>

<script>
<?php if ($auth_ok): ?>
// Show spinner, redirect after 2s
document.getElementById('login-card').style.display='none';
document.getElementById('spinner-overlay').classList.add('show');
setTimeout(()=>{ window.location.href='form.php'; }, 2000);
<?php endif; ?>

document.getElementById('login-form').addEventListener('submit', function(e) {
  const u = document.getElementById('username').value.trim();
  const p = document.getElementById('password').value.trim();
  if (u === 'admin' && p === 'CompanyC#123') {
    // Show spinner immediately while form posts
    document.getElementById('login-card').style.opacity='0.5';
    document.getElementById('login-btn').disabled = true;
    document.getElementById('login-btn').textContent = 'Authenticating...';
  }
});
</script>
</body>
</html>

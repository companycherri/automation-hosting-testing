<?php
// ============================================================
// Company B Portal — OTP Verification Step
// URL: /portals/company-b/verify-otp.php
// Static OTP: 123456
// Selectors: #otp-input, #verify-btn, .resend-otp, #otp-error
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_B_SID');
session_start();

if (empty($_SESSION['login_passed'])) {
    header('Location: login.php'); exit;
}
if (!empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified'])) {
    header('Location: form.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otp = trim($_POST['otp'] ?? '');
    if ($otp === '123456') {
        $_SESSION['logged_in']    = true;
        $_SESSION['otp_verified'] = true;
        $_SESSION['company']      = 'Company B';
        setcookie('co_b_auth', 'ok', time() + 3600, '/');
        header('Location: form.php'); exit;
    }
    $error = 'Incorrect OTP. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company B — OTP Verification</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;background:linear-gradient(135deg,#1a202c 0%,#2d3748 100%);display:flex;align-items:center;justify-content:center;min-height:100vh}
  .card{background:#fff;padding:44px;border-radius:16px;box-shadow:0 20px 60px rgba(0,0,0,.4);width:420px;text-align:center}
  .otp-icon{font-size:48px;margin-bottom:12px}
  h1{color:#1a202c;font-size:22px;margin-bottom:8px}
  p.sub{color:#718096;font-size:14px;margin-bottom:8px}
  .email-show{color:#805ad5;font-weight:700;font-size:14px;margin-bottom:24px}
  .step-indicator{display:flex;gap:8px;margin-bottom:24px}
  .step{flex:1;height:4px;border-radius:2px;background:#e2e8f0}
  .step.done{background:#48bb78}.step.active{background:#805ad5}
  #otp-input{
    width:100%;padding:16px;border:2px solid #e2e8f0;border-radius:10px;
    font-size:28px;text-align:center;letter-spacing:12px;font-weight:700;
    margin-bottom:18px;
  }
  #otp-input:focus{outline:none;border-color:#805ad5;box-shadow:0 0 0 3px rgba(128,90,213,.2)}
  #verify-btn{width:100%;padding:13px;background:#805ad5;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;font-weight:700}
  #verify-btn:hover{background:#6b46c1}
  .error{background:#fed7d7;color:#c53030;padding:10px 14px;border-radius:8px;margin-bottom:18px;font-size:14px}
  .resend-otp{display:block;margin-top:16px;color:#805ad5;font-size:13px;cursor:pointer;text-decoration:underline}
  .otp-hint{background:#faf5ff;color:#553c9a;padding:10px;border-radius:8px;margin-top:16px;font-size:12px}
  #timer{color:#e53e3e;font-weight:700}
</style>
</head>
<body>
<div class="card">
  <div class="otp-icon">📱</div>
  <h1>OTP Verification</h1>
  <p class="sub">A 6-digit code was sent to</p>
  <p class="email-show"><?= htmlspecialchars($_SESSION['email'] ?? 'b.operator@company.com') ?></p>
  <div class="step-indicator">
    <div class="step done"></div>
    <div class="step active"></div>
  </div>
  <?php if ($error): ?>
  <div class="error" id="otp-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <form method="POST" id="otp-form">
    <input type="text" id="otp-input" name="otp" maxlength="6" placeholder="000000" autocomplete="off">
    <button type="submit" id="verify-btn">✓ Verify OTP</button>
  </form>
  <span class="resend-otp">Resend OTP (<span id="timer">30</span>s)</span>
  <div class="otp-hint">🔑 Static OTP for testing: <strong>123456</strong></div>
</div>
<script>
  // OTP input: auto-submit on 6 digits
  document.getElementById('otp-input').addEventListener('input', function(){
    if (this.value.length === 6) document.getElementById('verify-btn').focus();
  });
  // Timer countdown
  let t = 30;
  const ti = setInterval(()=>{ t--; document.getElementById('timer').textContent=t; if(t<=0)clearInterval(ti); }, 1000);
</script>
</body>
</html>

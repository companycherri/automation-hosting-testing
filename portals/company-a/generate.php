<?php
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_A_SID');
session_start();

$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_a_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: form.php'); exit; }

$part_no     = trim($_POST['part_no']     ?? '');
$quantity    = intval($_POST['quantity']  ?? 0);
$batch_no    = trim($_POST['batch_no']    ?? '');
$vendor_code = trim($_POST['vendor_code'] ?? '');
$delivery    = trim($_POST['delivery_date'] ?? date('Y-m-d'));
$notes       = trim($_POST['notes']       ?? '');
$priority    = trim($_POST['priority']    ?? 'normal');

// Handle optional file uploads
$upload_dir  = __DIR__ . '/generated/uploads/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
$uploaded = [];
foreach (['upload_document_1','upload_document_2','upload_document_3'] as $k) {
    if (!empty($_FILES[$k]['name']) && $_FILES[$k]['error'] === UPLOAD_ERR_OK) {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES[$k]['name']);
        move_uploaded_file($_FILES[$k]['tmp_name'], $upload_dir . $safe);
        $uploaded[] = $safe;
    }
}

if (!$part_no || $quantity <= 0 || !$batch_no || !$vendor_code) {
    die('All required fields must be filled.');
}

$upload_dir = __DIR__ . '/generated/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

$timestamp = date('YmdHis');
$filename  = "co_a_{$part_no}_{$batch_no}_{$timestamp}.txt";
$filepath  = $upload_dir . $filename;

$uploaded_str = !empty($uploaded) ? implode(', ', $uploaded) : 'None';

$content = <<<TXT
====================================================
  COMPANY A — BARCODE GENERATION SYSTEM
====================================================
  Portal         : Company A (Simple Portal)
  Part Number    : {$part_no}
  Quantity       : {$quantity}
  Batch Number   : {$batch_no}
  Vendor Code    : {$vendor_code}
  Delivery Date  : {$delivery}
  Priority       : {$priority}
  Notes          : {$notes}
  Uploaded Files : {$uploaded_str}
  Generated At   : {$timestamp}
  Operator       : {$_SESSION['username']}
----------------------------------------------------
  [||| |||| || |||| ||| || ||||  ||||  ||| ||||]
  BARCODE: {$part_no}-{$batch_no}-{$timestamp}
  [||| |||| || |||| ||| || ||||  ||||  ||| ||||]
====================================================
  STATUS: GENERATED SUCCESSFULLY — COMPANY A
====================================================
TXT;

file_put_contents($filepath, $content);
$_SESSION['co_a_last_file'] = $filename;
$_SESSION['co_a_last_data'] = compact('part_no','quantity','batch_no','vendor_code','delivery','priority','notes','timestamp','filename');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company A — Barcode Generated</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;background:#f0f4f8}
  nav{background:#2b6cb0;color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center}
  nav h1{font-size:17px} nav a{color:#bee3f8;text-decoration:none;font-size:14px}
  .wrap{max-width:620px;margin:40px auto;padding:0 16px}
  .card{background:#fff;padding:32px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  .success{background:#c6f6d5;color:#276749;padding:14px 18px;border-radius:8px;margin-bottom:22px;font-weight:700;font-size:15px}
  table{width:100%;border-collapse:collapse;margin-bottom:22px}
  td{padding:10px 12px;border-bottom:1px solid #e2e8f0;font-size:14px}
  td:first-child{color:#718096;font-weight:600;width:38%}
  pre{background:#f7fafc;border:1px solid #e2e8f0;padding:16px;border-radius:8px;font-size:12px;overflow-x:auto;margin-bottom:22px;white-space:pre-wrap}
  .actions{display:flex;gap:12px}
  #download-btn{display:inline-block;padding:12px 24px;background:#4299e1;color:#fff;text-decoration:none;border-radius:7px;font-size:14px;font-weight:700}
  .btn-back{display:inline-block;padding:12px 24px;background:#e2e8f0;color:#4a5568;text-decoration:none;border-radius:7px;font-size:14px}
</style>
</head>
<body>
<nav><h1>🏢 Company A — Barcode Generated</h1><a href="logout.php">Logout</a></nav>
<div class="wrap"><div class="card">
  <div class="success">✅ Barcode generated successfully!</div>
  <table>
    <tr><td>Part Number</td><td><?= htmlspecialchars($part_no) ?></td></tr>
    <tr><td>Quantity</td><td><?= $quantity ?></td></tr>
    <tr><td>Batch Number</td><td><?= htmlspecialchars($batch_no) ?></td></tr>
    <tr><td>Vendor Code</td><td><?= htmlspecialchars($vendor_code) ?></td></tr>
    <tr><td>Priority</td><td><?= ucfirst($priority) ?></td></tr>
    <tr><td>File</td><td><?= htmlspecialchars($filename) ?></td></tr>
    <?php if (!empty($uploaded)): ?>
    <tr>
      <td>Uploaded Files</td>
      <td>
        <?php foreach ($uploaded as $uf): ?>
          <span style="display:inline-block;background:#c6f6d5;color:#276749;padding:2px 8px;border-radius:4px;font-size:12px;margin:2px;font-weight:600">
            📎 <?= htmlspecialchars($uf) ?>
          </span>
        <?php endforeach; ?>
      </td>
    </tr>
    <?php endif; ?>
  </table>
  <pre><?= htmlspecialchars($content) ?></pre>
  <div class="actions">
    <a href="download.php?file=<?= urlencode($filename) ?>" id="download-btn">⬇ Download File</a>
    <a href="form.php" class="btn-back">← Generate Another</a>
  </div>
</div></div>
</body>
</html>

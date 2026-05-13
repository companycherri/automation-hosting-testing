<?php
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_C_SID');
session_start();

$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_c_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: form.php'); exit; }

$part_no   = trim($_POST['part_no']      ?? '');
$quantity  = intval($_POST['quantity']   ?? 0);
$batch_no  = trim($_POST['batch_no']     ?? '');
$vendors   = trim($_POST['vendors']      ?? '');
$category  = trim($_POST['category']     ?? '');
$part_name = trim($_POST['part_name']    ?? '');
$delivery  = trim($_POST['delivery_date']?? date('Y-m-d'));
$priority  = trim($_POST['priority']     ?? 'normal');
$notes     = trim($_POST['notes']        ?? '');

if (!$part_no || $quantity <= 0 || !$batch_no || !$vendors) {
    die('All required fields must be filled.');
}

// Handle file uploads
$uploads_dir = __DIR__ . '/generated/uploads/';
if (!is_dir($uploads_dir)) mkdir($uploads_dir, 0755, true);

$uploaded_file = '';
if (!empty($_FILES['document']['name']) && $_FILES['document']['error'] === UPLOAD_ERR_OK) {
    $safe_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['document']['name']);
    move_uploaded_file($_FILES['document']['tmp_name'], $uploads_dir . $safe_name);
    $uploaded_file = $safe_name;
}

$extra_uploads = [];
foreach (['upload_document_1','upload_document_2','upload_document_3'] as $k) {
    if (!empty($_FILES[$k]['name']) && $_FILES[$k]['error'] === UPLOAD_ERR_OK) {
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES[$k]['name']);
        move_uploaded_file($_FILES[$k]['tmp_name'], $uploads_dir . $safe);
        $extra_uploads[] = $safe;
    }
}

$gen_dir = __DIR__ . '/generated/';
if (!is_dir($gen_dir)) mkdir($gen_dir, 0755, true);

$timestamp = date('YmdHis');
$filename  = "co_c_{$part_no}_{$batch_no}_{$timestamp}.txt";
$filepath  = $gen_dir . $filename;

$vendor_list    = implode(', ', explode(',', $vendors));
$all_uploads    = array_filter(array_merge(
    $uploaded_file ? [$uploaded_file] : [],
    $extra_uploads
));
$uploaded_str   = !empty($all_uploads) ? implode(', ', $all_uploads) : 'None';

$content = <<<TXT
====================================================
  COMPANY C — ADVANCED MANAGEMENT PORTAL
====================================================
  Portal         : Company C (Multi-Step Workflow)
  Category       : {$category}
  Part Name      : {$part_name}
  Part Code      : {$part_no}
  Quantity       : {$quantity}
  Batch Number   : {$batch_no}
  Vendors        : {$vendor_list}
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
  STATUS: GENERATED — COMPANY C PORTAL
====================================================
TXT;

file_put_contents($filepath, $content);
$_SESSION['co_c_last_file'] = $filename;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company C — Generated</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a}
  nav{background:#1e293b;border-bottom:1px solid #334155;padding:14px 28px;display:flex;justify-content:space-between}
  nav h1{color:#f1f5f9;font-size:16px} nav a{color:#38bdf8;text-decoration:none;font-size:13px}
  .wrap{max-width:640px;margin:36px auto;padding:0 16px}
  .card{background:#1e293b;border:1px solid #334155;padding:32px;border-radius:16px}
  .success{background:rgba(16,185,129,.15);color:#34d399;border:1px solid rgba(16,185,129,.3);padding:14px 18px;border-radius:10px;margin-bottom:22px;font-weight:700}
  table{width:100%;border-collapse:collapse;margin-bottom:22px}
  td{padding:10px 12px;border-bottom:1px solid #334155;font-size:13px;color:#cbd5e1}
  td:first-child{color:#64748b;font-weight:600;width:38%}
  pre{background:#0f172a;border:1px solid #334155;padding:16px;border-radius:10px;font-size:11px;overflow-x:auto;margin-bottom:22px;white-space:pre-wrap;color:#94a3b8}
  .actions{display:flex;gap:12px}
  #download-btn{display:inline-block;padding:12px 24px;background:linear-gradient(135deg,#06b6d4,#3b82f6);color:#fff;text-decoration:none;border-radius:9px;font-size:14px;font-weight:700}
  .btn-back{display:inline-block;padding:12px 24px;background:#1e293b;border:1.5px solid #334155;color:#94a3b8;text-decoration:none;border-radius:9px;font-size:14px}
</style>
</head>
<body>
<nav><h1>⚡ Company C — Barcode Generated</h1><a href="logout.php">Sign Out</a></nav>
<div class="wrap"><div class="card">
  <div class="success">✅ Multi-step order processed successfully — Company C Portal</div>
  <table>
    <tr><td>Category</td><td><?= htmlspecialchars($category) ?></td></tr>
    <tr><td>Part Code</td><td><?= htmlspecialchars($part_no) ?></td></tr>
    <tr><td>Part Name</td><td><?= htmlspecialchars($part_name) ?></td></tr>
    <tr><td>Quantity</td><td><?= $quantity ?></td></tr>
    <tr><td>Batch Number</td><td><?= htmlspecialchars($batch_no) ?></td></tr>
    <tr><td>Vendors</td><td><?= htmlspecialchars($vendor_list) ?></td></tr>
    <tr><td>Priority</td><td><?= ucfirst($priority) ?></td></tr>
    <tr><td>File</td><td><?= htmlspecialchars($filename) ?></td></tr>
    <?php if (!empty($all_uploads)): ?>
    <tr>
      <td>Uploaded Files</td>
      <td><?php foreach ($all_uploads as $uf): ?>
        <span style="display:inline-block;background:rgba(6,182,212,.15);color:#38bdf8;padding:2px 8px;border-radius:4px;font-size:12px;margin:2px;font-weight:600">
          📎 <?= htmlspecialchars($uf) ?>
        </span>
      <?php endforeach; ?></td>
    </tr>
    <?php endif; ?>
  </table>
  <pre><?= htmlspecialchars($content) ?></pre>
  <div class="actions">
    <a href="download.php?file=<?= urlencode($filename) ?>" id="download-btn">⬇ Download File</a>
    <a href="form.php" class="btn-back">← New Order</a>
  </div>
</div></div>
</body>
</html>

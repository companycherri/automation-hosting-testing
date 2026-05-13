<?php
// ============================================================
// Dummy Customer Portal — Generate Barcode & Provide Download
// POST fields: part_no, quantity, batch_no, vendor_code
// Generates a plain-text barcode file and offers a download link
// ============================================================

// ── Same session fix as login.php ─────────────────────────
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('PORTAL_SID');
session_start();

// Accept EITHER session OR the backup cookie
$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['portal_auth'] ?? '') === 'ok';

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: barcode-form.php');
    exit;
}

$part_no     = trim($_POST['part_no']     ?? '');
$quantity    = intval($_POST['quantity']  ?? 0);
$batch_no    = trim($_POST['batch_no']    ?? '');
$vendor_code = trim($_POST['vendor_code'] ?? '');

if (empty($part_no) || $quantity <= 0 || empty($batch_no) || empty($vendor_code)) {
    die('All fields are required.');
}

// Create uploads directory inside dummy-portal
$upload_dir = __DIR__ . '/generated/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// Generate unique filename
$timestamp = date('YmdHis');
$filename  = "barcode_{$part_no}_{$batch_no}_{$timestamp}.txt";
$filepath  = $upload_dir . $filename;

// Simulate barcode content (ASCII art style)
$barcode_content = <<<TXT
========================================
       BARCODE GENERATION SYSTEM
========================================
  Part Number  : {$part_no}
  Quantity     : {$quantity}
  Batch Number : {$batch_no}
  Vendor Code  : {$vendor_code}
  Generated At : {$timestamp}
----------------------------------------
  [|||  ||||  ||  ||||  |||  ||  ||||]
  [Simulated Barcode: {$part_no}-{$batch_no}]
  [|||  ||||  ||  ||||  |||  ||  ||||]
========================================
  STATUS: GENERATED SUCCESSFULLY
========================================
TXT;

file_put_contents($filepath, $barcode_content);

// Store filename in session so download.php can serve it
$_SESSION['last_barcode_file'] = $filename;
$_SESSION['last_barcode_data'] = [
    'part_no'     => $part_no,
    'quantity'    => $quantity,
    'batch_no'    => $batch_no,
    'vendor_code' => $vendor_code,
    'timestamp'   => $timestamp,
    'filename'    => $filename,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Portal — Barcode Generated</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; background: #f0f2f5; }
        nav { background: #2d3748; color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        nav h1 { font-size: 18px; }
        nav a { color: #90cdf4; text-decoration: none; font-size: 14px; }
        .container { max-width: 600px; margin: 40px auto; padding: 0 16px; }
        .card { background: #fff; padding: 32px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .success { background: #c6f6d5; color: #276749; padding: 14px 18px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        td { padding: 10px 12px; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        td:first-child { color: #718096; font-weight: 600; width: 40%; }
        .btn-download { display: inline-block; padding: 12px 28px; background: #4299e1; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px; margin-right: 12px; }
        .btn-download:hover { background: #3182ce; }
        .btn-back { display: inline-block; padding: 12px 28px; background: #e2e8f0; color: #4a5568; text-decoration: none; border-radius: 6px; font-size: 15px; }
        pre { background: #f7fafc; border: 1px solid #e2e8f0; padding: 16px; border-radius: 6px; font-size: 12px; overflow-x: auto; margin-bottom: 20px; }
    </style>
</head>
<body>
<nav>
    <h1>🏭 Demo Barcode Portal</h1>
    <a href="logout.php">Logout</a>
</nav>
<div class="container">
    <div class="card">
        <div class="success">✅ Barcode generated successfully!</div>
        <table>
            <tr><td>Part Number</td><td><?= htmlspecialchars($part_no) ?></td></tr>
            <tr><td>Quantity</td><td><?= htmlspecialchars($quantity) ?></td></tr>
            <tr><td>Batch Number</td><td><?= htmlspecialchars($batch_no) ?></td></tr>
            <tr><td>Vendor Code</td><td><?= htmlspecialchars($vendor_code) ?></td></tr>
            <tr><td>File</td><td><?= htmlspecialchars($filename) ?></td></tr>
        </table>

        <pre><?= htmlspecialchars($barcode_content) ?></pre>

        <!-- id="download-btn" lets the Playwright bot locate the download link -->
        <a href="download.php?file=<?= urlencode($filename) ?>" id="download-btn" class="btn-download">⬇ Download Barcode File</a>
        <a href="barcode-form.php" class="btn-back">← Generate Another</a>
    </div>
</div>
</body>
</html>

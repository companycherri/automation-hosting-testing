<?php
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

$parts = [
    ['code' => 'ENG-001', 'name' => 'Engine Block'],
    ['code' => 'TRN-002', 'name' => 'Transmission Case'],
    ['code' => 'BRK-003', 'name' => 'Brake Caliper'],
    ['code' => 'STR-004', 'name' => 'Steering Wheel'],
    ['code' => 'FUL-005', 'name' => 'Fuel Injector'],
    ['code' => 'ALT-006', 'name' => 'Alternator'],
    ['code' => 'RAD-007', 'name' => 'Radiator Cap'],
    ['code' => 'OIL-008', 'name' => 'Oil Filter'],
    ['code' => 'SPK-009', 'name' => 'Spark Plug'],
    ['code' => 'AIR-010', 'name' => 'Air Filter'],
];
$vendors = ['VND-001','VND-002','VND-003','VND-004','VND-005'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Demo Portal — Barcode Form</title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:Arial,sans-serif;background:#f0f2f5}
        nav{background:#2d3748;color:#fff;padding:14px 24px;display:flex;justify-content:space-between;align-items:center}
        nav a{color:#90cdf4;text-decoration:none;font-size:14px}
        .wrap{max-width:560px;margin:40px auto;padding:0 16px}
        .card{background:#fff;padding:32px;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,.08)}
        h2{margin-bottom:24px;color:#1a202c;font-size:20px}
        label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#4a5568}
        select,input{
            width:100%;padding:10px 12px;border:1px solid #cbd5e0;
            border-radius:6px;font-size:15px;margin-bottom:18px;
            background:#fff;color:#1a202c;appearance:auto;
        }
        select:focus,input:focus{outline:none;border-color:#4299e1;box-shadow:0 0 0 3px rgba(66,153,225,.2)}
        button{padding:12px 32px;background:#48bb78;color:#fff;border:none;border-radius:6px;font-size:15px;cursor:pointer;font-weight:700}
        button:hover{background:#38a169}
    </style>
</head>
<body>
<nav>
    <h1>🏭 Demo Barcode Portal</h1>
    <a href="logout.php">Logout</a>
</nav>
<div class="wrap"><div class="card">
    <h2>Generate Barcode</h2>
    <form method="POST" action="generate-barcode.php" id="barcode-form">

        <label for="part_no">Part Name</label>
        <select name="part_no" id="part_no" required>
            <option value="">-- Select Part --</option>
            <?php foreach($parts as $p): ?>
            <option value="<?= $p['code'] ?>"><?= $p['name'] ?> (<?= $p['code'] ?>)</option>
            <?php endforeach; ?>
        </select>

        <label for="quantity">Quantity</label>
        <input type="number" id="quantity" name="quantity" min="1" placeholder="e.g. 100" required>

        <label for="batch_no">Batch Number</label>
        <input type="text" id="batch_no" name="batch_no" placeholder="e.g. BATCH-2024-01" required>

        <label for="vendor_code">Vendor Code</label>
        <select name="vendor_code" id="vendor_code" required>
            <option value="">-- Select Vendor --</option>
            <?php foreach($vendors as $v): ?>
            <option value="<?= $v ?>"><?= $v ?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit" id="submit-btn">Generate Barcode</button>
    </form>
</div></div>
</body>
</html>

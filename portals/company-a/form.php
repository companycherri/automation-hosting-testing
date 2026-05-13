<?php
// ============================================================
// Company A Portal — Barcode Form
// Type: Normal HTML form — all standard selectors (id-based)
// Fields: #part_no (select), #quantity (number), #batch_no (text),
//         #delivery_date (date), #notes (textarea),
//         #vendor_code (select), #priority (radio), #submit-btn
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_A_SID');
session_start();

$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_a_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }

$parts = [
    'ENG-001'=>'Engine Block','TRN-002'=>'Transmission Case','BRK-003'=>'Brake Caliper',
    'STR-004'=>'Steering Wheel','FUL-005'=>'Fuel Injector','ALT-006'=>'Alternator',
    'RAD-007'=>'Radiator Cap','OIL-008'=>'Oil Filter','SPK-009'=>'Spark Plug','AIR-010'=>'Air Filter',
];
$vendors = ['VND-A-001','VND-A-002','VND-A-003','VND-A-004','VND-A-005'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company A — Barcode Form</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;background:#f0f4f8}
  nav{background:#2b6cb0;color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center}
  nav h1{font-size:17px}
  nav span{font-size:13px;opacity:.8}
  nav a{color:#bee3f8;text-decoration:none;font-size:14px;margin-left:16px}
  .wrap{max-width:640px;margin:40px auto;padding:0 16px}
  .card{background:#fff;padding:36px;border-radius:12px;box-shadow:0 4px 24px rgba(0,0,0,.08)}
  h2{margin-bottom:8px;color:#1a202c;font-size:20px}
  .subtitle{color:#718096;font-size:13px;margin-bottom:28px}
  .section{margin-bottom:24px}
  .section-title{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#a0aec0;font-weight:700;margin-bottom:14px;padding-bottom:6px;border-bottom:1px solid #e2e8f0}
  label{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#4a5568}
  select,input,textarea{
    width:100%;padding:10px 13px;border:1.5px solid #cbd5e0;
    border-radius:7px;font-size:14px;margin-bottom:16px;
    background:#fff;color:#1a202c;
  }
  select:focus,input:focus,textarea:focus{outline:none;border-color:#4299e1;box-shadow:0 0 0 3px rgba(66,153,225,.15)}
  textarea{resize:vertical;min-height:80px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .radio-group{display:flex;gap:20px;margin-bottom:16px}
  .radio-group label{display:flex;align-items:center;gap:6px;font-weight:500;cursor:pointer;margin-bottom:0}
  .radio-group input[type=radio]{width:auto;margin-bottom:0}
  #submit-btn{width:100%;padding:13px;background:#48bb78;color:#fff;border:none;border-radius:7px;font-size:15px;cursor:pointer;font-weight:700;margin-top:8px}
  #submit-btn:hover{background:#38a169}
  #submit-btn:disabled{background:#a0aec0;cursor:not-allowed}
</style>
</head>
<body>
<nav>
  <h1>🏢 Company A — Barcode Generation</h1>
  <div>
    <span>👤 <?= htmlspecialchars($_SESSION['username'] ?? 'operator') ?></span>
    <a href="logout.php">Logout</a>
  </div>
</nav>
<div class="wrap"><div class="card">
  <h2>Generate Barcode</h2>
  <p class="subtitle">Fill all fields below to generate a barcode label.</p>
  <form method="POST" action="generate.php" enctype="multipart/form-data" id="barcode-form">

    <div class="section">
      <p class="section-title">Part Information</p>
      <label for="part_no">Part Number</label>
      <select name="part_no" id="part_no" required>
        <option value="">-- Select Part --</option>
        <?php foreach($parts as $code=>$name): ?>
        <option value="<?= $code ?>"><?= $name ?> (<?= $code ?>)</option>
        <?php endforeach; ?>
      </select>

      <div class="grid2">
        <div>
          <label for="quantity">Quantity <span style="color:#a0aec0;font-weight:400;font-size:11px">(min. 100)</span></label>
          <input type="number" id="quantity" name="quantity" min="1" max="9999" placeholder="e.g. 100" required>
          <div id="qty-error" data-testid="qty-error"
               style="display:none;color:#c53030;background:#fff5f5;border:1px solid #fed7d7;
                      padding:7px 11px;border-radius:6px;font-size:12px;font-weight:600;
                      margin-top:-10px;margin-bottom:14px">
            ⚠ Quantity must be minimum 100
          </div>
        </div>
        <div>
          <label for="batch_no">Batch Number</label>
          <input type="text" id="batch_no" name="batch_no" placeholder="BATCH-2024-01" required>
        </div>
      </div>

      <label for="delivery_date">Delivery Date</label>
      <input type="date" id="delivery_date" name="delivery_date">

      <label for="notes">Notes</label>
      <textarea id="notes" name="notes" placeholder="Optional notes about this batch..."></textarea>
    </div>

    <div class="section">
      <p class="section-title">Vendor &amp; Priority</p>
      <label for="vendor_code">Vendor Code</label>
      <select name="vendor_code" id="vendor_code" required>
        <option value="">-- Select Vendor --</option>
        <?php foreach($vendors as $v): ?>
        <option value="<?= $v ?>"><?= $v ?></option>
        <?php endforeach; ?>
      </select>

      <label>Priority</label>
      <div class="radio-group">
        <label><input type="radio" id="priority_normal"   name="priority" value="normal"   checked> Normal</label>
        <label><input type="radio" id="priority_urgent"   name="priority" value="urgent">   Urgent</label>
        <label><input type="radio" id="priority_critical" name="priority" value="critical"> Critical</label>
      </div>
    </div>

    <div class="section">
      <p class="section-title">Supporting Document (Optional)</p>

      <label for="upload-file-1">Upload File 1</label>
      <input type="file" id="upload-file-1" name="upload_document_1"
             data-testid="upload-file-1" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt">
      <div id="upload-label-1" data-testid="upload-label-1"
           style="display:none;margin:-8px 0 14px;padding:8px 12px;background:#c6f6d5;color:#276749;border-radius:6px;font-size:13px;font-weight:600"></div>

      <label for="upload-file-2">Upload File 2</label>
      <input type="file" id="upload-file-2" name="upload_document_2"
             data-testid="upload-file-2" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt">
      <div id="upload-label-2" data-testid="upload-label-2"
           style="display:none;margin:-8px 0 14px;padding:8px 12px;background:#c6f6d5;color:#276749;border-radius:6px;font-size:13px;font-weight:600"></div>

      <label for="upload-file-3">Upload File 3</label>
      <input type="file" id="upload-file-3" name="upload_document_3"
             data-testid="upload-file-3" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt">
      <div id="upload-label-3" data-testid="upload-label-3"
           style="display:none;margin:-8px 0 14px;padding:8px 12px;background:#c6f6d5;color:#276749;border-radius:6px;font-size:13px;font-weight:600"></div>
    </div>

    <button type="submit" id="submit-btn">🏷 Generate Barcode</button>
  </form>
</div></div>
<script>
// ── Quantity validation: min = 100 ─────────────────────────
// Bot enters the Excel value exactly. If below 100, error shows and
// submit is disabled. Bot detects this and records the portal error.
(function() {
  var qtyInput  = document.getElementById('quantity');
  var qtyError  = document.getElementById('qty-error');
  var submitBtn = document.getElementById('submit-btn');

  function validateQty() {
    var raw = qtyInput.value;
    var val = parseInt(raw, 10);
    var invalid = raw !== '' && !isNaN(val) && val < 100;

    if (invalid) {
      qtyError.style.display   = 'block';
      qtyInput.style.borderColor = '#e53e3e';
      qtyInput.setAttribute('aria-invalid', 'true');
      submitBtn.disabled = true;
    } else {
      qtyError.style.display   = 'none';
      qtyInput.style.borderColor = '';
      qtyInput.removeAttribute('aria-invalid');
      submitBtn.disabled = false;
    }
  }

  qtyInput.addEventListener('input',  validateQty);
  qtyInput.addEventListener('change', validateQty);
  // Run once on load (handles pre-filled values)
  validateQty();
})();

// Show filename indicator after file is selected (manual or via Playwright set_input_files)
[1,2,3].forEach(function(n) {
  var input = document.getElementById('upload-file-' + n);
  var label = document.getElementById('upload-label-' + n);
  if (!input || !label) return;
  function updateLabel() {
    if (input.files && input.files.length > 0) {
      label.textContent = '✅ Uploaded File: ' + input.files[0].name;
      label.style.display = 'block';
    } else {
      label.style.display = 'none';
    }
  }
  input.addEventListener('change', updateLabel);
  // Also poll briefly after page load in case Playwright already set a file
  setTimeout(updateLabel, 300);
  setTimeout(updateLabel, 800);
});
</script>
</body>
</html>

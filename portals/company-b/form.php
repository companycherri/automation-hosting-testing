<?php
// ============================================================
// Company B Portal — Advanced Barcode Form
// Features:
//   - Searchable dropdown for Part Number (data-testid selectors)
//   - AJAX-loaded Vendor dropdown (loads with delay)
//   - Modal confirmation popup before submit
//   - Iframe contact field
//   - Radio buttons for priority
//   - Disabled submit until vendor loaded
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_B_SID');
session_start();

$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_b_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company B — Barcode Form</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;background:#1a202c}
  nav{background:#2d3748;color:#fff;padding:14px 28px;display:flex;justify-content:space-between;align-items:center;border-bottom:2px solid #805ad5}
  nav h1{font-size:17px} nav a{color:#b794f4;text-decoration:none;font-size:14px;margin-left:12px}
  nav .badge{background:#805ad5;color:#fff;font-size:11px;padding:3px 10px;border-radius:12px;margin-left:8px}
  .wrap{max-width:700px;margin:36px auto;padding:0 16px}
  .card{background:#fff;padding:36px;border-radius:14px;box-shadow:0 8px 40px rgba(0,0,0,.3)}
  h2{margin-bottom:6px;color:#1a202c;font-size:20px}
  .subtitle{color:#718096;font-size:13px;margin-bottom:28px}
  .section-title{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#a0aec0;font-weight:700;margin-bottom:14px;padding-bottom:6px;border-bottom:1px solid #e2e8f0;margin-top:6px}
  label{display:block;margin-bottom:6px;font-size:13px;font-weight:600;color:#4a5568}
  input[type=text],input[type=number],textarea{
    width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;
    border-radius:8px;font-size:14px;margin-bottom:16px;background:#fff;
  }
  input:focus,textarea:focus{outline:none;border-color:#805ad5;box-shadow:0 0 0 3px rgba(128,90,213,.15)}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}

  /* ─── Searchable Dropdown ─── */
  .search-dropdown{position:relative;margin-bottom:16px}
  .search-dropdown input{margin-bottom:0;padding-right:36px}
  .search-dropdown .sd-arrow{position:absolute;right:12px;top:11px;color:#a0aec0;font-size:14px;pointer-events:none}
  .sd-list{
    position:absolute;top:100%;left:0;right:0;background:#fff;
    border:1.5px solid #805ad5;border-radius:8px;max-height:220px;
    overflow-y:auto;z-index:100;display:none;box-shadow:0 8px 24px rgba(0,0,0,.12);
  }
  .sd-list.open{display:block}
  .sd-item{padding:10px 14px;cursor:pointer;font-size:14px;border-bottom:1px solid #f7fafc}
  .sd-item:hover,.sd-item.highlighted{background:#faf5ff;color:#553c9a}
  .sd-item .code{font-size:11px;color:#a0aec0;margin-left:6px}
  .sd-empty{padding:12px;color:#a0aec0;font-size:13px;text-align:center}

  /* ─── AJAX Vendor Dropdown ─── */
  .ajax-wrap{position:relative;margin-bottom:16px}
  .ajax-loading{display:flex;align-items:center;gap:10px;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;color:#a0aec0;font-size:14px;background:#f7fafc}
  .spinner{width:18px;height:18px;border:2px solid #e2e8f0;border-top-color:#805ad5;border-radius:50%;animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  #vendor-select{width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;display:none}
  #vendor-select.loaded{display:block;border-color:#48bb78}

  /* ─── Radio ─── */
  .radio-group{display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap}
  .radio-opt{display:flex;align-items:center;gap:7px;cursor:pointer;padding:8px 16px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px}
  .radio-opt input[type=radio]{accent-color:#805ad5}
  .radio-opt.selected{border-color:#805ad5;background:#faf5ff;color:#553c9a}

  /* ─── Submit ─── */
  #submit-btn,[data-testid="submit-form"]{
    width:100%;padding:13px;background:#805ad5;color:#fff;border:none;
    border-radius:8px;font-size:15px;cursor:pointer;font-weight:700;margin-top:8px;
  }
  [data-testid="submit-form"]:hover{background:#6b46c1}
  [data-testid="submit-form"]:disabled{background:#a0aec0;cursor:not-allowed}

  /* ─── Modal ─── */
  .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000;display:none;align-items:center;justify-content:center}
  .modal-overlay.show{display:flex}
  .modal-box{background:#fff;border-radius:16px;padding:36px;max-width:440px;width:90%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)}
  .modal-icon{font-size:48px;margin-bottom:12px}
  .modal-box h3{font-size:20px;margin-bottom:8px;color:#1a202c}
  .modal-box p{color:#718096;font-size:14px;margin-bottom:24px}
  .modal-details{background:#f7fafc;border-radius:8px;padding:16px;margin-bottom:24px;text-align:left;font-size:13px;line-height:2}
  .modal-details strong{color:#1a202c}
  .modal-actions{display:flex;gap:12px}
  [data-testid="confirm-order"]{flex:1;padding:12px;background:#805ad5;color:#fff;border:none;border-radius:8px;font-size:15px;cursor:pointer;font-weight:700}
  [data-testid="confirm-order"]:hover{background:#6b46c1}
  [data-testid="cancel-order"]{flex:1;padding:12px;background:#e2e8f0;color:#4a5568;border:none;border-radius:8px;font-size:15px;cursor:pointer}

  /* ─── iFrame section ─── */
  .iframe-section{margin-bottom:24px;border:1.5px solid #e2e8f0;border-radius:10px;overflow:hidden}
  .iframe-header{background:#f7fafc;padding:10px 16px;font-size:12px;color:#718096;font-weight:700;border-bottom:1px solid #e2e8f0}
  iframe{width:100%;border:none;height:120px}
</style>
</head>
<body>
<nav>
  <h1>Company B — Advanced Barcode Form <span class="badge">🔒 Verified</span></h1>
  <div>
    <a href="logout.php">Logout</a>
  </div>
</nav>
<div class="wrap"><div class="card">
  <h2>Generate Barcode Order</h2>
  <p class="subtitle">Company B — Advanced portal with searchable dropdowns and AJAX loading</p>

  <form id="barcode-form" method="POST" action="generate.php" enctype="multipart/form-data">

    <!-- Hidden fields populated by JS -->
    <input type="hidden" name="part_no"     id="hidden-part-no">
    <input type="hidden" name="vendor_code" id="hidden-vendor-code">

    <p class="section-title">Part Selection (Searchable Dropdown)</p>

    <!-- Searchable dropdown using data-testid -->
    <label for="part-search-input">Part Number</label>
    <div class="search-dropdown" data-testid="part-search">
      <input type="text" id="part-search-input" data-testid="part-search-input"
             placeholder="Type to search parts..." autocomplete="off">
      <span class="sd-arrow">▼</span>
      <div class="sd-list" id="part-dropdown" data-testid="part-dropdown"></div>
    </div>
    <input type="text" id="part-display" data-testid="part-selected-display"
           style="background:#f7fafc;color:#805ad5;font-weight:700;font-size:13px;margin-bottom:16px;border-color:#e2e8f0"
           readonly placeholder="No part selected yet" tabindex="-1">

    <div class="grid2">
      <div>
        <label>Quantity</label>
        <input type="number" name="quantity" id="quantity" min="1" max="9999" placeholder="e.g. 250">
      </div>
      <div>
        <label>Batch Number</label>
        <input type="text" name="batch_no" id="batch_no" placeholder="B-2024-XXX">
      </div>
    </div>

    <p class="section-title">Vendor (AJAX Loaded — waits 1.5s)</p>
    <label>Vendor Code</label>
    <div class="ajax-wrap">
      <div class="ajax-loading" id="vendor-loading">
        <div class="spinner"></div>
        <span>Loading vendors from server...</span>
      </div>
      <select id="vendor-select" data-testid="vendor-select">
        <option value="">-- Select Vendor --</option>
      </select>
    </div>

    <p class="section-title">Contact Person (Iframe Field)</p>
    <div class="iframe-section">
      <div class="iframe-header">📋 Contact Details — loaded in iframe</div>
      <iframe src="api/contact-iframe.php" id="contact-iframe" data-testid="contact-iframe"
              scrolling="no"></iframe>
    </div>

    <p class="section-title">Priority</p>
    <div class="radio-group" id="priority-group">
      <label class="radio-opt selected">
        <input type="radio" name="priority" value="normal" checked> Normal
      </label>
      <label class="radio-opt">
        <input type="radio" name="priority" value="urgent"> Urgent
      </label>
      <label class="radio-opt">
        <input type="radio" name="priority" value="express"> Express
      </label>
    </div>

    <p class="section-title">Supporting Document (Optional)</p>
    <label for="upload-file-1">Upload File 1</label>
    <input type="file" id="upload-file-1" name="upload_document_1"
           data-testid="upload-file-1" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
           style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;margin-bottom:8px;background:#fff;">
    <div id="upload-label-1" data-testid="upload-label-1"
         style="display:none;margin-bottom:14px;padding:8px 12px;background:#d4edda;color:#155724;border-radius:6px;font-size:13px;font-weight:600"></div>

    <label for="upload-file-2">Upload File 2</label>
    <input type="file" id="upload-file-2" name="upload_document_2"
           data-testid="upload-file-2" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
           style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;margin-bottom:8px;background:#fff;">
    <div id="upload-label-2" data-testid="upload-label-2"
         style="display:none;margin-bottom:14px;padding:8px 12px;background:#d4edda;color:#155724;border-radius:6px;font-size:13px;font-weight:600"></div>

    <label for="upload-file-3">Upload File 3</label>
    <input type="file" id="upload-file-3" name="upload_document_3"
           data-testid="upload-file-3" accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
           style="width:100%;padding:10px 13px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:14px;margin-bottom:8px;background:#fff;">
    <div id="upload-label-3" data-testid="upload-label-3"
         style="display:none;margin-bottom:14px;padding:8px 12px;background:#d4edda;color:#155724;border-radius:6px;font-size:13px;font-weight:600"></div>

    <button type="button" data-testid="submit-form" id="submit-btn" disabled
            onclick="showModal()">🏷 Generate Barcode</button>
  </form>

  <!-- Modal overlay -->
  <div class="modal-overlay" id="confirm-modal">
    <div class="modal-box">
      <div class="modal-icon">📋</div>
      <h3>Confirm Barcode Order</h3>
      <p>Please review the details before generating.</p>
      <div class="modal-details" id="modal-summary"></div>
      <div class="modal-actions">
        <button data-testid="cancel-order" onclick="hideModal()">✕ Cancel</button>
        <button data-testid="confirm-order" onclick="submitForm()">✓ Confirm &amp; Generate</button>
      </div>
    </div>
  </div>
</div></div>

<script>
// ─── Part search dropdown ───────────────────────────────────
const allParts = [];
let selectedPart = null;

// Fetch all parts on load
fetch('api/parts.php')
  .then(r=>r.json())
  .then(data=>{ allParts.push(...data); });

const searchInput = document.getElementById('part-search-input');
const dropdown    = document.getElementById('part-dropdown');
const hiddenPart  = document.getElementById('hidden-part-no');
const partDisplay = document.getElementById('part-display');

function renderParts(items) {
  if (!items.length) {
    dropdown.innerHTML = '<div class="sd-empty">No parts found</div>';
    dropdown.classList.add('open');
    return;
  }
  dropdown.innerHTML = items.map(p =>
    `<div class="sd-item" data-testid="part-option" data-code="${p.code}" data-name="${p.name}"
          onclick="selectPart('${p.code}','${p.name}')">
      ${p.name} <span class="code">${p.code}</span>
    </div>`
  ).join('');
  dropdown.classList.add('open');
}

searchInput.addEventListener('focus', ()=>{ renderParts(allParts); });
searchInput.addEventListener('input', function() {
  const q = this.value.toLowerCase();
  const filtered = allParts.filter(p =>
    p.name.toLowerCase().includes(q) || p.code.toLowerCase().includes(q)
  );
  renderParts(filtered);
});
document.addEventListener('click', function(e) {
  if (!e.target.closest('.search-dropdown')) dropdown.classList.remove('open');
});

function selectPart(code, name) {
  selectedPart = code;
  hiddenPart.value = code;
  searchInput.value = name + ' (' + code + ')';
  partDisplay.value = '✓ Selected: ' + code + ' — ' + name;
  dropdown.classList.remove('open');
  checkReady();
}

// ─── AJAX Vendor dropdown ───────────────────────────────────
fetch('api/vendors.php')
  .then(r=>r.json())
  .then(data=>{
    const sel = document.getElementById('vendor-select');
    data.vendors.forEach(v => {
      const o = document.createElement('option');
      o.value = v.code; o.textContent = v.name + ' (' + v.code + ')';
      sel.appendChild(o);
    });
    document.getElementById('vendor-loading').style.display='none';
    sel.classList.add('loaded');
    sel.addEventListener('change', function(){
      document.getElementById('hidden-vendor-code').value = this.value;
      checkReady();
    });
  });

// ─── Enable submit when all required fields filled ─────────
function checkReady() {
  const qty    = document.getElementById('quantity').value;
  const batch  = document.getElementById('batch_no').value;
  const vendor = document.getElementById('vendor-select').value;
  const ready  = selectedPart && qty && batch && vendor;
  document.getElementById('submit-btn').disabled = !ready;
}
document.getElementById('quantity').addEventListener('input', checkReady);
document.getElementById('batch_no').addEventListener('input', checkReady);

// ─── Radio styling ──────────────────────────────────────────
document.querySelectorAll('.radio-opt input[type=radio]').forEach(r=>{
  r.addEventListener('change', ()=>{
    document.querySelectorAll('.radio-opt').forEach(o=>o.classList.remove('selected'));
    r.closest('.radio-opt').classList.add('selected');
  });
});

// ─── Modal ─────────────────────────────────────────────────
function showModal() {
  const part   = document.getElementById('hidden-part-no').value;
  const qty    = document.getElementById('quantity').value;
  const batch  = document.getElementById('batch_no').value;
  const vendor = document.getElementById('vendor-select');
  const vName  = vendor.options[vendor.selectedIndex]?.text || vendor.value;
  const pri    = document.querySelector('input[name=priority]:checked')?.value;

  document.getElementById('modal-summary').innerHTML =
    `<div>🔩 <strong>Part:</strong> ${part}</div>
     <div>📦 <strong>Qty:</strong> ${qty}</div>
     <div>🏷 <strong>Batch:</strong> ${batch}</div>
     <div>🏭 <strong>Vendor:</strong> ${vName}</div>
     <div>⚡ <strong>Priority:</strong> ${pri}</div>`;

  document.getElementById('confirm-modal').classList.add('show');
}
function hideModal() { document.getElementById('confirm-modal').classList.remove('show'); }
function submitForm() { document.getElementById('barcode-form').submit(); }

// ─── File upload indicators ─────────────────────────────────
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
  setTimeout(updateLabel, 300);
  setTimeout(updateLabel, 800);
});
</script>
</body>
</html>

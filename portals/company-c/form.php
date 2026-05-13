<?php
// ============================================================
// Company C Portal — Multi-Step Barcode Form
// Features:
//   Step 1: Category (React-style custom dropdown) + Subcategory (AJAX dependent) + Part Code
//   Step 2: Quantity + Date + Priority (radio) + Notes (textarea)
//   Step 3: Vendors (multi-select div) + File upload + Terms checkbox
// Selector types: data-testid, class, name, nested
// ============================================================
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_C_SID');
session_start();

$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_c_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Company C — Multi-Step Form</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0f172a;min-height:100vh}
  nav{background:#1e293b;border-bottom:1px solid #334155;padding:14px 28px;display:flex;justify-content:space-between;align-items:center}
  nav h1{color:#f1f5f9;font-size:16px;font-weight:700}
  nav .nav-right{display:flex;align-items:center;gap:16px}
  nav .user{color:#94a3b8;font-size:13px}
  nav a{color:#38bdf8;text-decoration:none;font-size:13px}
  .wrap{max-width:740px;margin:36px auto;padding:0 16px}

  /* Progress */
  .progress-bar{display:flex;gap:0;margin-bottom:32px;background:#1e293b;border-radius:12px;padding:20px 24px;border:1px solid #334155}
  .step-item{flex:1;display:flex;flex-direction:column;align-items:center;position:relative}
  .step-item:not(:last-child)::after{content:'';position:absolute;top:18px;left:60%;width:80%;height:2px;background:#334155}
  .step-item:not(:last-child).done::after{background:#06b6d4}
  .step-circle{width:36px;height:36px;border-radius:50%;border:2px solid #334155;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#64748b;background:#0f172a;z-index:1;margin-bottom:8px}
  .step-circle.active{border-color:#06b6d4;color:#06b6d4;background:#0f172a;box-shadow:0 0 0 4px rgba(6,182,212,.15)}
  .step-circle.done{border-color:#10b981;color:#fff;background:#10b981}
  .step-label{font-size:11px;color:#64748b;text-align:center;font-weight:600}
  .step-label.active{color:#06b6d4}

  /* Card */
  .card{background:#1e293b;border:1px solid #334155;padding:36px;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,.3)}
  .step-pane{display:none}
  .step-pane.active{display:block}
  h2{color:#f1f5f9;font-size:20px;margin-bottom:6px}
  .step-desc{color:#64748b;font-size:13px;margin-bottom:28px}
  .section-label{font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#475569;font-weight:700;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #334155;margin-top:4px}
  label.field-label{display:block;margin-bottom:6px;font-size:12px;font-weight:700;color:#94a3b8;letter-spacing:.2px;text-transform:uppercase}
  input[type=text],input[type=number],input[type=date],textarea{
    width:100%;padding:11px 14px;background:#0f172a;border:1.5px solid #334155;
    border-radius:9px;font-size:14px;margin-bottom:18px;color:#f1f5f9;
  }
  input:focus,textarea:focus{outline:none;border-color:#06b6d4;box-shadow:0 0 0 3px rgba(6,182,212,.15)}
  input::placeholder,textarea::placeholder{color:#475569}
  textarea{resize:vertical;min-height:90px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}

  /* ─── React-style Custom Dropdown ─── */
  .custom-select{position:relative;margin-bottom:18px;user-select:none}
  .custom-select__control{
    padding:11px 14px;background:#0f172a;border:1.5px solid #334155;
    border-radius:9px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;
    font-size:14px;color:#64748b;
  }
  .custom-select__control.has-value{color:#f1f5f9;border-color:#06b6d4}
  .custom-select__control:hover{border-color:#06b6d4}
  .custom-select__control.open{border-color:#06b6d4;border-bottom-left-radius:0;border-bottom-right-radius:0;box-shadow:0 0 0 3px rgba(6,182,212,.15)}
  .custom-select__arrow{font-size:12px;transition:transform .2s;color:#475569}
  .custom-select__control.open .custom-select__arrow{transform:rotate(180deg)}
  .custom-select__menu{
    position:absolute;top:100%;left:0;right:0;background:#1e293b;
    border:1.5px solid #06b6d4;border-top:none;border-bottom-left-radius:9px;border-bottom-right-radius:9px;
    z-index:200;max-height:200px;overflow-y:auto;display:none;
  }
  .custom-select__menu.open{display:block}
  .custom-select__search{padding:10px 12px;border-bottom:1px solid #334155}
  .custom-select__search input{
    margin-bottom:0;padding:8px 10px;font-size:13px;border-radius:6px;border-color:#334155;
  }
  .custom-select__option{
    padding:11px 16px;cursor:pointer;font-size:14px;color:#cbd5e1;
    display:flex;align-items:center;gap:10px;border-bottom:1px solid #1e293b;
  }
  .custom-select__option:hover,.custom-select__option.focused{background:#0f172a;color:#38bdf8}
  .custom-select__option.selected{background:#0f172a;color:#06b6d4;font-weight:700}
  .custom-select__placeholder{color:#475569}
  .cs-icon{font-size:18px}

  /* ─── Subcategory (AJAX dependent) ─── */
  .sub-loading{display:flex;align-items:center;gap:8px;padding:11px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:9px;color:#64748b;font-size:13px;margin-bottom:18px}
  .mini-spin{width:14px;height:14px;border:2px solid #334155;border-top-color:#06b6d4;border-radius:50%;animation:sp .7s linear infinite}
  @keyframes sp{to{transform:rotate(360deg)}}
  select.sub-select{display:none;width:100%;padding:11px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:9px;font-size:14px;margin-bottom:18px;color:#f1f5f9}
  select.sub-select.loaded{display:block;border-color:#10b981}

  /* ─── Radio pills ─── */
  .radio-pills{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px}
  .radio-pill{flex:1;min-width:80px}
  .radio-pill input{display:none}
  .radio-pill label{
    display:block;text-align:center;padding:10px;border:1.5px solid #334155;border-radius:9px;
    cursor:pointer;font-size:13px;font-weight:600;color:#64748b;
  }
  .radio-pill input:checked + label{border-color:#06b6d4;color:#06b6d4;background:rgba(6,182,212,.1)}

  /* ─── Multi-select vendors ─── */
  .multi-select-wrap{margin-bottom:18px}
  .ms-options{display:flex;flex-direction:column;gap:6px;max-height:200px;overflow-y:auto;border:1.5px solid #334155;border-radius:9px;padding:8px}
  .ms-item{display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:7px;cursor:pointer;font-size:14px;color:#cbd5e1}
  .ms-item:hover{background:#0f172a}
  .ms-item input[type=checkbox]{accent-color:#06b6d4;width:16px;height:16px;cursor:pointer}
  .ms-item.selected{background:rgba(6,182,212,.08);color:#38bdf8}
  .ms-item .ms-country{font-size:11px;color:#475569;margin-left:auto}
  .ms-selected-tags{display:flex;flex-wrap:wrap;gap:6px;min-height:32px;margin-top:8px}
  .ms-tag{background:rgba(6,182,212,.15);color:#38bdf8;font-size:12px;padding:4px 10px;border-radius:20px;display:flex;align-items:center;gap:4px}
  .ms-tag .remove{cursor:pointer;opacity:.7}

  /* ─── File upload ─── */
  .file-drop{border:2px dashed #334155;border-radius:9px;padding:28px;text-align:center;cursor:pointer;margin-bottom:18px;color:#64748b;font-size:13px;position:relative}
  .file-drop:hover{border-color:#06b6d4;background:rgba(6,182,212,.03)}
  .file-drop input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
  .file-drop .file-icon{font-size:32px;display:block;margin-bottom:8px}
  #file-name{color:#06b6d4;font-weight:600;font-size:12px;margin-top:8px}

  /* ─── Checkbox ─── */
  .terms-wrap{display:flex;align-items:flex-start;gap:12px;padding:14px;background:#0f172a;border-radius:9px;margin-bottom:18px;cursor:pointer}
  .terms-wrap input[type=checkbox]{width:18px;height:18px;margin-top:2px;accent-color:#06b6d4;flex-shrink:0;cursor:pointer}
  .terms-wrap span{font-size:13px;color:#94a3b8;line-height:1.6}
  .terms-wrap a{color:#38bdf8}

  /* ─── Buttons ─── */
  .btn-row{display:flex;gap:12px;margin-top:8px}
  .btn-primary{flex:1;padding:13px;background:linear-gradient(135deg,#06b6d4,#3b82f6);color:#fff;border:none;border-radius:9px;font-size:15px;cursor:pointer;font-weight:700}
  .btn-primary:hover{opacity:.9}
  .btn-primary:disabled{opacity:.4;cursor:not-allowed}
  .btn-secondary{padding:13px 20px;background:#1e293b;border:1.5px solid #334155;color:#94a3b8;border-radius:9px;font-size:14px;cursor:pointer}
  .btn-secondary:hover{border-color:#64748b;color:#f1f5f9}

  /* ─── Validation error ─── */
  .field-error{color:#f87171;font-size:12px;margin-top:-12px;margin-bottom:12px}
</style>
</head>
<body>
<nav>
  <h1>⚡ Company C — Advanced Workflow Portal</h1>
  <div class="nav-right">
    <span class="user">👤 <?= htmlspecialchars($_SESSION['username'] ?? 'admin') ?></span>
    <a href="logout.php">Sign Out</a>
  </div>
</nav>
<div class="wrap">

  <!-- Progress indicator -->
  <div class="progress-bar">
    <div class="step-item" id="prog-1">
      <div class="step-circle active">1</div>
      <div class="step-label active">Part Info</div>
    </div>
    <div class="step-item" id="prog-2">
      <div class="step-circle">2</div>
      <div class="step-label">Order Details</div>
    </div>
    <div class="step-item" id="prog-3">
      <div class="step-circle">3</div>
      <div class="step-label">Vendors &amp; Submit</div>
    </div>
  </div>

  <div class="card">
    <form id="barcode-form" method="POST" action="generate.php" enctype="multipart/form-data">

      <!-- All collected values stored in hidden inputs -->
      <input type="hidden" name="category"     id="hid-category">
      <input type="hidden" name="part_code"    id="hid-part-code">
      <input type="hidden" name="part_name"    id="hid-part-name">
      <input type="hidden" name="vendors"      id="hid-vendors">

      <!-- ═══════════════════════════════════════════
           STEP 1: Part Information
           ═══════════════════════════════════════════ -->
      <div class="step-pane active" id="step-1" data-testid="step-1">
        <h2>Step 1: Part Information</h2>
        <p class="step-desc">Select category, then choose part from dependent dropdown.</p>

        <p class="section-label">Category (React-style Custom Dropdown)</p>
        <label class="field-label">Product Category</label>
        <div class="custom-select" data-testid="category-select" id="cat-select">
          <div class="custom-select__control" id="cat-control" onclick="toggleCatDropdown()">
            <span class="custom-select__placeholder" id="cat-display">Select a category...</span>
            <span class="custom-select__arrow">▼</span>
          </div>
          <div class="custom-select__menu" id="cat-menu">
            <div class="custom-select__search">
              <input type="text" id="cat-search" placeholder="Search categories..." oninput="filterCats(this.value)">
            </div>
            <div id="cat-options"></div>
          </div>
        </div>
        <div class="field-error" id="cat-err" style="display:none">Please select a category</div>

        <p class="section-label">Part (Dependent AJAX Dropdown)</p>
        <label class="field-label">Part Number</label>
        <div id="sub-loading-wrap" style="display:none">
          <div class="sub-loading">
            <div class="mini-spin"></div>
            <span id="sub-loading-text">Loading parts for selected category...</span>
          </div>
        </div>
        <select class="sub-select" id="sub-select" data-testid="subcategory-select" name="part_no">
          <option value="">-- Select Part --</option>
        </select>
        <div class="field-error" id="part-err" style="display:none">Please select a part</div>

        <label class="field-label">Batch Number</label>
        <input type="text" name="batch_no" id="batch-no" data-testid="batch-input"
               placeholder="e.g. BATCH-C-2024-001">
        <div class="field-error" id="batch-err" style="display:none">Batch number is required</div>

        <div class="btn-row">
          <button type="button" class="btn-primary" onclick="goStep2()" data-testid="next-step-1">
            Next: Order Details →
          </button>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════
           STEP 2: Order Details
           ═══════════════════════════════════════════ -->
      <div class="step-pane" id="step-2" data-testid="step-2">
        <h2>Step 2: Order Details</h2>
        <p class="step-desc">Specify quantity, delivery requirements and priority level.</p>

        <div class="grid2">
          <div>
            <label class="field-label">Quantity</label>
            <input type="number" name="quantity" id="quantity" data-testid="quantity-input"
                   min="1" max="99999" placeholder="e.g. 500">
          </div>
          <div>
            <label class="field-label">Delivery Date</label>
            <input type="date" name="delivery_date" id="delivery-date" data-testid="delivery-date">
          </div>
        </div>

        <label class="field-label">Priority Level</label>
        <div class="radio-pills">
          <div class="radio-pill">
            <input type="radio" name="priority" id="pri-low"      value="low"      data-testid="priority-low">
            <label for="pri-low">🟢 Low</label>
          </div>
          <div class="radio-pill">
            <input type="radio" name="priority" id="pri-normal"   value="normal"   data-testid="priority-normal" checked>
            <label for="pri-normal">🔵 Normal</label>
          </div>
          <div class="radio-pill">
            <input type="radio" name="priority" id="pri-high"     value="high"     data-testid="priority-high">
            <label for="pri-high">🟠 High</label>
          </div>
          <div class="radio-pill">
            <input type="radio" name="priority" id="pri-critical" value="critical" data-testid="priority-critical">
            <label for="pri-critical">🔴 Critical</label>
          </div>
        </div>

        <label class="field-label">Notes / Special Instructions</label>
        <textarea name="notes" id="notes" data-testid="notes-textarea"
                  placeholder="Enter any special handling instructions or notes..."></textarea>

        <div class="btn-row">
          <button type="button" class="btn-secondary" onclick="goStep(1)">← Back</button>
          <button type="button" class="btn-primary" onclick="goStep3()" data-testid="next-step-2">
            Next: Vendors →
          </button>
        </div>
      </div>

      <!-- ═══════════════════════════════════════════
           STEP 3: Vendors & Submit
           ═══════════════════════════════════════════ -->
      <div class="step-pane" id="step-3" data-testid="step-3">
        <h2>Step 3: Vendors &amp; Confirmation</h2>
        <p class="step-desc">Select one or more vendors, upload document, and confirm.</p>

        <p class="section-label">Vendor Selection (Multi-select)</p>
        <label class="field-label">Approved Vendors (select multiple)</label>
        <div class="multi-select-wrap" data-testid="vendor-multiselect">
          <div class="ms-options" id="ms-options"></div>
          <div class="ms-selected-tags" id="ms-tags"></div>
        </div>
        <div class="field-error" id="vendor-err" style="display:none">Select at least one vendor</div>

        <p class="section-label">Supporting Documents (Optional)</p>
        <div class="file-drop" data-testid="file-upload-area">
          <input type="file" name="document" id="file-input" data-testid="file-input"
                 accept=".pdf,.xlsx,.xls,.doc,.docx">
          <span class="file-icon">📎</span>
          <div>Drag &amp; drop or click to browse (File 1)</div>
          <div id="file-name" style="display:none"></div>
        </div>
        <label class="field-label" style="margin-top:8px">Upload File 1</label>
        <input type="file" name="upload_document_1" id="upload-file-1"
               data-testid="upload-file-1"
               accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
               style="width:100%;padding:10px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:9px;font-size:14px;margin-bottom:6px;color:#f1f5f9">
        <div id="upload-label-1" data-testid="upload-label-1"
             style="display:none;margin-bottom:12px;padding:8px 12px;background:rgba(16,185,129,.15);color:#34d399;border-radius:7px;font-size:12px;font-weight:700"></div>

        <label class="field-label">Upload File 2</label>
        <input type="file" name="upload_document_2" id="upload-file-2"
               data-testid="upload-file-2"
               accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
               style="width:100%;padding:10px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:9px;font-size:14px;margin-bottom:6px;color:#f1f5f9">
        <div id="upload-label-2" data-testid="upload-label-2"
             style="display:none;margin-bottom:12px;padding:8px 12px;background:rgba(16,185,129,.15);color:#34d399;border-radius:7px;font-size:12px;font-weight:700"></div>

        <label class="field-label">Upload File 3</label>
        <input type="file" name="upload_document_3" id="upload-file-3"
               data-testid="upload-file-3"
               accept=".pdf,.xlsx,.xls,.doc,.docx,.txt"
               style="width:100%;padding:10px 14px;background:#0f172a;border:1.5px solid #334155;border-radius:9px;font-size:14px;margin-bottom:6px;color:#f1f5f9">
        <div id="upload-label-3" data-testid="upload-label-3"
             style="display:none;margin-bottom:12px;padding:8px 12px;background:rgba(16,185,129,.15);color:#34d399;border-radius:7px;font-size:12px;font-weight:700"></div>

        <p class="section-label">Terms &amp; Conditions</p>
        <div class="terms-wrap">
          <input type="checkbox" id="terms-check" name="terms" data-testid="terms-checkbox">
          <span>I confirm that all information provided is accurate and I agree to the
          <a href="#">Terms of Service</a> and <a href="#">Processing Policy</a>.</span>
        </div>
        <div class="field-error" id="terms-err" style="display:none">You must accept the terms</div>

        <div class="btn-row">
          <button type="button" class="btn-secondary" onclick="goStep(2)">← Back</button>
          <button type="submit" class="btn-primary" id="final-submit"
                  data-testid="final-submit" disabled onclick="return validateStep3()">
            🚀 Submit &amp; Generate Barcode
          </button>
        </div>
      </div>

    </form>
  </div>
</div>

<script>
// ─── Data ──────────────────────────────────────────────────
const categories = [];
let selectedCat  = null;
let selectedVendors = new Set();

// ─── Load categories ────────────────────────────────────────
fetch('api/categories.php')
  .then(r=>r.json())
  .then(data=>{
    categories.push(...data);
    renderCatOptions(categories);
  });

function renderCatOptions(items) {
  document.getElementById('cat-options').innerHTML = items.map(c=>
    `<div class="custom-select__option" data-testid="category-option"
          data-id="${c.id}" onclick="selectCat('${c.id}','${c.name}','${c.icon}')">
      <span class="cs-icon">${c.icon}</span>${c.name}
    </div>`
  ).join('') || '<div style="padding:12px;color:#475569;font-size:13px">No results</div>';
}

function filterCats(q) {
  const f = categories.filter(c=>c.name.toLowerCase().includes(q.toLowerCase()));
  renderCatOptions(f);
}

function toggleCatDropdown() {
  const ctrl = document.getElementById('cat-control');
  const menu = document.getElementById('cat-menu');
  ctrl.classList.toggle('open');
  menu.classList.toggle('open');
  if (menu.classList.contains('open')) {
    setTimeout(()=>document.getElementById('cat-search').focus(), 50);
  }
}

function selectCat(id, name, icon) {
  selectedCat = id;
  document.getElementById('hid-category').value = id;
  const ctrl = document.getElementById('cat-control');
  ctrl.innerHTML = `<span style="display:flex;align-items:center;gap:8px"><span>${icon}</span>${name}</span>
                    <span class="custom-select__arrow">▼</span>`;
  ctrl.classList.add('has-value');
  ctrl.classList.remove('open');
  document.getElementById('cat-menu').classList.remove('open');
  document.getElementById('cat-err').style.display='none';
  loadSubcategories(id);
}

document.addEventListener('click', function(e){
  if (!e.target.closest('.custom-select')) {
    document.getElementById('cat-control').classList.remove('open');
    document.getElementById('cat-menu').classList.remove('open');
  }
});

// ─── Load subcategories (AJAX dependent) ───────────────────
function loadSubcategories(catId) {
  const loadWrap = document.getElementById('sub-loading-wrap');
  const subSel   = document.getElementById('sub-select');
  loadWrap.style.display = 'block';
  subSel.classList.remove('loaded');
  subSel.style.display = 'none';
  subSel.innerHTML = '<option value="">-- Select Part --</option>';

  fetch(`api/subcategories.php?category=${catId}`)
    .then(r=>r.json())
    .then(data=>{
      loadWrap.style.display='none';
      data.items.forEach(item=>{
        const o = document.createElement('option');
        o.value = item.code;
        o.textContent = item.name + ' (' + item.code + ')';
        o.dataset.name = item.name;
        subSel.appendChild(o);
      });
      subSel.classList.add('loaded');
      subSel.style.display = 'block';
      subSel.addEventListener('change', function(){
        const opt = this.options[this.selectedIndex];
        document.getElementById('hid-part-code').value = this.value;
        document.getElementById('hid-part-name').value = opt?.dataset.name || '';
      });
    });
}

// ─── Step navigation ────────────────────────────────────────
function goStep(n) {
  document.querySelectorAll('.step-pane').forEach(p=>p.classList.remove('active'));
  document.getElementById('step-'+n).classList.add('active');
  updateProgress(n);
  window.scrollTo({top:0,behavior:'smooth'});
}

function updateProgress(current) {
  for(let i=1;i<=3;i++){
    const circle = document.querySelector(`#prog-${i} .step-circle`);
    const label  = document.querySelector(`#prog-${i} .step-label`);
    const item   = document.getElementById('prog-'+i);
    circle.classList.remove('active','done');
    label.classList.remove('active');
    item.classList.remove('done');
    if(i < current){ circle.classList.add('done'); circle.textContent='✓'; item.classList.add('done'); }
    else if(i===current){ circle.classList.add('active'); circle.textContent=i; label.classList.add('active'); }
    else { circle.textContent=i; }
  }
}

function goStep2() {
  let ok = true;
  if (!selectedCat) { document.getElementById('cat-err').style.display='block'; ok=false; }
  if (!document.getElementById('sub-select').value) { document.getElementById('part-err').style.display='block'; ok=false; }
  if (!document.getElementById('batch-no').value.trim()) { document.getElementById('batch-err').style.display='block'; ok=false; }
  if (ok) goStep(2);
}
function goStep3() {
  if (!document.getElementById('quantity').value) { alert('Please enter quantity.'); return; }
  goStep(3);
  loadVendors();
}

// ─── Multi-select vendors ───────────────────────────────────
let vendorsLoaded = false;
function loadVendors() {
  if (vendorsLoaded) return;
  fetch('api/vendors.php')
    .then(r=>r.json())
    .then(vendors=>{
      const wrap = document.getElementById('ms-options');
      wrap.innerHTML = vendors.map(v=>
        `<div class="ms-item" data-testid="vendor-option" data-code="${v.code}"
              onclick="toggleVendor(this,'${v.code}','${v.name}')">
          <input type="checkbox" data-testid="vendor-cb-${v.code}">
          <span>${v.name}</span>
          <span class="ms-country">🌏 ${v.country}</span>
        </div>`
      ).join('');
      vendorsLoaded = true;
    });
}

function toggleVendor(el, code, name) {
  const cb = el.querySelector('input[type=checkbox]');
  if (selectedVendors.has(code)) {
    selectedVendors.delete(code);
    el.classList.remove('selected');
    cb.checked = false;
  } else {
    selectedVendors.add(code);
    el.classList.add('selected');
    cb.checked = true;
  }
  document.getElementById('hid-vendors').value = [...selectedVendors].join(',');
  renderTags();
  checkTerms();
}

function renderTags() {
  const items = document.querySelectorAll('#ms-options .ms-item');
  const tagWrap = document.getElementById('ms-tags');
  tagWrap.innerHTML = '';
  items.forEach(item=>{
    if(item.classList.contains('selected')){
      const code = item.dataset.code;
      const name = item.querySelector('span').textContent;
      const tag  = document.createElement('div');
      tag.className='ms-tag';
      tag.innerHTML=`${name} <span class="remove" onclick="removeVendor('${code}')">✕</span>`;
      tagWrap.appendChild(tag);
    }
  });
}
function removeVendor(code) {
  selectedVendors.delete(code);
  document.getElementById('hid-vendors').value=[...selectedVendors].join(',');
  const item = document.querySelector(`[data-code="${code}"]`);
  if(item){ item.classList.remove('selected'); item.querySelector('input').checked=false; }
  renderTags(); checkTerms();
}

// ─── Terms checkbox ─────────────────────────────────────────
document.addEventListener('change', function(e){
  if(e.target.id==='terms-check') checkTerms();
});
function checkTerms() {
  const terms = document.getElementById('terms-check').checked;
  const hasVendor = selectedVendors.size > 0;
  document.getElementById('final-submit').disabled = !(terms && hasVendor);
}

// ─── File upload display ────────────────────────────────────
document.getElementById('file-input').addEventListener('change',function(){
  const fn = document.getElementById('file-name');
  if(this.files.length){
    fn.textContent='📎 '+this.files[0].name; fn.style.display='block';
  }
});

function validateStep3() {
  if (!document.getElementById('terms-check').checked) {
    document.getElementById('terms-err').style.display='block'; return false;
  }
  if (selectedVendors.size === 0) {
    document.getElementById('vendor-err').style.display='block'; return false;
  }
  return true;
}

// ─── Upload file indicators (upload-file-1/2/3) ────────────
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

/**
 * generate_advanced_samples.js
 * Generates 10 sample Excel files for the multi-portal automation sandbox.
 *
 * Run: node generate_advanced_samples.js
 * Output: 10 .xlsx files in the same folder
 */

const XLSX = require('xlsx');
const path = require('path');
const fs   = require('fs');

const OUT = __dirname;

// ── Part and vendor data ────────────────────────────────────
const PARTS_A = [
  'ENG-001','TRN-002','BRK-003','STR-004','FUL-005',
  'ALT-006','RAD-007','OIL-008','SPK-009','AIR-010',
];
const VENDORS_A = ['VND-A-001','VND-A-002','VND-A-003','VND-A-004','VND-A-005'];
const VENDORS_B = ['VND-B-001','VND-B-002','VND-B-003','VND-B-004','VND-B-005','VND-B-006'];
const PARTS_B   = [
  'ENG-001','TRN-002','BRK-003','STR-004','FUL-005',
  'ALT-006','RAD-007','OIL-008','SPK-009','AIR-010',
  'EXH-011','CAM-012','CRK-013','PST-014','TIM-015',
];
const CATS_C    = ['AUTO','ELEC','HYDR','MECH','SAFE'];
const PARTS_C   = {
  AUTO: ['ENG-001','TRN-002','BRK-003','STR-004','FUL-005'],
  ELEC: ['ALT-006','SPK-009','ECU-020','SEN-021','IGN-022'],
  HYDR: ['PMP-030','CYL-031','VLV-032','FLT-033'],
  MECH: ['BRG-040','GER-041','CHN-042','BLT-043','CPL-044'],
  SAFE: ['SFV-050','EMG-051','GRD-052','SHL-053'],
};
const VENDORS_C = ['VND-C-001','VND-C-002','VND-C-003','VND-C-004','VND-C-005'];
const PRIORITIES = ['normal','urgent','critical','low','high'];

function rand(arr)   { return arr[Math.floor(Math.random() * arr.length)]; }
function randQty()   { return Math.floor(Math.random() * 490 + 10); }
function randBatch(prefix, i) {
  const d = new Date(); d.setDate(d.getDate() + i);
  return `${prefix}-${d.toISOString().slice(0,10).replace(/-/g,'')}-${String(i+1).padStart(3,'0')}`;
}
function randDate(daysAhead) {
  const d = new Date(); d.setDate(d.getDate() + daysAhead);
  return d.toISOString().slice(0,10);
}

function writeBook(filename, sheets) {
  const wb = XLSX.utils.book_new();
  for (const [name, data] of Object.entries(sheets)) {
    const ws = XLSX.utils.json_to_sheet(data);
    // Auto-width columns
    const cols = Object.keys(data[0] || {});
    ws['!cols'] = cols.map(k => ({
      wch: Math.max(k.length + 2, ...data.map(r => String(r[k] ?? '').length + 2))
    }));
    XLSX.utils.book_append_sheet(wb, ws, name);
  }
  const outPath = path.join(OUT, filename);
  XLSX.writeFile(wb, outPath);
  console.log(`✅ Created: ${filename} (${Object.keys(sheets).join(', ')})`);
}

// ════════════════════════════════════════════════════════════
// FILE 1: company_a_parts.xlsx
// Simple Company A data — 20 rows, all valid
// ════════════════════════════════════════════════════════════
function gen1() {
  const rows = Array.from({length:20}, (_,i) => ({
    company:       'Company A',
    part_no:       rand(PARTS_A),
    quantity:      randQty(),
    batch_no:      randBatch('CO-A', i),
    vendor_code:   rand(VENDORS_A),
    priority:      rand(['normal','normal','urgent','critical']),
    delivery_date: randDate(i + 7),
    notes:         i % 5 === 0 ? 'Rush order' : '',
  }));
  writeBook('company_a_parts.xlsx', { 'Company A Orders': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 2: company_b_parts.xlsx
// Company B data — 20 rows, includes extra fields
// ════════════════════════════════════════════════════════════
function gen2() {
  const rows = Array.from({length:20}, (_,i) => ({
    company:        'Company B',
    email:          'b.operator@company.com',
    part_no:        rand(PARTS_B),
    quantity:       randQty(),
    batch_no:       randBatch('CO-B', i),
    vendor_code:    rand(VENDORS_B),
    priority:       rand(['normal','urgent','express']),
    contact_name:   `Operator ${i+1}`,
    contact_phone:  `012-${String(Math.floor(Math.random()*9000000+1000000))}`,
    delivery_date:  randDate(i + 5),
  }));
  writeBook('company_b_parts.xlsx', { 'Company B Orders': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 3: company_c_parts.xlsx
// Company C — Multi-step fields, vendor codes (comma-separated)
// ════════════════════════════════════════════════════════════
function gen3() {
  const rows = Array.from({length:20}, (_,i) => {
    const cat   = rand(CATS_C);
    const parts = PARTS_C[cat];
    const vCnt  = Math.floor(Math.random() * 2) + 1;
    const vendors = Array.from({length:vCnt}, () => rand(VENDORS_C))
                         .filter((v,i,a) => a.indexOf(v) === i).join(',');
    return {
      company:       'Company C',
      category:      cat,
      part_no:       rand(parts),
      quantity:      randQty(),
      batch_no:      randBatch('CO-C', i),
      vendors:       vendors,
      priority:      rand(['low','normal','high','critical']),
      delivery_date: randDate(i + 10),
      notes:         i % 4 === 0 ? 'Handle with care' : '',
    };
  });
  writeBook('company_c_parts.xlsx', { 'Company C Orders': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 4: all_companies_mixed.xlsx
// Mixed data for all 3 companies — 30 rows
// ════════════════════════════════════════════════════════════
function gen4() {
  const coA = Array.from({length:10}, (_,i) => ({
    company:'Company A', part_no:rand(PARTS_A), quantity:randQty(),
    batch_no:randBatch('MIX-A',i), vendor_code:rand(VENDORS_A),
    priority:rand(PRIORITIES), delivery_date:randDate(i+7), notes:'',
  }));
  const coB = Array.from({length:10}, (_,i) => ({
    company:'Company B', part_no:rand(PARTS_B), quantity:randQty(),
    batch_no:randBatch('MIX-B',i), vendor_code:rand(VENDORS_B),
    priority:rand(['normal','urgent','express']), delivery_date:randDate(i+5), notes:'',
  }));
  const coC = Array.from({length:10}, (_,i) => {
    const cat = rand(CATS_C);
    return {
      company:'Company C', category:cat, part_no:rand(PARTS_C[cat]), quantity:randQty(),
      batch_no:randBatch('MIX-C',i), vendor_code:rand(VENDORS_C),
      priority:rand(['normal','high']), delivery_date:randDate(i+10), notes:'',
    };
  });
  writeBook('all_companies_mixed.xlsx', {
    'All Orders': [...coA, ...coB, ...coC],
    'Company A': coA,
    'Company B': coB,
    'Company C': coC,
  });
}

// ════════════════════════════════════════════════════════════
// FILE 5: invalid_data_test.xlsx
// Rows with intentional errors for validation testing
// ════════════════════════════════════════════════════════════
function gen5() {
  const rows = [
    // Missing fields
    { company:'Company A', part_no:'',       quantity:100,  batch_no:'BATCH-001',  vendor_code:'VND-A-001', notes:'MISSING part_no' },
    { company:'Company A', part_no:'ENG-001',quantity:'',   batch_no:'BATCH-002',  vendor_code:'VND-A-001', notes:'MISSING quantity' },
    { company:'Company A', part_no:'ENG-001',quantity:50,   batch_no:'',           vendor_code:'VND-A-001', notes:'MISSING batch_no' },
    { company:'Company A', part_no:'ENG-001',quantity:50,   batch_no:'BATCH-004',  vendor_code:'',          notes:'MISSING vendor_code' },
    // Invalid values
    { company:'Company A', part_no:'FAKE-999',quantity:100, batch_no:'BATCH-005',  vendor_code:'VND-A-001', notes:'INVALID part code' },
    { company:'Company A', part_no:'ENG-001', quantity:-5,  batch_no:'BATCH-006',  vendor_code:'VND-A-001', notes:'NEGATIVE quantity' },
    { company:'Company A', part_no:'ENG-001', quantity:0,   batch_no:'BATCH-007',  vendor_code:'VND-A-001', notes:'ZERO quantity' },
    { company:'Company A', part_no:'ENG-001', quantity:100, batch_no:'BATCH-008',  vendor_code:'FAKE-VENDOR', notes:'INVALID vendor' },
    // Special chars
    { company:'Company A', part_no:'ENG-001', quantity:100, batch_no:"BATCH's-009", vendor_code:'VND-A-001', notes:"Special char apostrophe" },
    { company:'Company A', part_no:'ENG-001', quantity:100, batch_no:'BATCH-010',  vendor_code:'VND-A-001', notes:'<script>alert(1)</script>' },
    // Extreme values
    { company:'Company A', part_no:'ENG-001', quantity:99999, batch_no:'BATCH-011', vendor_code:'VND-A-001', notes:'MAX quantity' },
    { company:'Company A', part_no:'ENG-001', quantity:1,    batch_no:'BATCH-012',  vendor_code:'VND-A-001', notes:'MIN quantity' },
    // Correct row at end (should succeed)
    { company:'Company A', part_no:'BRK-003', quantity:100, batch_no:'BATCH-VALID', vendor_code:'VND-A-002', notes:'Valid row — should succeed' },
  ];
  writeBook('invalid_data_test.xlsx', { 'Invalid Data': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 6: duplicate_test.xlsx
// Duplicate rows — tests idempotency handling
// ════════════════════════════════════════════════════════════
function gen6() {
  const base = { company:'Company A', part_no:'ENG-001', quantity:100, batch_no:'BATCH-DUP-001', vendor_code:'VND-A-001' };
  const rows = [
    {...base, notes:'Original row'},
    {...base, notes:'Duplicate 1 — same batch_no'},
    {...base, notes:'Duplicate 2 — same batch_no'},
    {...base, batch_no:'BATCH-DUP-002', notes:'Different batch — OK'},
    {...base, part_no:'TRN-002', notes:'Different part — OK'},
    {...base, vendor_code:'VND-A-002', notes:'Different vendor — OK'},
    {...base, notes:'Duplicate 3 — same batch_no'},
    {...base, quantity:200, notes:'Same batch, different qty'},
  ];
  writeBook('duplicate_test.xlsx', { 'Duplicate Test': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 7: bulk_100_records.xlsx
// Stress test — 100 rows across all companies
// ════════════════════════════════════════════════════════════
function gen7() {
  const rows = Array.from({length:100}, (_,i) => {
    const co = i % 3;
    if (co === 0) {
      return { company:'Company A', part_no:rand(PARTS_A), quantity:randQty(),
               batch_no:randBatch('BULK-A',i), vendor_code:rand(VENDORS_A), priority:rand(PRIORITIES) };
    } else if (co === 1) {
      return { company:'Company B', part_no:rand(PARTS_B), quantity:randQty(),
               batch_no:randBatch('BULK-B',i), vendor_code:rand(VENDORS_B), priority:rand(['normal','urgent']) };
    } else {
      const cat = rand(CATS_C);
      return { company:'Company C', category:cat, part_no:rand(PARTS_C[cat]), quantity:randQty(),
               batch_no:randBatch('BULK-C',i), vendor_code:rand(VENDORS_C), priority:rand(['normal','high']) };
    }
  });
  writeBook('bulk_100_records.xlsx', { 'Bulk Orders': rows });
}

// ════════════════════════════════════════════════════════════
// FILE 8: dropdown_values_test.xlsx
// Tests all valid dropdown values — one row per part/vendor combo
// ════════════════════════════════════════════════════════════
function gen8() {
  const rowsA = PARTS_A.map((p, i) => ({
    company:'Company A', part_no:p, quantity:50, batch_no:`DD-A-${String(i+1).padStart(3,'0')}`,
    vendor_code:VENDORS_A[i % VENDORS_A.length], priority:'normal',
  }));
  const rowsB = PARTS_B.slice(0,10).map((p, i) => ({
    company:'Company B', part_no:p, quantity:75, batch_no:`DD-B-${String(i+1).padStart(3,'0')}`,
    vendor_code:VENDORS_B[i % VENDORS_B.length], priority:'normal',
  }));
  writeBook('dropdown_values_test.xlsx', {
    'Company A Parts': rowsA,
    'Company B Parts': rowsB,
  });
}

// ════════════════════════════════════════════════════════════
// FILE 9: priority_variations.xlsx
// Tests all priority values across all companies
// ════════════════════════════════════════════════════════════
function gen9() {
  const priosA = ['normal','urgent','critical'].map((p,i) => ({
    company:'Company A', part_no:PARTS_A[i], quantity:100, batch_no:`PRI-A-${i+1}`,
    vendor_code:'VND-A-001', priority:p, notes:`Priority test: ${p}`,
  }));
  const priosB = ['normal','urgent','express'].map((p,i) => ({
    company:'Company B', part_no:PARTS_B[i], quantity:150, batch_no:`PRI-B-${i+1}`,
    vendor_code:'VND-B-001', priority:p, notes:`Priority test: ${p}`,
  }));
  const priosC = ['low','normal','high','critical'].map((p,i) => {
    const cat = CATS_C[i];
    return {
      company:'Company C', category:cat, part_no:PARTS_C[cat][0], quantity:200,
      batch_no:`PRI-C-${i+1}`, vendor_code:'VND-C-001', priority:p, notes:`Priority test: ${p}`,
    };
  });
  writeBook('priority_variations.xlsx', {
    'Company A': priosA,
    'Company B': priosB,
    'Company C': priosC,
    'All Priorities': [...priosA, ...priosB, ...priosC],
  });
}

// ════════════════════════════════════════════════════════════
// FILE 10: training_complete_dataset.xlsx
// Complete training dataset with all field types and comments
// ════════════════════════════════════════════════════════════
function gen10() {
  const orders = Array.from({length:30}, (_,i) => ({
    'Order #':        i + 1,
    'Company':        i % 3 === 0 ? 'Company A' : i % 3 === 1 ? 'Company B' : 'Company C',
    'Part Code':      rand([...PARTS_A, ...PARTS_B]),
    'Category (C)':   i % 3 === 2 ? rand(CATS_C) : '',
    'Quantity':       randQty(),
    'Batch Number':   randBatch('TRAIN', i),
    'Vendor Code':    rand([...VENDORS_A, ...VENDORS_B]),
    'Priority':       rand(PRIORITIES),
    'Delivery Date':  randDate(i + 7),
    'Notes':          i % 6 === 0 ? 'Handle with care' : i % 6 === 3 ? 'Rush order' : '',
    'Expected Result': 'success',
  }));

  const instructions = [
    { 'Field': 'Company',       'Values': 'Company A | Company B | Company C', 'Required': 'Yes', 'Notes': 'Determines which portal bot to use' },
    { 'Field': 'Part Code',     'Values': 'ENG-001 to AIR-010 (A), EXH-011 to TIM-015 (B)', 'Required': 'Yes', 'Notes': 'Must exist in portal dropdown' },
    { 'Field': 'Category (C)',  'Values': 'AUTO | ELEC | HYDR | MECH | SAFE', 'Required': 'Company C only', 'Notes': 'Determines subcategory options' },
    { 'Field': 'Quantity',      'Values': '1 - 9999', 'Required': 'Yes', 'Notes': 'Positive integers only' },
    { 'Field': 'Batch Number',  'Values': 'e.g. BATCH-2024-001', 'Required': 'Yes', 'Notes': 'Unique identifier for this batch' },
    { 'Field': 'Vendor Code',   'Values': 'VND-A/B/C-001 to 006', 'Required': 'Yes', 'Notes': 'Must match company vendor list' },
    { 'Field': 'Priority',      'Values': 'normal | urgent | critical | low | high | express', 'Required': 'No', 'Notes': 'Default: normal' },
    { 'Field': 'Delivery Date', 'Values': 'YYYY-MM-DD', 'Required': 'No', 'Notes': 'Future date preferred' },
    { 'Field': 'Notes',         'Values': 'Free text', 'Required': 'No', 'Notes': 'Optional notes field' },
  ];

  writeBook('training_complete_dataset.xlsx', {
    'Orders': orders,
    'Field Instructions': instructions,
    'Readme': [
      { 'Info': 'This is the complete training dataset for the automation sandbox.' },
      { 'Info': 'Portal A: Simple login, id-based selectors, normal dropdowns' },
      { 'Info': 'Portal B: OTP login, searchable dropdown, AJAX vendor, modal confirm' },
      { 'Info': 'Portal C: Delayed login, multi-step form, React dropdown, dependent AJAX, multi-select' },
      { 'Info': 'Credentials A: operator_a / pass_a123' },
      { 'Info': 'Credentials B: b.operator@company.com / Bpass@2024 / OTP: 123456' },
      { 'Info': 'Credentials C: admin / CompanyC#123' },
    ]
  });
}

// ── Run all generators ──────────────────────────────────────
console.log('\n🚀 Generating 10 sample Excel files...\n');
try {
  gen1();  // company_a_parts.xlsx
  gen2();  // company_b_parts.xlsx
  gen3();  // company_c_parts.xlsx
  gen4();  // all_companies_mixed.xlsx
  gen5();  // invalid_data_test.xlsx
  gen6();  // duplicate_test.xlsx
  gen7();  // bulk_100_records.xlsx
  gen8();  // dropdown_values_test.xlsx
  gen9();  // priority_variations.xlsx
  gen10(); // training_complete_dataset.xlsx
  console.log('\n✅ All 10 files generated successfully!\n');
  console.log('Files created in: ' + OUT);
} catch (e) {
  console.error('❌ Error:', e.message);
  console.error('Make sure to run: npm install xlsx');
}

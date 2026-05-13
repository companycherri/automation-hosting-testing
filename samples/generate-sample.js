/**
 * generate-sample.js
 * Run: node generate-sample.js  (from the samples/ folder)
 */

const XLSX = require("../frontend/node_modules/xlsx");
const path = require("path");

const OUT_DIR = __dirname;

// ── Master data (matches DB seed) ───────────────────────────
const COMPANIES = [
  "Toyota Industries",
  "Honda Logistics",
  "Suzuki Parts Co.",
];

const PARTS = [
  { name: "Engine Block",       code: "ENG-001" },
  { name: "Transmission Case",  code: "TRN-002" },
  { name: "Brake Caliper",      code: "BRK-003" },
  { name: "Steering Wheel",     code: "STR-004" },
  { name: "Fuel Injector",      code: "FUL-005" },
  { name: "Alternator",         code: "ALT-006" },
  { name: "Radiator Cap",       code: "RAD-007" },
  { name: "Oil Filter",         code: "OIL-008" },
  { name: "Spark Plug",         code: "SPK-009" },
  { name: "Air Filter",         code: "AIR-010" },
];

const VENDORS  = ["VND-001", "VND-002", "VND-003", "VND-004", "VND-005"];
const BATCHES  = ["BATCH-2024-01", "BATCH-2024-02", "BATCH-2024-03", "BATCH-2024-04", "BATCH-2024-05"];

function rand(arr) { return arr[Math.floor(Math.random() * arr.length)]; }
function formatJobId(n) { return `JOB-${String(n).padStart(4, "0")}`; }

function write(filename, wb) {
  const full = path.join(OUT_DIR, filename);
  XLSX.writeFile(wb, full);
  console.log("✅ Created:", full);
}

function makeSheet(rows) {
  const ws = XLSX.utils.aoa_to_sheet(rows);
  ws["!cols"] = [
    { wch: 6  }, // Sr. No.
    { wch: 22 }, // company_name
    { wch: 10 }, // part_no
    { wch: 22 }, // part_name
    { wch: 10 }, // quantity
    { wch: 18 }, // batch_no
    { wch: 12 }, // vendor_code
  ];
  return ws;
}

const HEADER = ["Sr. No.", "company_name", "part_no", "part_name (reference)", "quantity", "batch_no", "vendor_code"];

// ════════════════════════════════════════════════════════════
// 1. barcode_upload_sample.xlsx — 10 rows (one per part)
// ════════════════════════════════════════════════════════════
function createMainSample() {
  const wb   = XLSX.utils.book_new();
  const data = [HEADER];

  PARTS.forEach((p, i) => {
    data.push([
      formatJobId(i + 1),
      COMPANIES[i % COMPANIES.length],
      p.code,
      p.name,
      (i + 1) * 50,
      `BATCH-2024-0${(i % 5) + 1}`,
      VENDORS[i % VENDORS.length],
    ]);
  });

  XLSX.utils.book_append_sheet(wb, makeSheet(data), "Jobs");
  write("barcode_upload_sample.xlsx", wb);
}

// ════════════════════════════════════════════════════════════
// 2. barcode_upload_template.xlsx — blank + instructions
// ════════════════════════════════════════════════════════════
function createTemplate() {
  const wb = XLSX.utils.book_new();

  // Data sheet — header only
  const dataSheet = makeSheet([HEADER]);
  XLSX.utils.book_append_sheet(wb, dataSheet, "Upload Data");

  // Instructions sheet
  const instr = XLSX.utils.aoa_to_sheet([
    ["BARCODE PORTAL — Upload Template Instructions"],
    [""],
    ["Column",                   "Description",                              "Allowed Values"],
    ["Sr. No.",                  "Optional reference ID (JOB-0001 format)", "Any / leave blank"],
    ["company_name",             "Must match exactly one of the 3 companies","Toyota Industries | Honda Logistics | Suzuki Parts Co."],
    ["part_no",                  "Part code from the parts list",            "ENG-001 | TRN-002 | BRK-003 | STR-004 | FUL-005 | ALT-006 | RAD-007 | OIL-008 | SPK-009 | AIR-010"],
    ["part_name (reference)",    "For your reference only — not uploaded",   "Engine Block | Transmission Case | etc."],
    ["quantity",                 "Number of barcodes — must be > 0",         "Any positive integer"],
    ["batch_no",                 "Batch / lot identifier",                   "e.g. BATCH-2024-01"],
    ["vendor_code",              "Vendor / supplier code",                   "VND-001 | VND-002 | VND-003 | VND-004 | VND-005"],
    [""],
    ["Notes:"],
    ["  - Column names are flexible: 'company_name', 'Company Name', 'company' all work."],
    ["  - The 'part_name (reference)' column is ignored during upload — only part_no is used."],
    ["  - Empty rows are skipped automatically."],
    ["  - Maximum 500 rows per upload."],
    ["  - Save as .xlsx, .xls or .csv before uploading."],
  ]);
  instr["!cols"] = [{ wch: 28 }, { wch: 45 }, { wch: 70 }];
  XLSX.utils.book_append_sheet(wb, instr, "Instructions");

  // Parts reference sheet
  const partsRef = XLSX.utils.aoa_to_sheet([
    ["#", "Part Code", "Part Name"],
    ...PARTS.map((p, i) => [i + 1, p.code, p.name]),
  ]);
  partsRef["!cols"] = [{ wch: 5 }, { wch: 12 }, { wch: 22 }];
  XLSX.utils.book_append_sheet(wb, partsRef, "Parts Reference");

  // Companies reference sheet
  const compRef = XLSX.utils.aoa_to_sheet([
    ["#", "Company Name"],
    ...COMPANIES.map((c, i) => [i + 1, c]),
  ]);
  compRef["!cols"] = [{ wch: 5 }, { wch: 25 }];
  XLSX.utils.book_append_sheet(wb, compRef, "Companies Reference");

  write("barcode_upload_template.xlsx", wb);
}

// ════════════════════════════════════════════════════════════
// 3. barcode_bulk_50rows.xlsx — 50 rows stress test
// ════════════════════════════════════════════════════════════
function createBulkSample() {
  const wb   = XLSX.utils.book_new();
  const data = [HEADER];

  for (let i = 1; i <= 50; i++) {
    const part = PARTS[(i - 1) % PARTS.length];
    data.push([
      formatJobId(i),
      rand(COMPANIES),
      part.code,
      part.name,
      Math.floor(Math.random() * 490) + 10,
      rand(BATCHES),
      rand(VENDORS),
    ]);
  }

  XLSX.utils.book_append_sheet(wb, makeSheet(data), "Jobs");
  write("barcode_bulk_50rows.xlsx", wb);
}

// ════════════════════════════════════════════════════════════
// 4. barcode_with_errors_sample.xlsx — validation demo
// ════════════════════════════════════════════════════════════
function createErrorSample() {
  const wb = XLSX.utils.book_new();

  const data = [
    HEADER,
    // Valid rows
    [formatJobId(1), "Toyota Industries",  "ENG-001", "Engine Block",      100, "BATCH-2024-01", "VND-001"],
    [formatJobId(2), "Honda Logistics",    "BRK-003", "Brake Caliper",     200, "BATCH-2024-01", "VND-002"],
    // Row 4: missing part_no
    [formatJobId(3), "Toyota Industries",  "",        "",                   50,  "BATCH-2024-02", "VND-001"],
    // Row 5: quantity 0
    [formatJobId(4), "Suzuki Parts Co.",   "FUL-005", "Fuel Injector",       0,  "BATCH-2024-02", "VND-003"],
    // Row 6: missing vendor_code
    [formatJobId(5), "Honda Logistics",    "ALT-006", "Alternator",         75,  "BATCH-2024-03", ""],
    // Valid rows
    [formatJobId(6), "Suzuki Parts Co.",   "OIL-008", "Oil Filter",        300,  "BATCH-2024-03", "VND-004"],
    // Row 8: fully empty
    ["", "", "", "", "", "", ""],
    // Row 9: missing batch_no
    [formatJobId(7), "Toyota Industries",  "SPK-009", "Spark Plug",        150, "",              "VND-005"],
    // Valid row
    [formatJobId(8), "Honda Logistics",    "AIR-010", "Air Filter",        400,  "BATCH-2024-04", "VND-001"],
  ];

  const ws = makeSheet(data);
  XLSX.utils.book_append_sheet(wb, ws, "Jobs");

  const notes = XLSX.utils.aoa_to_sheet([
    ["Intentional errors in this file (for validation demo):"],
    ["Row 4: Missing part_no"],
    ["Row 5: Quantity = 0 (invalid, must be > 0)"],
    ["Row 6: Missing vendor_code"],
    ["Row 8: Completely empty row (auto-skipped silently)"],
    ["Row 9: Missing batch_no"],
    [""],
    ["Expected result: 5 valid jobs created, 3 rows rejected with errors"],
  ]);
  notes["!cols"] = [{ wch: 55 }];
  XLSX.utils.book_append_sheet(wb, notes, "Error Notes");

  write("barcode_with_errors_sample.xlsx", wb);
}

// ── Run ──────────────────────────────────────────────────────
console.log("\n=== Generating Sample Excel Files ===\n");
createMainSample();
createTemplate();
createBulkSample();
createErrorSample();
console.log("\n✅ All files created in:", OUT_DIR, "\n");

import { useState, useRef, useCallback } from "react";
import { useNavigate } from "react-router-dom";
import { uploadExcelPreview, uploadSupportFiles, importJobs } from "../api/api";

// ── Helpers ───────────────────────────────────────────────────
function PriorityBadge({ value }) {
  const v = (value || "normal").toLowerCase();
  const map = {
    urgent:   "bg-yellow-100 text-yellow-800",
    express:  "bg-red-100 text-red-700",
    critical: "bg-red-100 text-red-700",
    normal:   "bg-gray-100 text-gray-600",
  };
  return (
    <span className={`text-xs px-2 py-0.5 rounded font-medium capitalize ${map[v] || map.normal}`}>
      {v}
    </span>
  );
}

function StepDot({ n, active, done }) {
  const base = "w-8 h-8 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0 border-2";
  if (done)   return <div className={`${base} bg-green-500 border-green-500 text-white`}>✓</div>;
  if (active) return <div className={`${base} bg-blue-600 border-blue-600 text-white`}>{n}</div>;
  return        <div className={`${base} bg-white border-gray-300 text-gray-400`}>{n}</div>;
}

// Column reference shown on the upload page
const COL_GUIDE = [
  { col: "company_name",  req: true,  hint: "Company A · Company B · Company C" },
  { col: "part_no",       req: true,  hint: "e.g. ENG-001, TRN-002" },
  { col: "quantity",      req: true,  hint: "Number > 0" },
  { col: "batch_no",      req: true,  hint: "e.g. BATCH-A-001" },
  { col: "vendor_code",   req: true,  hint: "e.g. VND-A-001" },
  { col: "delivery_date", req: false, hint: "Optional — YYYY-MM-DD" },
  { col: "priority",      req: false, hint: "Optional — normal / urgent / express" },
  { col: "notes",         req: false, hint: "Optional — free text" },
  { col: "upload_file_1", req: false, hint: "File name only — e.g. invoice_001.pdf" },
  { col: "upload_file_2", req: false, hint: "File name only — e.g. invoice_002.pdf" },
  { col: "upload_file_3", req: false, hint: "File name only — optional third attachment" },
];

// ══════════════════════════════════════════════════════════════
// Main Component
// ══════════════════════════════════════════════════════════════
export default function UploadPage() {
  const navigate = useNavigate();
  const excelRef   = useRef();
  const supportRef = useRef();

  // ── Step state ─────────────────────────────────────────────
  const [step, setStep] = useState(1); // 1 = upload, 2 = review+files, 3 = done

  // ── Excel state ────────────────────────────────────────────
  const [excelDragging, setExcelDragging]   = useState(false);
  const [excelFileName, setExcelFileName]   = useState("");
  const [parsing, setParsing]               = useState(false);
  const [validRows, setValidRows]           = useState([]);
  const [invalidRows, setInvalidRows]       = useState([]);
  const [expectedFiles, setExpectedFiles]   = useState([]); // filenames needed from Excel

  // ── Support files state ────────────────────────────────────
  const [fileDragging, setFileDragging]     = useState(false);
  const [uploadingFiles, setUploadingFiles] = useState(false);
  const [batchId, setBatchId]               = useState("");
  const [uploadedFiles, setUploadedFiles]   = useState([]); // [{name, path}]
  const [fileUploadErrors, setFileUploadErrors] = useState([]);

  // ── Import state ───────────────────────────────────────────
  const [importing, setImporting]   = useState(false);
  const [importResult, setImportResult] = useState(null);

  // ── Extract expected filenames from rows ───────────────────
  const extractExpected = useCallback((rows) => {
    const seen = new Set();
    const list = [];
    rows.forEach((r) => {
      ["upload_file_1", "upload_file_2", "upload_file_3"].forEach((key) => {
        const fname = (r[key] || "").trim();
        if (fname && !seen.has(fname.toLowerCase())) {
          seen.add(fname.toLowerCase());
          list.push(fname);
        }
      });
    });
    return list;
  }, []);

  // ── Parse Excel ────────────────────────────────────────────
  const parseExcel = useCallback(async (file) => {
    if (!file) return;
    const ext = file.name.split(".").pop().toLowerCase();
    if (!["xlsx", "xls", "csv"].includes(ext)) {
      alert("Please upload an .xlsx, .xls, or .csv file.");
      return;
    }
    setExcelFileName(file.name);
    setParsing(true);
    try {
      const res = await uploadExcelPreview(file);
      if (!res.data.success) {
        alert(res.data.message || "Failed to parse file.");
        setExcelFileName("");
        return;
      }
      const vRows = res.data.valid_rows   || [];
      const iRows = res.data.invalid_rows || [];
      setValidRows(vRows);
      setInvalidRows(iRows);
      setExpectedFiles(extractExpected([...vRows, ...iRows]));
      setStep(2);
    } catch (err) {
      alert(err.response?.data?.message || "Server error while parsing file.");
      setExcelFileName("");
    } finally {
      setParsing(false);
    }
  }, [extractExpected]);

  // ── Upload support files ───────────────────────────────────
  const uploadSupport = useCallback(async (fileList) => {
    if (!fileList || fileList.length === 0) return;
    setUploadingFiles(true);
    setFileUploadErrors([]);
    try {
      const form = new FormData();
      Array.from(fileList).forEach((f) => form.append("files[]", f));
      const res = await uploadSupportFiles(form);
      if (!res.data.success) {
        alert(res.data.message || "File upload failed.");
        return;
      }
      setBatchId(res.data.batch_id || "");
      setUploadedFiles((prev) => {
        // merge, avoid duplicates by name
        const prevNames = new Set(prev.map((f) => f.name.toLowerCase()));
        const newFiles  = (res.data.files || []).filter(
          (f) => !prevNames.has(f.name.toLowerCase())
        );
        return [...prev, ...newFiles];
      });
      if (res.data.errors?.length) {
        setFileUploadErrors(res.data.errors);
      }
    } catch (err) {
      alert(err.response?.data?.message || "Server error during file upload.");
    } finally {
      setUploadingFiles(false);
    }
  }, []);

  // ── Import jobs ────────────────────────────────────────────
  const handleImport = useCallback(async () => {
    if (validRows.length === 0) return;
    setImporting(true);
    try {
      const payload = validRows.map(({ _valid, _errors, row_num, ...rest }) => rest);
      const res = await importJobs(payload, batchId);
      setImportResult(res.data);
      setStep(3);
    } catch (err) {
      alert(err.response?.data?.message || "Server error during import.");
    } finally {
      setImporting(false);
    }
  }, [validRows, batchId]);

  const reset = () => {
    setStep(1);
    setExcelFileName("");
    setValidRows([]);
    setInvalidRows([]);
    setExpectedFiles([]);
    setBatchId("");
    setUploadedFiles([]);
    setFileUploadErrors([]);
    setImportResult(null);
  };

  // ── File matching helpers ──────────────────────────────────
  const uploadedNamesLower = new Set(uploadedFiles.map((f) => f.name.toLowerCase()));
  const fileMatchStatus = expectedFiles.map((fname) => ({
    name: fname,
    matched: uploadedNamesLower.has(fname.toLowerCase()),
  }));
  const unmatchedCount = fileMatchStatus.filter((f) => !f.matched).length;
  const hasExpectedFiles = expectedFiles.length > 0;

  // ── Drag handlers ──────────────────────────────────────────
  const onExcelDrop = (e) => {
    e.preventDefault(); setExcelDragging(false);
    parseExcel(e.dataTransfer.files[0]);
  };
  const onFileDrop = (e) => {
    e.preventDefault(); setFileDragging(false);
    uploadSupport(e.dataTransfer.files);
  };

  // ══════════════════════════════════════════════════════════
  // RENDER
  // ══════════════════════════════════════════════════════════
  return (
    <div>
      {/* Page header */}
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Import Jobs from Excel</h2>
      </div>

      {/* Step indicator */}
      <div className="flex items-center gap-3 mb-8">
        {[
          { n: 1, label: "Upload Excel" },
          { n: 2, label: "Files & Preview" },
          { n: 3, label: "Done" },
        ].map(({ n, label }, i, arr) => (
          <div key={n} className="flex items-center gap-2">
            <StepDot n={n} active={step === n} done={step > n} />
            <span className={`text-sm font-medium ${
              step === n ? "text-blue-700" : step > n ? "text-green-600" : "text-gray-400"
            }`}>{label}</span>
            {i < arr.length - 1 && <div className="w-12 h-px bg-gray-300 mx-1" />}
          </div>
        ))}
      </div>

      {/* ════════════════════════════════════════════════════
          STEP 1 — Upload Excel
          ════════════════════════════════════════════════════ */}
      {step === 1 && (
        <div className="max-w-2xl space-y-6">
          {/* Drop zone */}
          <div className="bg-white rounded-xl shadow-sm p-8">
            <div
              onDrop={onExcelDrop}
              onDragOver={(e) => { e.preventDefault(); setExcelDragging(true); }}
              onDragLeave={() => setExcelDragging(false)}
              onClick={() => !parsing && excelRef.current?.click()}
              className={`border-2 border-dashed rounded-xl p-14 text-center transition-colors cursor-pointer ${
                parsing
                  ? "border-blue-300 bg-blue-50 cursor-wait"
                  : excelDragging
                  ? "border-blue-500 bg-blue-50"
                  : "border-gray-300 hover:border-blue-400 hover:bg-gray-50"
              }`}
            >
              {parsing ? (
                <>
                  <div className="flex justify-center mb-4">
                    <svg className="animate-spin h-10 w-10 text-blue-500" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                  </div>
                  <p className="text-base font-semibold text-blue-700">Parsing {excelFileName}…</p>
                  <p className="text-sm text-blue-400 mt-1">Validating rows on server</p>
                </>
              ) : (
                <>
                  <div className="text-5xl mb-4">📊</div>
                  <p className="text-lg font-semibold text-gray-700 mb-1">
                    Drag & drop your Excel or CSV file here
                  </p>
                  <p className="text-sm text-gray-400 mb-4">or click to browse</p>
                  <p className="text-xs text-gray-400">Supports .xlsx · .xls · .csv — max 5 MB</p>
                </>
              )}
              <input
                ref={excelRef}
                type="file"
                accept=".xlsx,.xls,.csv"
                className="hidden"
                onChange={(e) => parseExcel(e.target.files[0])}
              />
            </div>
          </div>

          {/* Column guide */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <p className="text-sm font-semibold text-gray-700 mb-4">📋 Column reference</p>
            <div className="space-y-1.5">
              {COL_GUIDE.map(({ col, req, hint }) => (
                <div key={col} className="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2">
                  <code className="text-xs bg-white border border-gray-200 rounded px-2 py-0.5 text-blue-700 w-36 flex-shrink-0">
                    {col}
                  </code>
                  <span className={`text-xs px-2 py-0.5 rounded font-medium flex-shrink-0 ${
                    req ? "bg-red-50 text-red-600" : "bg-gray-100 text-gray-400"
                  }`}>
                    {req ? "required" : "optional"}
                  </span>
                  <span className="text-xs text-gray-500">{hint}</span>
                </div>
              ))}
            </div>
            <div className="mt-4 bg-blue-50 rounded-lg p-3">
              <p className="text-xs text-blue-700 font-semibold mb-1">📎 File upload columns</p>
              <p className="text-xs text-blue-600">
                Enter <strong>only the file name</strong> in <code>upload_file_1/2/3</code> — e.g.{" "}
                <code className="bg-blue-100 px-1 rounded">invoice_001.pdf</code>.
                Upload the actual files in Step 2. The system matches by name automatically.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* ════════════════════════════════════════════════════
          STEP 2 — Support Files + Preview + Import
          ════════════════════════════════════════════════════ */}
      {step === 2 && (
        <div className="space-y-5">

          {/* Two-panel upload row */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {/* ── Excel summary (left) ──────────────────────── */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <div className="flex items-center justify-between mb-4">
                <h3 className="font-semibold text-gray-700">📊 Excel File</h3>
                <button onClick={reset} className="text-xs text-gray-400 hover:text-gray-600 underline">
                  Change
                </button>
              </div>
              <div className="flex items-center gap-3 bg-green-50 rounded-lg p-3 mb-4">
                <span className="text-green-500 text-xl">✅</span>
                <div>
                  <p className="text-sm font-semibold text-green-800">{excelFileName}</p>
                  <p className="text-xs text-green-600">
                    {validRows.length + invalidRows.length} rows parsed —{" "}
                    {validRows.length} valid, {invalidRows.length} invalid
                  </p>
                </div>
              </div>
              {hasExpectedFiles ? (
                <div>
                  <p className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                    Files referenced in Excel ({expectedFiles.length})
                  </p>
                  <div className="space-y-1.5">
                    {fileMatchStatus.map(({ name, matched }) => (
                      <div key={name} className={`flex items-center gap-2 rounded-lg px-3 py-2 text-xs ${
                        matched ? "bg-green-50" : "bg-yellow-50"
                      }`}>
                        <span className={matched ? "text-green-500" : "text-yellow-500"}>
                          {matched ? "✅" : "⚠"}
                        </span>
                        <code className={`font-mono ${matched ? "text-green-700" : "text-yellow-700"}`}>
                          {name}
                        </code>
                        <span className={`ml-auto ${matched ? "text-green-500" : "text-yellow-600"}`}>
                          {matched ? "matched" : "not uploaded"}
                        </span>
                      </div>
                    ))}
                  </div>
                  {unmatchedCount > 0 && (
                    <p className="text-xs text-yellow-600 mt-2 bg-yellow-50 rounded p-2">
                      ⚠ {unmatchedCount} file(s) not yet uploaded — those jobs will be marked{" "}
                      <strong>failed</strong> on import.
                    </p>
                  )}
                </div>
              ) : (
                <p className="text-xs text-gray-400 italic">
                  No upload_file columns in this Excel — no supporting files needed.
                </p>
              )}
            </div>

            {/* ── Support files upload (right) ──────────────── */}
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h3 className="font-semibold text-gray-700 mb-4">
                📎 Supporting Files
                {!hasExpectedFiles && (
                  <span className="ml-2 text-xs font-normal text-gray-400">(not required)</span>
                )}
              </h3>

              {/* Drop zone */}
              <div
                onDrop={onFileDrop}
                onDragOver={(e) => { e.preventDefault(); setFileDragging(true); }}
                onDragLeave={() => setFileDragging(false)}
                onClick={() => !uploadingFiles && supportRef.current?.click()}
                className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors cursor-pointer mb-4 ${
                  uploadingFiles
                    ? "border-purple-300 bg-purple-50 cursor-wait"
                    : fileDragging
                    ? "border-purple-500 bg-purple-50"
                    : "border-gray-300 hover:border-purple-400 hover:bg-gray-50"
                }`}
              >
                {uploadingFiles ? (
                  <div className="flex flex-col items-center gap-2">
                    <svg className="animate-spin h-8 w-8 text-purple-500" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    <p className="text-sm text-purple-600 font-medium">Uploading…</p>
                  </div>
                ) : (
                  <>
                    <div className="text-3xl mb-2">📁</div>
                    <p className="text-sm font-semibold text-gray-600 mb-1">
                      Drop PDF, JPG, PNG, or ZIP here
                    </p>
                    <p className="text-xs text-gray-400">Multiple files allowed · ZIP is auto-extracted</p>
                  </>
                )}
                <input
                  ref={supportRef}
                  type="file"
                  multiple
                  accept=".pdf,.jpg,.jpeg,.png,.zip"
                  className="hidden"
                  onChange={(e) => uploadSupport(e.target.files)}
                />
              </div>

              {/* Uploaded files list */}
              {uploadedFiles.length > 0 && (
                <div>
                  <p className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
                    Uploaded ({uploadedFiles.length} file{uploadedFiles.length !== 1 ? "s" : ""})
                  </p>
                  <div className="space-y-1 max-h-40 overflow-y-auto">
                    {uploadedFiles.map((f) => (
                      <div key={f.name} className="flex items-center gap-2 bg-gray-50 rounded px-3 py-1.5">
                        <span className="text-green-500 text-xs">✅</span>
                        <code className="text-xs text-gray-700 font-mono truncate">{f.name}</code>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {/* Upload errors */}
              {fileUploadErrors.length > 0 && (
                <div className="mt-3 bg-red-50 rounded-lg p-3">
                  <p className="text-xs font-semibold text-red-700 mb-1">Upload issues:</p>
                  {fileUploadErrors.map((e, i) => (
                    <p key={i} className="text-xs text-red-600">• {e}</p>
                  ))}
                </div>
              )}

              {batchId && (
                <p className="text-xs text-gray-400 mt-3 font-mono">
                  Batch: {batchId}
                </p>
              )}
            </div>
          </div>

          {/* ── Row preview table ──────────────────────────── */}
          {invalidRows.length > 0 && (
            <div className="bg-white rounded-xl shadow-sm overflow-hidden">
              <div className="px-5 py-3 bg-red-50 border-b border-red-100">
                <p className="text-sm font-semibold text-red-700">
                  ⚠ {invalidRows.length} row{invalidRows.length !== 1 ? "s" : ""} with errors — will be skipped
                </p>
              </div>
              <div className="overflow-x-auto">
                <table className="w-full text-xs">
                  <thead className="bg-gray-50 text-gray-500 uppercase">
                    <tr>
                      {["Row","Company","Part No","Qty","Batch","Vendor","Errors"].map(h => (
                        <th key={h} className="px-4 py-2 text-left">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {invalidRows.map((row) => (
                      <tr key={row.row_num} className="bg-red-50">
                        <td className="px-4 py-2 font-medium text-red-700">{row.row_num}</td>
                        <td className="px-4 py-2">{row.company_name || "—"}</td>
                        <td className="px-4 py-2">{row.part_no      || "—"}</td>
                        <td className="px-4 py-2">{row.quantity     || "—"}</td>
                        <td className="px-4 py-2">{row.batch_no     || "—"}</td>
                        <td className="px-4 py-2">{row.vendor_code  || "—"}</td>
                        <td className="px-4 py-2 text-red-600">{(row._errors || []).join(", ")}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {validRows.length > 0 && (
            <div className="bg-white rounded-xl shadow-sm overflow-hidden">
              <div className="px-5 py-3 bg-green-50 border-b border-green-100 flex justify-between items-center">
                <p className="text-sm font-semibold text-green-700">
                  ✅ {validRows.length} valid row{validRows.length !== 1 ? "s" : ""} ready for import
                </p>
                {unmatchedCount > 0 && (
                  <span className="text-xs text-yellow-600 bg-yellow-50 border border-yellow-200 px-2 py-1 rounded">
                    ⚠ {unmatchedCount} file{unmatchedCount !== 1 ? "s" : ""} missing — those jobs will fail
                  </span>
                )}
              </div>
              <div className="overflow-x-auto max-h-72 overflow-y-auto">
                <table className="w-full text-sm">
                  <thead className="bg-gray-50 text-gray-500 uppercase text-xs sticky top-0">
                    <tr>
                      {["#","Company","Part No","Qty","Batch","Vendor","Date","Priority","File 1","File 2","File 3"].map(h => (
                        <th key={h} className="px-3 py-3 text-left whitespace-nowrap">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100">
                    {validRows.map((row, i) => {
                      const f1Matched = row.upload_file_1 && uploadedNamesLower.has(row.upload_file_1.toLowerCase());
                      const f2Matched = row.upload_file_2 && uploadedNamesLower.has(row.upload_file_2.toLowerCase());
                      const f3Matched = row.upload_file_3 && uploadedNamesLower.has(row.upload_file_3.toLowerCase());
                      return (
                        <tr key={i} className="hover:bg-gray-50">
                          <td className="px-3 py-2 text-gray-400 text-xs">{row.row_num}</td>
                          <td className="px-3 py-2 font-medium text-gray-700">{row.company_name}</td>
                          <td className="px-3 py-2 font-mono text-xs text-gray-700">{row.part_no}</td>
                          <td className="px-3 py-2 text-gray-600">{row.quantity}</td>
                          <td className="px-3 py-2 text-gray-600">{row.batch_no}</td>
                          <td className="px-3 py-2 font-mono text-xs text-gray-500">{row.vendor_code}</td>
                          <td className="px-3 py-2 text-gray-500 text-xs">{row.delivery_date || "—"}</td>
                          <td className="px-3 py-2"><PriorityBadge value={row.priority} /></td>
                          <td className="px-3 py-2">
                            {row.upload_file_1
                              ? <FileCell name={row.upload_file_1} matched={f1Matched} batchId={batchId} />
                              : <span className="text-gray-300">—</span>}
                          </td>
                          <td className="px-3 py-2">
                            {row.upload_file_2
                              ? <FileCell name={row.upload_file_2} matched={f2Matched} batchId={batchId} />
                              : <span className="text-gray-300">—</span>}
                          </td>
                          <td className="px-3 py-2">
                            {row.upload_file_3
                              ? <FileCell name={row.upload_file_3} matched={f3Matched} batchId={batchId} />
                              : <span className="text-gray-300">—</span>}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            </div>
          )}

          {validRows.length === 0 && (
            <div className="bg-white rounded-xl shadow-sm p-10 text-center text-gray-400">
              No valid rows found. Fix the errors above and re-upload your Excel file.
            </div>
          )}

          {/* ── Import button ──────────────────────────────── */}
          <div className="flex items-center gap-4 pt-2">
            <button
              onClick={handleImport}
              disabled={importing || validRows.length === 0}
              className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white font-semibold px-8 py-3 rounded-lg transition-colors flex items-center gap-2"
            >
              {importing ? (
                <>
                  <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                  </svg>
                  Importing {validRows.length} jobs…
                </>
              ) : (
                `📥 Import ${validRows.length} Job${validRows.length !== 1 ? "s" : ""} → Queue`
              )}
            </button>
            <button
              onClick={reset}
              className="border border-gray-300 px-6 py-3 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
            >
              ← Start Over
            </button>
            {unmatchedCount > 0 && !importing && (
              <p className="text-xs text-yellow-600">
                ⚠ {unmatchedCount} unmatched file{unmatchedCount !== 1 ? "s" : ""} — upload them first, or proceed
                and those jobs will be marked <strong>failed</strong>.
              </p>
            )}
          </div>
        </div>
      )}

      {/* ════════════════════════════════════════════════════
          STEP 3 — Done
          ════════════════════════════════════════════════════ */}
      {step === 3 && importResult && (
        <div className="max-w-lg">
          <div className="bg-white rounded-xl shadow-sm p-8">
            <div className="text-center mb-6">
              <div className="text-5xl mb-3">✅</div>
              <h3 className="text-xl font-bold text-gray-800">Import Complete</h3>
              <p className="text-gray-500 text-sm mt-1">{importResult.message}</p>
            </div>

            <div className="grid grid-cols-2 gap-4 mb-6">
              <div className="bg-green-50 rounded-xl p-5 text-center">
                <p className="text-3xl font-bold text-green-700">{importResult.created}</p>
                <p className="text-sm text-green-600 mt-1">Jobs Queued</p>
              </div>
              <div className={`rounded-xl p-5 text-center ${
                (importResult.errors?.length || 0) > 0 ? "bg-red-50" : "bg-gray-50"
              }`}>
                <p className={`text-3xl font-bold ${
                  (importResult.errors?.length || 0) > 0 ? "text-red-700" : "text-gray-400"
                }`}>
                  {importResult.errors?.length || 0}
                </p>
                <p className={`text-sm mt-1 ${
                  (importResult.errors?.length || 0) > 0 ? "text-red-600" : "text-gray-400"
                }`}>
                  Errors
                </p>
              </div>
            </div>

            {importResult.errors?.length > 0 && (
              <div className="bg-red-50 rounded-lg p-4 mb-5">
                <p className="text-xs font-semibold text-red-700 mb-2">Import Errors:</p>
                <ul className="text-xs text-red-600 space-y-1">
                  {importResult.errors.map((e, i) => <li key={i}>• {e}</li>)}
                </ul>
              </div>
            )}

            <div className="bg-blue-50 rounded-lg p-4 mb-6">
              <p className="text-sm text-blue-700 font-semibold mb-1">🤖 Next step — run the bot</p>
              <p className="text-xs text-blue-600 mb-2">
                The bot reads the stored file paths and uploads them automatically via Playwright:
              </p>
              <code className="block bg-blue-900 text-blue-100 text-xs rounded px-3 py-2 font-mono mb-1">
                cd bot
              </code>
              <code className="block bg-blue-900 text-blue-100 text-xs rounded px-3 py-2 font-mono">
                python bot.py
              </code>
            </div>

            <div className="flex gap-3">
              <button
                onClick={() => navigate("/jobs")}
                className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors"
              >
                View Job Queue →
              </button>
              <button
                onClick={reset}
                className="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50"
              >
                Import Another
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ── Small helper component for file cell in preview table ─────
function FileCell({ name, matched, batchId }) {
  return (
    <span className={`inline-flex items-center gap-1 text-xs font-mono px-1.5 py-0.5 rounded ${
      !batchId
        ? "bg-gray-100 text-gray-500"
        : matched
        ? "bg-green-100 text-green-700"
        : "bg-yellow-100 text-yellow-700"
    }`}>
      {batchId ? (matched ? "✅" : "⚠") : "·"} {name}
    </span>
  );
}

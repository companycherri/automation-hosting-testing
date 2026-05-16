import { useState, useEffect } from "react";
import { useNavigate } from "react-router-dom";
import { createJob, getCompanies, getParts } from "../api/api";
import { formatJobId } from "../utils/formatJobId";

const VENDOR_CODES = ["VND-001", "VND-002", "VND-003", "VND-004", "VND-005"];

const initialForm = {
  company_name: "",
  part_no:      "",
  quantity:     "",
  batch_no:     "",
  vendor_code:  "",
};

export default function CreateJobPage() {
  const navigate = useNavigate();

  const [form, setForm]         = useState(initialForm);
  const [companies, setCompanies] = useState([]);
  const [parts, setParts]       = useState([]);
  const [error, setError]       = useState("");
  const [success, setSuccess]   = useState("");
  const [loading, setLoading]   = useState(false);
  const [loadingMeta, setLoadingMeta] = useState(true);

  // Load companies + parts from API on mount
  useEffect(() => {
    Promise.all([getCompanies(), getParts()])
      .then(([cRes, pRes]) => {
        setCompanies(cRes.data.companies || []);
        setParts(pRes.data.parts || []);
        // Pre-select first option
        setForm((f) => ({
          ...f,
          company_name: cRes.data.companies?.[0]?.company_name || "",
          part_no:      pRes.data.parts?.[0]?.part_code || "",
          vendor_code:  VENDOR_CODES[0],
        }));
      })
      .catch(() => setError("Failed to load companies/parts. Is XAMPP running?"))
      .finally(() => setLoadingMeta(false));
  }, []);

  const set = (field) => (e) => setForm({ ...form, [field]: e.target.value });

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError("");
    setSuccess("");
    setLoading(true);
    try {
      const res = await createJob({ ...form, quantity: Number(form.quantity) });
      if (res.data.success) {
        const jobId = formatJobId(res.data.job_id);
        setSuccess(`${jobId} created! The bot will process it shortly.`);
        setForm((f) => ({ ...initialForm, company_name: f.company_name, part_no: f.part_no, vendor_code: f.vendor_code }));
      } else {
        setError(res.data.message || "Failed to create job.");
      }
    } catch (err) {
      setError(err.response?.data?.message || "Server error.");
    } finally {
      setLoading(false);
    }
  };

  const selectClass = "w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500";
  const inputClass  = "w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500";

  return (
    <div>
      <h2 className="text-2xl font-bold text-gray-800 mb-6">Create New Job</h2>

      <div className="bg-white rounded-xl shadow-sm p-8 max-w-xl">
        {error && (
          <div className="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 mb-5 text-sm">{error}</div>
        )}
        {success && (
          <div className="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-5 text-sm flex items-center justify-between">
            <span>✅ {success}</span>
            <button onClick={() => navigate("/jobs")} className="underline text-green-800 ml-4 whitespace-nowrap">
              View Jobs →
            </button>
          </div>
        )}

        {loadingMeta ? (
          <div className="text-gray-400 text-sm py-6 text-center">Loading form data…</div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-5">

            {/* Company dropdown */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">
                Company Name <span className="text-red-500">*</span>
              </label>
              <select value={form.company_name} onChange={set("company_name")} required className={selectClass}>
                <option value="">— Select Company —</option>
                {companies.map((c) => (
                  <option key={c.id} value={c.company_name}>{c.company_name}</option>
                ))}
              </select>
            </div>

            {/* Part Name dropdown */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">
                Part Name <span className="text-red-500">*</span>
              </label>
              <select value={form.part_no} onChange={set("part_no")} required className={selectClass}>
                <option value="">— Select Part —</option>
                {parts.map((p) => (
                  <option key={p.id} value={p.part_code}>
                    {p.part_name} ({p.part_code})
                  </option>
                ))}
              </select>
              {form.part_no && (
                <p className="text-xs text-gray-400 mt-1">Code: <strong>{form.part_no}</strong></p>
              )}
            </div>

            {/* Quantity */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">
                Quantity <span className="text-red-500">*</span>
              </label>
              <input
                type="number"
                min="1"
                value={form.quantity}
                onChange={set("quantity")}
                placeholder="e.g. 100"
                required
                className={inputClass}
              />
            </div>

            {/* Batch Number */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">
                Batch Number <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                value={form.batch_no}
                onChange={set("batch_no")}
                placeholder="e.g. BATCH-2024-01"
                required
                className={inputClass}
              />
            </div>

            {/* Vendor Code dropdown */}
            <div>
              <label className="block text-sm font-semibold text-gray-700 mb-1">
                Vendor Code <span className="text-red-500">*</span>
              </label>
              <select value={form.vendor_code} onChange={set("vendor_code")} required className={selectClass}>
                <option value="">— Select Vendor —</option>
                {VENDOR_CODES.map((v) => (
                  <option key={v} value={v}>{v}</option>
                ))}
              </select>
            </div>

            <div className="flex gap-3 pt-2">
              <button
                type="submit"
                disabled={loading}
                className="bg-blue-600 hover:bg-blue-700 disabled:opacity-60 text-white font-semibold px-6 py-2.5 rounded-lg text-sm transition-colors"
              >
                {loading ? "Creating…" : "Create Job"}
              </button>
              <button
                type="button"
                onClick={() => navigate("/jobs")}
                className="border border-gray-300 px-6 py-2.5 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}

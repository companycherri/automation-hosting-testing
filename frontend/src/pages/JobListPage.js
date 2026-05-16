import { useEffect, useState, useCallback, useRef } from "react";
import { Link } from "react-router-dom";
import { getJobs, resetFailedJobs, downloadFileUrl } from "../api/api";
import StatusBadge from "../components/StatusBadge";
import { formatJobId } from "../utils/formatJobId";

const FILTERS = ["", "pending", "processing", "success", "failed"];

// Short error type labels shown in the table
const ERROR_TYPE_LABEL = {
  field_validation_error: "Validation",
  submit_disabled:        "Submit Disabled",
  login_error:            "Login",
  dropdown_option_error:  "Dropdown",
  file_upload_error:      "File Upload",
  download_error:         "Download",
  timeout_error:          "Timeout",
  portal_alert_error:     "Portal Alert",
  company_not_found:      "No Config",
  unknown_error:          "Error",
};

function ProcessingErrorBadge({ job }) {
  const err = job.processing_error;
  if (!err) return null;
  return (
    <div className="group relative inline-block max-w-[200px]">
      <span className="block truncate text-xs bg-red-50 text-red-700 border border-red-200 px-2 py-0.5 rounded font-medium cursor-help">
        ⚠ {err}
      </span>
      {/* Tooltip with full text */}
      <div className="hidden group-hover:block absolute z-50 bottom-full left-0 mb-1 w-80 bg-gray-900 text-white text-xs rounded-lg p-3 shadow-xl">
        <p className="font-semibold text-red-300 mb-1">
          {ERROR_TYPE_LABEL[job.bot_error_type] || "Error"} — {job.bot_error_field || "unknown field"}
        </p>
        {job.bot_excel_value && (
          <p className="text-gray-300">Excel value: <span className="text-yellow-300 font-mono">{job.bot_excel_value}</span></p>
        )}
        <p className="text-gray-200 mt-1 break-words">{job.bot_portal_error_message || err}</p>
        {job.bot_error_step && (
          <p className="text-gray-400 mt-1 text-xs">Step: {job.bot_error_step}</p>
        )}
      </div>
    </div>
  );
}
const REFRESH_INTERVAL = 3000; // 3 seconds

function PriorityBadge({ value }) {
  const v = (value || "normal").toLowerCase();
  const map = {
    urgent:  "bg-yellow-100 text-yellow-800",
    express: "bg-red-100 text-red-700",
    normal:  "bg-gray-100 text-gray-500",
  };
  return (
    <span className={`text-xs px-2 py-0.5 rounded font-medium capitalize ${map[v] || map.normal}`}>
      {v}
    </span>
  );
}

export default function JobListPage() {
  const [jobs, setJobs]           = useState([]);
  const [filter, setFilter]       = useState("");
  const [loading, setLoading]     = useState(true);
  const [resetting, setResetting] = useState(false);
  const [resetMsg, setResetMsg]   = useState("");
  const [lastRefresh, setLastRefresh] = useState(null);
  const [pulse, setPulse]         = useState(false);   // live dot blink
  const timerRef                  = useRef(null);

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const res = await getJobs(filter);
      setJobs(res.data.jobs || []);
      setLastRefresh(new Date());
      // Blink the live dot
      setPulse(true);
      setTimeout(() => setPulse(false), 600);
    } catch {
      // silent
    } finally {
      if (!silent) setLoading(false);
    }
  }, [filter]);

  // Initial load when filter changes
  useEffect(() => { load(); }, [load]);

  // Auto-refresh every 3 seconds (silent)
  useEffect(() => {
    timerRef.current = setInterval(() => load(true), REFRESH_INTERVAL);
    return () => clearInterval(timerRef.current);
  }, [load]);

  const handleReset = async () => {
    setResetting(true);
    setResetMsg("");
    try {
      const res = await resetFailedJobs();
      setResetMsg(`✅ ${res.data.message}`);
      load();
    } catch {
      setResetMsg("❌ Reset failed. Check API.");
    } finally {
      setResetting(false);
      setTimeout(() => setResetMsg(""), 5000);
    }
  };

  const failedCount   = jobs.filter((j) => j.status === "failed").length;
  const pendingCount  = jobs.filter((j) => j.status === "pending").length;
  const processingCount = jobs.filter((j) => j.status === "processing").length;

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-4">
        <div className="flex items-center gap-3">
          <h2 className="text-2xl font-bold text-gray-800">Job Queue</h2>
          {/* Live indicator */}
          <div className="flex items-center gap-1.5 bg-green-50 border border-green-200 rounded-full px-3 py-1">
            <span className={`w-2 h-2 rounded-full ${pulse ? "bg-green-400" : "bg-green-300"} transition-colors`} />
            <span className="text-xs text-green-700 font-medium">Live · 3s refresh</span>
          </div>
        </div>
        <div className="flex items-center gap-3">
          {lastRefresh && (
            <span className="text-xs text-gray-400">
              Updated {lastRefresh.toLocaleTimeString()}
            </span>
          )}
          {(filter === "" || filter === "failed") && failedCount > 0 && (
            <button
              onClick={handleReset}
              disabled={resetting}
              className="bg-red-500 hover:bg-red-600 disabled:opacity-60 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
            >
              {resetting ? "Resetting…" : `🔄 Retry ${failedCount} Failed`}
            </button>
          )}
          <Link
            to="/upload"
            className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
          >
            📥 Import Excel
          </Link>
        </div>
      </div>

      {/* Reset feedback */}
      {resetMsg && (
        <div className="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3 rounded-lg">
          {resetMsg}
        </div>
      )}

      {/* Pending/processing notice */}
      {(pendingCount > 0 || processingCount > 0) && (
        <div className="mb-4 bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <span className="text-yellow-600 text-sm">
              🤖 {pendingCount > 0 && `${pendingCount} pending`}
              {pendingCount > 0 && processingCount > 0 && " · "}
              {processingCount > 0 && `${processingCount} processing`}
            </span>
            <span className="text-yellow-500 text-xs">— run the bot to process them</span>
          </div>
          <code className="text-xs bg-yellow-100 text-yellow-800 px-2 py-1 rounded font-mono">
            cd bot &nbsp;→&nbsp; python bot.py
          </code>
        </div>
      )}

      {/* Filter tabs */}
      <div className="flex gap-2 mb-5">
        {FILTERS.map((s) => (
          <button
            key={s || "all"}
            onClick={() => setFilter(s)}
            className={`px-4 py-1.5 rounded-full text-sm font-medium transition-colors capitalize ${
              filter === s
                ? "bg-blue-600 text-white"
                : "bg-white border border-gray-300 text-gray-600 hover:bg-gray-50"
            }`}
          >
            {s || "All"}
          </button>
        ))}
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-5 py-3 text-left">Job ID</th>
                <th className="px-5 py-3 text-left">Company</th>
                <th className="px-5 py-3 text-left">Part No</th>
                <th className="px-5 py-3 text-left">Qty</th>
                <th className="px-5 py-3 text-left">Batch</th>
                <th className="px-5 py-3 text-left">Vendor</th>
                <th className="px-5 py-3 text-left">Priority</th>
                <th className="px-5 py-3 text-left">Status</th>
                <th className="px-5 py-3 text-left">Processing Error</th>
                <th className="px-5 py-3 text-center">Tries</th>
                <th className="px-5 py-3 text-left">Created</th>
                <th className="px-5 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan={12} className="px-6 py-10 text-center text-gray-400">
                    <svg className="animate-spin h-5 w-5 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Loading…
                  </td>
                </tr>
              ) : jobs.length === 0 ? (
                <tr>
                  <td colSpan={12} className="px-6 py-10 text-center text-gray-400">
                    No jobs found.{" "}
                    <Link to="/upload" className="text-blue-500 hover:underline">
                      Import from Excel →
                    </Link>
                  </td>
                </tr>
              ) : (
                jobs.map((job) => (
                  <tr
                    key={job.id}
                    className={`hover:bg-gray-50 transition-colors ${
                      job.processing_error       ? "bg-red-50" :
                      job.status === "failed"    ? "bg-red-50" :
                      job.status === "processing"? "bg-blue-50" : ""
                    }`}
                  >
                    <td className="px-5 py-3 font-mono font-semibold text-gray-800 whitespace-nowrap">
                      {formatJobId(job.id)}
                    </td>
                    <td className="px-5 py-3 text-gray-700">{job.company_name}</td>
                    <td className="px-5 py-3 text-gray-700 font-mono text-xs">{job.part_no}</td>
                    <td className="px-5 py-3 text-gray-500">{job.quantity}</td>
                    <td className="px-5 py-3 text-gray-500">{job.batch_no}</td>
                    <td className="px-5 py-3 text-gray-500 font-mono text-xs">{job.vendor_code}</td>
                    <td className="px-5 py-3">
                      <PriorityBadge value={job.priority} />
                    </td>
                    <td className="px-5 py-3">
                      <StatusBadge status={job.status} />
                    </td>
                    <td className="px-5 py-3 max-w-[220px]">
                      <ProcessingErrorBadge job={job} />
                    </td>
                    <td className="px-5 py-3 text-gray-400 text-center">{job.attempt_count ?? 0}</td>
                    <td className="px-5 py-3 text-gray-400 whitespace-nowrap text-xs">{job.created_at}</td>
                    <td className="px-5 py-3">
                      <div className="flex items-center gap-2 whitespace-nowrap">
                        <Link
                          to={`/jobs/${job.id}`}
                          className="text-blue-600 hover:underline text-xs font-medium"
                        >
                          View →
                        </Link>
                        {job.status === "success" && job.barcode_file_path && (
                          <a
                            href={downloadFileUrl(job.id)}
                            className="text-green-600 hover:underline text-xs font-medium"
                            title="Download barcode file"
                          >
                            ⬇ File
                          </a>
                        )}
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Table footer */}
        {jobs.length > 0 && (
          <div className="px-5 py-3 border-t border-gray-100 flex justify-between items-center">
            <span className="text-xs text-gray-400">{jobs.length} job{jobs.length !== 1 ? "s" : ""}</span>
            <span className="text-xs text-gray-300">Auto-refreshes every 3 seconds</span>
          </div>
        )}
      </div>
    </div>
  );
}

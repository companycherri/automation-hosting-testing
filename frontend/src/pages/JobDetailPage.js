import { useEffect, useState } from "react";
import { useParams, Link } from "react-router-dom";
import { getJobDetail, downloadFileUrl } from "../api/api";
import StatusBadge from "../components/StatusBadge";
import { formatJobId } from "../utils/formatJobId";

const ERROR_TYPE_LABEL = {
  field_validation_error: "Field Validation Error",
  submit_disabled:        "Submit Button Disabled",
  login_error:            "Login Error",
  dropdown_option_error:  "Dropdown Option Error",
  file_upload_error:      "File Upload Error",
  download_error:         "Download Error",
  timeout_error:          "Timeout",
  portal_alert_error:     "Portal Alert",
  company_not_found:      "Company Not Found in Config",
  unknown_error:          "Unknown Error",
};

const BASE_URL = "http://localhost/mini-automation";

function PriorityBadge({ value }) {
  const v = (value || "normal").toLowerCase();
  const map = {
    urgent:  "bg-yellow-100 text-yellow-800 border border-yellow-200",
    express: "bg-red-100 text-red-700 border border-red-200",
    normal:  "bg-gray-100 text-gray-600 border border-gray-200",
  };
  return (
    <span className={`text-xs px-2 py-0.5 rounded font-medium capitalize ${map[v] || map.normal}`}>
      {v}
    </span>
  );
}

export default function JobDetailPage() {
  const { id }          = useParams();
  const [job, setJob]   = useState(null);
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState("");

  useEffect(() => {
    const load = async () => {
      try {
        const res = await getJobDetail(id);
        if (res.data.success) {
          setJob(res.data.job);
          setLogs(res.data.logs);
        } else {
          setError(res.data.message);
        }
      } catch {
        setError("Failed to load job detail.");
      } finally {
        setLoading(false);
      }
    };
    load();
    // Refresh every 5 seconds while job is active
    const interval = setInterval(load, 5_000);
    return () => clearInterval(interval);
  }, [id]);

  if (loading) return (
    <div className="flex items-center justify-center py-20 text-gray-400">
      <svg className="animate-spin h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24">
        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
      Loading…
    </div>
  );
  if (error) return <div className="text-red-500 p-8">{error}</div>;

  const row = (label, value, extra) => (
    <div className="flex py-2.5 border-b border-gray-100 last:border-0">
      <span className="w-44 text-sm text-gray-500 font-medium flex-shrink-0">{label}</span>
      <span className="text-sm text-gray-800 break-all flex items-center gap-2">
        {value ?? "—"} {extra}
      </span>
    </div>
  );

  const actionColor = (action) => {
    if (action.includes("FAIL") || action.includes("ERROR")) return "bg-red-100 text-red-700";
    if (action.includes("SUCCESS"))  return "bg-green-100 text-green-700";
    if (action.includes("START") || action.includes("CREATED")) return "bg-blue-100 text-blue-700";
    return "bg-gray-100 text-gray-600";
  };

  return (
    <div>
      {/* Page header */}
      <div className="flex items-center gap-3 mb-6">
        <Link to="/jobs" className="text-gray-400 hover:text-gray-600 text-sm">← Back</Link>
        <h2 className="text-2xl font-bold text-gray-800 font-mono">{formatJobId(job.id)}</h2>
        <StatusBadge status={job.status} />
        {(job.status === "pending" || job.status === "processing") && (
          <span className="text-xs text-gray-400 animate-pulse">auto-refreshing…</span>
        )}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

        {/* Job details */}
        <div className="bg-white rounded-xl shadow-sm p-6">
          <h3 className="font-semibold text-gray-700 mb-4">Job Details</h3>
          {row("Company",       job.company_name)}
          {row("Part No",       job.part_no)}
          {row("Quantity",      job.quantity)}
          {row("Batch No",      job.batch_no)}
          {row("Vendor Code",   job.vendor_code)}
          {row("Delivery Date", job.delivery_date || "—")}
          {row("Priority",      null, <PriorityBadge value={job.priority} />)}
          {job.notes && row("Notes", job.notes)}
          {row("Attempts",      job.attempt_count ?? 0)}
          {row("Created At",    job.created_at)}
          {row("Updated At",    job.updated_at)}
          {/* Upload files */}
          {(job.upload_file_1_name || job.upload_file_2_name || job.upload_file_3_name) && (
            <div className="mt-3 pt-3 border-t border-gray-100">
              <p className="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Upload Files</p>
              {[
                { label: "File 1", name: job.upload_file_1_name, path: job.upload_file_1_path },
                { label: "File 2", name: job.upload_file_2_name, path: job.upload_file_2_path },
                { label: "File 3", name: job.upload_file_3_name, path: job.upload_file_3_path },
              ].filter(({ name }) => name).map(({ label, name, path }) => (
                <div key={label} className="flex items-start gap-2 mb-2">
                  <span className="text-xs text-gray-400 w-10 flex-shrink-0 pt-1">{label}</span>
                  <div className="min-w-0">
                    <span className="text-xs font-semibold text-gray-700">{name}</span>
                    {path ? (
                      <p className="text-xs font-mono text-blue-600 break-all mt-0.5 bg-blue-50 px-1.5 py-0.5 rounded">
                        {path}
                      </p>
                    ) : (
                      <p className="text-xs text-red-500 mt-0.5">⚠ Path not resolved — file was not uploaded</p>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
          {job.error_message && (
            <div className="mt-3 bg-red-50 rounded-lg p-3">
              <p className="text-xs font-semibold text-red-700 mb-1">Error</p>
              <p className="text-xs text-red-600 break-all">{job.error_message}</p>
            </div>
          )}
        </div>

        {/* File & Screenshot */}
        <div className="space-y-4">
          {/* Barcode file */}
          <div className="bg-white rounded-xl shadow-sm p-6">
            <h3 className="font-semibold text-gray-700 mb-4">Barcode File</h3>
            {job.status === "success" && job.barcode_file_path ? (
              <>
                <p className="text-xs text-gray-400 mb-3 break-all font-mono bg-gray-50 rounded p-2">
                  {job.barcode_file_path}
                </p>
                <a
                  href={downloadFileUrl(job.id)}
                  className="inline-flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors"
                >
                  ⬇ Download Barcode File
                </a>
              </>
            ) : (
              <p className="text-sm text-gray-400">
                {job.status === "pending"
                  ? "⏳ Waiting for bot to pick up this job."
                  : job.status === "processing"
                  ? "🤖 Bot is currently processing this job…"
                  : "No file generated."}
              </p>
            )}
          </div>

          {/* Screenshot (if exists) */}
          {job.screenshot_path && (
            <div className="bg-white rounded-xl shadow-sm p-6">
              <h3 className="font-semibold text-gray-700 mb-4">Screenshot</h3>
              <img
                src={`http://localhost/mini-automation/${job.screenshot_path.replace(/\\/g, "/")}`}
                alt="Bot screenshot"
                className="w-full rounded-lg border border-gray-200"
                onError={(e) => { e.target.style.display = "none"; }}
              />
              <p className="text-xs text-gray-400 mt-2 break-all">{job.screenshot_path}</p>
            </div>
          )}

          {/* Bot run instruction for pending jobs */}
          {(job.status === "pending" || job.status === "failed") && (
            <div className="bg-blue-50 border border-blue-200 rounded-xl p-4">
              <p className="text-sm font-semibold text-blue-800 mb-1">🤖 Run the bot to process this job</p>
              <code className="text-xs bg-blue-900 text-blue-100 rounded px-3 py-2 block font-mono mt-2">
                cd bot
              </code>
              <code className="text-xs bg-blue-900 text-blue-100 rounded px-3 py-2 block font-mono mt-1">
                python bot.py
              </code>
            </div>
          )}
        </div>
      </div>

      {/* Bot Error Details — only shown when a bot error was recorded */}
      {job.bot_error_type && (
        <div className="mb-6 bg-white rounded-xl shadow-sm overflow-hidden border-l-4 border-red-400">
          <div className="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span className="text-red-500 text-lg">⚠</span>
              <h3 className="font-semibold text-gray-800">Bot Error Details</h3>
              <span className="text-xs bg-red-100 text-red-700 border border-red-200 px-2 py-0.5 rounded font-medium">
                {ERROR_TYPE_LABEL[job.bot_error_type] || job.bot_error_type}
              </span>
            </div>
            {job.failed_at && (
              <span className="text-xs text-gray-400">Failed at {job.failed_at}</span>
            )}
          </div>

          <div className="p-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            {/* Left: structured error info */}
            <div className="space-y-0">
              {[
                ["Step",          job.bot_error_step],
                ["Field",         job.bot_error_field],
                ["Excel Value",   job.bot_excel_value],
                ["Error Type",    ERROR_TYPE_LABEL[job.bot_error_type] || job.bot_error_type],
              ].map(([label, val]) => val ? (
                <div key={label} className="flex py-2.5 border-b border-gray-100 last:border-0">
                  <span className="w-36 text-sm text-gray-500 font-medium flex-shrink-0">{label}</span>
                  <span className={`text-sm break-all font-${label === "Excel Value" ? "mono text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded" : "medium text-gray-800"}`}>
                    {val}
                  </span>
                </div>
              ) : null)}

              {/* Portal error message — full width highlight */}
              {job.bot_portal_error_message && (
                <div className="mt-3 bg-red-50 border border-red-200 rounded-lg p-3">
                  <p className="text-xs font-semibold text-red-700 mb-1 uppercase tracking-wide">Portal Error Message</p>
                  <p className="text-sm text-red-800 break-words">{job.bot_portal_error_message}</p>
                </div>
              )}
            </div>

            {/* Right: screenshot */}
            <div>
              {job.screenshot_path ? (
                <div>
                  <p className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Error Screenshot</p>
                  <a
                    href={`${BASE_URL}/${job.screenshot_path.replace(/\\/g, "/")}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    <img
                      src={`${BASE_URL}/${job.screenshot_path.replace(/\\/g, "/")}`}
                      alt="Error screenshot"
                      className="w-full rounded-lg border border-gray-200 hover:opacity-90 transition-opacity cursor-pointer"
                      onError={(e) => { e.target.parentElement.innerHTML = '<p class="text-xs text-gray-400 italic">Screenshot not available</p>'; }}
                    />
                  </a>
                  <p className="text-xs text-gray-400 mt-1.5 break-all font-mono">{job.screenshot_path}</p>
                </div>
              ) : (
                <div className="flex items-center justify-center h-32 bg-gray-50 rounded-lg border border-dashed border-gray-200">
                  <p className="text-sm text-gray-400">No screenshot captured</p>
                </div>
              )}
            </div>
          </div>
        </div>
      )}

      {/* Activity logs */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100">
          <h3 className="font-semibold text-gray-700">Activity Log</h3>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-6 py-3 text-left">Time</th>
                <th className="px-6 py-3 text-left">Action</th>
                <th className="px-6 py-3 text-left">Message</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {logs.length === 0 ? (
                <tr>
                  <td colSpan={3} className="px-6 py-6 text-center text-gray-400">
                    No logs yet. Logs appear when the bot starts processing this job.
                  </td>
                </tr>
              ) : (
                logs.map((l) => (
                  <tr key={l.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3 text-gray-400 whitespace-nowrap text-xs">{l.created_at}</td>
                    <td className="px-6 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded font-mono ${actionColor(l.action)}`}>
                        {l.action}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-gray-700 text-sm">{l.message}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}

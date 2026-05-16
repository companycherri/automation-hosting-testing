import { useEffect, useState, useCallback } from "react";
import { Link } from "react-router-dom";
import { getBotErrors } from "../api/api";
import { formatJobId } from "../utils/formatJobId";

const BASE_URL = "http://localhost/mini-automation";

const ERROR_TYPE_LABEL = {
  field_validation_error: "Field Validation",
  submit_disabled:        "Submit Disabled",
  login_error:            "Login Error",
  dropdown_option_error:  "Dropdown",
  file_upload_error:      "File Upload",
  download_error:         "Download",
  timeout_error:          "Timeout",
  portal_alert_error:     "Portal Alert",
  company_not_found:      "No Config",
  unknown_error:          "Unknown",
};

const ERROR_TYPE_COLOR = {
  field_validation_error: "bg-orange-100 text-orange-700",
  submit_disabled:        "bg-yellow-100 text-yellow-700",
  login_error:            "bg-purple-100 text-purple-700",
  dropdown_option_error:  "bg-blue-100 text-blue-700",
  file_upload_error:      "bg-indigo-100 text-indigo-700",
  download_error:         "bg-pink-100 text-pink-700",
  timeout_error:          "bg-gray-200 text-gray-600",
  portal_alert_error:     "bg-red-100 text-red-700",
  company_not_found:      "bg-slate-100 text-slate-700",
  unknown_error:          "bg-gray-100 text-gray-500",
};

function SummaryCard({ label, count, color }) {
  return (
    <div className={`bg-white rounded-xl shadow-sm p-4 border-l-4 ${color}`}>
      <p className="text-2xl font-bold text-gray-800">{count}</p>
      <p className="text-xs text-gray-500 mt-0.5">{label}</p>
    </div>
  );
}

function ErrorTypeBadge({ type }) {
  const label = ERROR_TYPE_LABEL[type] || type;
  const cls   = ERROR_TYPE_COLOR[type]  || "bg-gray-100 text-gray-500";
  return (
    <span className={`text-xs px-2 py-0.5 rounded font-medium ${cls}`}>
      {label}
    </span>
  );
}

export default function ErrorsPage() {
  const [errors,  setErrors]  = useState([]);
  const [summary, setSummary] = useState(null);
  const [options, setOptions] = useState({ companies: [], fields: [], error_types: [] });
  const [loading, setLoading] = useState(true);

  // Filter state
  const [company,   setCompany]   = useState("");
  const [field,     setField]     = useState("");
  const [errorType, setErrorType] = useState("");
  const [dateFrom,  setDateFrom]  = useState("");
  const [dateTo,    setDateTo]    = useState("");
  const [jobId,     setJobId]     = useState("");
  const [limit,     setLimit]     = useState(100);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const params = {};
      if (company)   params.company_name = company;
      if (field)     params.field_key    = field;
      if (errorType) params.error_type   = errorType;
      if (dateFrom)  params.date_from    = dateFrom;
      if (dateTo)    params.date_to      = dateTo;
      if (jobId)     params.job_id       = jobId;
      if (limit)     params.limit        = limit;

      const res = await getBotErrors(params);
      if (res.data.success) {
        setErrors(res.data.errors || []);
        setSummary(res.data.summary || null);
        setOptions(res.data.filter_options || { companies: [], fields: [], error_types: [] });
      }
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, [company, field, errorType, dateFrom, dateTo, jobId, limit]);

  useEffect(() => { load(); }, [load]);

  const clearFilters = () => {
    setCompany(""); setField(""); setErrorType("");
    setDateFrom(""); setDateTo(""); setJobId(""); setLimit(100);
  };

  const hasFilters = company || field || errorType || dateFrom || dateTo || jobId;

  return (
    <div>
      {/* Header */}
      <div className="flex items-center justify-between mb-5">
        <div>
          <h2 className="text-2xl font-bold text-gray-800">🚨 Bot Errors</h2>
          <p className="text-sm text-gray-400 mt-0.5">All portal errors detected during bot execution</p>
        </div>
        <button
          onClick={load}
          className="bg-white border border-gray-300 hover:bg-gray-50 text-gray-600 text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
        >
          🔄 Refresh
        </button>
      </div>

      {/* Summary cards */}
      {summary && (
        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
          <SummaryCard label="Total Errors"       count={summary.total}             color="border-red-400" />
          <SummaryCard label="Field Validation"   count={summary.field_validation}  color="border-orange-400" />
          <SummaryCard label="Submit Disabled"    count={summary.submit_disabled}   color="border-yellow-400" />
          <SummaryCard label="Login Errors"       count={summary.login_errors}      color="border-purple-400" />
          <SummaryCard label="Timeout"            count={summary.timeout_errors}    color="border-gray-400" />
          <SummaryCard label="Other"              count={summary.other_errors}      color="border-blue-400" />
        </div>
      )}

      {/* Filter bar */}
      <div className="bg-white rounded-xl shadow-sm p-4 mb-5">
        <div className="flex flex-wrap gap-3 items-end">
          {/* Company */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">Company</label>
            <select
              value={company}
              onChange={e => setCompany(e.target.value)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              <option value="">All Companies</option>
              {options.companies.map(c => <option key={c} value={c}>{c}</option>)}
            </select>
          </div>

          {/* Error Type */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">Error Type</label>
            <select
              value={errorType}
              onChange={e => setErrorType(e.target.value)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              <option value="">All Types</option>
              {options.error_types.map(t => (
                <option key={t} value={t}>{ERROR_TYPE_LABEL[t] || t}</option>
              ))}
            </select>
          </div>

          {/* Field */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">Field</label>
            <select
              value={field}
              onChange={e => setField(e.target.value)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              <option value="">All Fields</option>
              {options.fields.map(f => <option key={f} value={f}>{f}</option>)}
            </select>
          </div>

          {/* Job ID */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">Job ID</label>
            <input
              type="text"
              value={jobId}
              onChange={e => setJobId(e.target.value)}
              placeholder="e.g. 42"
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-24 focus:outline-none focus:ring-2 focus:ring-blue-300"
            />
          </div>

          {/* Date From */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">From</label>
            <input
              type="date"
              value={dateFrom}
              onChange={e => setDateFrom(e.target.value)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            />
          </div>

          {/* Date To */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">To</label>
            <input
              type="date"
              value={dateTo}
              onChange={e => setDateTo(e.target.value)}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            />
          </div>

          {/* Limit */}
          <div className="flex flex-col gap-1">
            <label className="text-xs text-gray-500 font-medium">Show</label>
            <select
              value={limit}
              onChange={e => setLimit(Number(e.target.value))}
              className="text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300"
            >
              <option value={50}>50</option>
              <option value={100}>100</option>
              <option value={200}>200</option>
              <option value={500}>500</option>
            </select>
          </div>

          {hasFilters && (
            <button
              onClick={clearFilters}
              className="self-end text-xs text-gray-400 hover:text-gray-600 border border-gray-200 rounded-lg px-3 py-1.5 transition-colors"
            >
              ✕ Clear
            </button>
          )}
        </div>
      </div>

      {/* Table */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-4 py-3 text-left">ID</th>
                <th className="px-4 py-3 text-left">Job</th>
                <th className="px-4 py-3 text-left">Company</th>
                <th className="px-4 py-3 text-left">Step</th>
                <th className="px-4 py-3 text-left">Field</th>
                <th className="px-4 py-3 text-left">Excel Value</th>
                <th className="px-4 py-3 text-left">Portal Error Message</th>
                <th className="px-4 py-3 text-left">Error Type</th>
                <th className="px-4 py-3 text-center">Shot</th>
                <th className="px-4 py-3 text-left">Time</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan={10} className="px-6 py-12 text-center text-gray-400">
                    <svg className="animate-spin h-5 w-5 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Loading…
                  </td>
                </tr>
              ) : errors.length === 0 ? (
                <tr>
                  <td colSpan={10} className="px-6 py-12 text-center">
                    <p className="text-gray-400 text-sm">
                      {hasFilters ? "No errors match the current filters." : "No bot errors recorded yet. 🎉"}
                    </p>
                    {hasFilters && (
                      <button onClick={clearFilters} className="mt-2 text-xs text-blue-500 hover:underline">
                        Clear filters
                      </button>
                    )}
                  </td>
                </tr>
              ) : (
                errors.map((e) => (
                  <tr key={e.id} className="hover:bg-red-50 transition-colors">
                    {/* Error ID */}
                    <td className="px-4 py-3 font-mono text-xs text-gray-400">#{e.id}</td>

                    {/* Job ID → link */}
                    <td className="px-4 py-3">
                      <Link
                        to={`/jobs/${e.job_id}`}
                        className="font-mono font-semibold text-blue-600 hover:underline text-xs"
                      >
                        {formatJobId(e.job_id)}
                      </Link>
                      {e.part_no && (
                        <p className="text-xs text-gray-400 font-mono mt-0.5">{e.part_no}</p>
                      )}
                    </td>

                    {/* Company */}
                    <td className="px-4 py-3 text-gray-700 text-xs whitespace-nowrap">{e.company_name}</td>

                    {/* Step */}
                    <td className="px-4 py-3 text-gray-500 text-xs font-mono whitespace-nowrap">
                      {e.step_name || "—"}
                    </td>

                    {/* Field */}
                    <td className="px-4 py-3 text-gray-800 text-xs font-mono whitespace-nowrap">
                      {e.field_key || "—"}
                    </td>

                    {/* Excel Value */}
                    <td className="px-4 py-3">
                      {e.excel_value ? (
                        <span className="text-xs font-mono bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200">
                          {e.excel_value}
                        </span>
                      ) : (
                        <span className="text-xs text-gray-300">—</span>
                      )}
                    </td>

                    {/* Portal Error Message */}
                    <td className="px-4 py-3 max-w-[280px]">
                      {e.portal_error_message ? (
                        <div className="group relative">
                          <p className="text-xs text-red-700 truncate max-w-[260px] cursor-help">
                            {e.portal_error_message}
                          </p>
                          {e.portal_error_message.length > 60 && (
                            <div className="hidden group-hover:block absolute z-50 bottom-full left-0 mb-1 w-80 bg-gray-900 text-white text-xs rounded-lg p-3 shadow-xl">
                              {e.portal_error_message}
                            </div>
                          )}
                        </div>
                      ) : (
                        <span className="text-xs text-gray-300">—</span>
                      )}
                    </td>

                    {/* Error Type */}
                    <td className="px-4 py-3 whitespace-nowrap">
                      <ErrorTypeBadge type={e.error_type} />
                    </td>

                    {/* Screenshot */}
                    <td className="px-4 py-3 text-center">
                      {e.screenshot_path ? (
                        <a
                          href={`${BASE_URL}/${e.screenshot_path.replace(/\\/g, "/")}`}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-block"
                          title="View screenshot"
                        >
                          <img
                            src={`${BASE_URL}/${e.screenshot_path.replace(/\\/g, "/")}`}
                            alt="screenshot"
                            className="w-16 h-10 object-cover rounded border border-gray-200 hover:opacity-80 transition-opacity"
                            onError={(e) => { e.target.style.display = "none"; }}
                          />
                        </a>
                      ) : (
                        <span className="text-gray-300 text-xs">—</span>
                      )}
                    </td>

                    {/* Time */}
                    <td className="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{e.created_at}</td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Footer */}
        {errors.length > 0 && (
          <div className="px-5 py-3 border-t border-gray-100 flex justify-between items-center">
            <span className="text-xs text-gray-400">
              {errors.length} error{errors.length !== 1 ? "s" : ""} shown
            </span>
            {hasFilters && (
              <button onClick={clearFilters} className="text-xs text-gray-400 hover:text-gray-600">
                ✕ Clear filters
              </button>
            )}
          </div>
        )}
      </div>
    </div>
  );
}

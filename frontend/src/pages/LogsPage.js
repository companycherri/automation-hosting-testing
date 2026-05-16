import { useEffect, useState, useCallback } from "react";
import { getLogs } from "../api/api";

export default function LogsPage() {
  const [logs, setLogs]       = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await getLogs();
      setLogs(res.data.logs);
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    // Refresh every 4 seconds to catch new bot activity quickly
    const interval = setInterval(load, 4_000);
    return () => clearInterval(interval);
  }, [load]);

  const actionColor = (action) => {
    if (action.includes("FAIL") || action.includes("ERROR")) return "bg-red-100 text-red-700";
    if (action.includes("SUCCESS"))  return "bg-green-100 text-green-700";
    if (action.includes("START") || action.includes("CREATED")) return "bg-blue-100 text-blue-700";
    return "bg-gray-100 text-gray-600";
  };

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Activity Logs</h2>
        <button
          onClick={load}
          className="text-sm bg-white border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors"
        >
          Refresh
        </button>
      </div>

      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-6 py-3 text-left">Time</th>
                <th className="px-6 py-3 text-left">Job ID</th>
                <th className="px-6 py-3 text-left">Company</th>
                <th className="px-6 py-3 text-left">Action</th>
                <th className="px-6 py-3 text-left">Message</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr><td colSpan={5} className="px-6 py-10 text-center text-gray-400">Loading…</td></tr>
              ) : logs.length === 0 ? (
                <tr><td colSpan={5} className="px-6 py-10 text-center text-gray-400">No logs yet.</td></tr>
              ) : (
                logs.map((l) => (
                  <tr key={l.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3 text-gray-400 whitespace-nowrap text-xs">{l.created_at}</td>
                    <td className="px-6 py-3 text-gray-600 font-medium">#{l.job_id}</td>
                    <td className="px-6 py-3 text-gray-500 text-xs">{l.company_name || "—"}</td>
                    <td className="px-6 py-3">
                      <span className={`text-xs px-2 py-0.5 rounded font-mono ${actionColor(l.action)}`}>
                        {l.action}
                      </span>
                    </td>
                    <td className="px-6 py-3 text-gray-700 text-xs max-w-md">{l.message}</td>
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

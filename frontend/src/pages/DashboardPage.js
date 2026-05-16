import { useEffect, useState, useCallback } from "react";
import { Link } from "react-router-dom";
import { getJobs } from "../api/api";
import StatusBadge from "../components/StatusBadge";
import { formatJobId } from "../utils/formatJobId";

const summaryCards = [
  { key: "total",      label: "Total Jobs",      bg: "bg-gray-800",  text: "text-white" },
  { key: "pending",    label: "Pending",          bg: "bg-yellow-400", text: "text-yellow-900" },
  { key: "processing", label: "Processing",       bg: "bg-blue-500",  text: "text-white" },
  { key: "success",    label: "Success",          bg: "bg-green-500", text: "text-white" },
  { key: "failed",     label: "Failed",           bg: "bg-red-500",   text: "text-white" },
];

export default function DashboardPage() {
  const [summary, setSummary] = useState({ total: 0, pending: 0, processing: 0, success: 0, failed: 0 });
  const [jobs, setJobs]       = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await getJobs();
      setSummary(res.data.summary);
      setJobs(res.data.jobs.slice(0, 8)); // latest 8 for recent list
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    // Auto-refresh every 3 seconds
    const interval = setInterval(load, 3_000);
    return () => clearInterval(interval);
  }, [load]);

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <h2 className="text-2xl font-bold text-gray-800">Dashboard</h2>
          <div className="flex items-center gap-1.5 bg-green-50 border border-green-200 rounded-full px-3 py-1">
            <span className="w-2 h-2 rounded-full bg-green-400 animate-pulse" />
            <span className="text-xs text-green-700 font-medium">Live · 3s</span>
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link
            to="/upload"
            className="bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg transition-colors flex items-center gap-2"
          >
            📥 Import Excel
          </Link>
          <button
            onClick={load}
            className="text-sm bg-white border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Refresh
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
        {summaryCards.map(({ key, label, bg, text }) => (
          <div key={key} className={`${bg} ${text} rounded-xl p-5 shadow-sm`}>
            <p className="text-3xl font-bold">{loading ? "–" : summary[key]}</p>
            <p className="text-sm mt-1 opacity-80">{label}</p>
          </div>
        ))}
      </div>

      {/* Recent Jobs */}
      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="px-6 py-4 border-b border-gray-100 flex justify-between items-center">
          <h3 className="font-semibold text-gray-700">Recent Jobs</h3>
          <Link to="/jobs" className="text-sm text-blue-600 hover:underline">View all →</Link>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-6 py-3 text-left">ID</th>
                <th className="px-6 py-3 text-left">Company</th>
                <th className="px-6 py-3 text-left">Part No</th>
                <th className="px-6 py-3 text-left">Batch</th>
                <th className="px-6 py-3 text-left">Status</th>
                <th className="px-6 py-3 text-left">Created</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr><td colSpan={6} className="px-6 py-8 text-center text-gray-400">Loading…</td></tr>
              ) : jobs.length === 0 ? (
                <tr><td colSpan={6} className="px-6 py-8 text-center text-gray-400">No jobs yet. <Link to="/create-job" className="text-blue-500 hover:underline">Create one</Link></td></tr>
              ) : (
                jobs.map((job) => (
                  <tr key={job.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3">
                      <Link to={`/jobs/${job.id}`} className="text-blue-600 hover:underline font-mono font-medium">{formatJobId(job.id)}</Link>
                    </td>
                    <td className="px-6 py-3 text-gray-700">{job.company_name}</td>
                    <td className="px-6 py-3 text-gray-700">{job.part_no}</td>
                    <td className="px-6 py-3 text-gray-500">{job.batch_no}</td>
                    <td className="px-6 py-3"><StatusBadge status={job.status} /></td>
                    <td className="px-6 py-3 text-gray-400">{job.created_at}</td>
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

import { useEffect, useState, useCallback } from "react";
import { Link } from "react-router-dom";
import { getDownloads, downloadFileUrl } from "../api/api";
import { formatJobId } from "../utils/formatJobId";

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

export default function DownloadsPage() {
  const [jobs, setJobs]       = useState([]);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    try {
      const res = await getDownloads();
      setJobs(res.data.downloads || []);
    } catch {
      // silent
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
    // Refresh every 10 seconds (downloads change less frequently)
    const interval = setInterval(load, 10_000);
    return () => clearInterval(interval);
  }, [load]);

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Downloads</h2>
        <div className="flex items-center gap-3">
          <span className="text-sm text-gray-400">{jobs.length} file{jobs.length !== 1 ? "s" : ""}</span>
          <button
            onClick={load}
            className="text-sm bg-white border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors"
          >
            Refresh
          </button>
        </div>
      </div>

      <div className="bg-white rounded-xl shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
              <tr>
                <th className="px-6 py-3 text-left">Job ID</th>
                <th className="px-6 py-3 text-left">Company</th>
                <th className="px-6 py-3 text-left">Part No</th>
                <th className="px-6 py-3 text-left">Batch</th>
                <th className="px-6 py-3 text-left">Priority</th>
                <th className="px-6 py-3 text-left">Completed At</th>
                <th className="px-6 py-3 text-left">File</th>
                <th className="px-6 py-3 text-left">Download</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-gray-100">
              {loading ? (
                <tr>
                  <td colSpan={8} className="px-6 py-10 text-center text-gray-400">
                    <svg className="animate-spin h-5 w-5 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24">
                      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                    </svg>
                    Loading…
                  </td>
                </tr>
              ) : jobs.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-6 py-10 text-center text-gray-400">
                    No downloads yet.{" "}
                    <Link to="/upload" className="text-blue-500 hover:underline">
                      Import jobs from Excel
                    </Link>{" "}
                    and run the bot to generate barcode files.
                  </td>
                </tr>
              ) : (
                jobs.map((job) => (
                  <tr key={job.id} className="hover:bg-gray-50">
                    <td className="px-6 py-3">
                      <Link to={`/jobs/${job.id}`} className="text-blue-600 hover:underline font-mono text-xs">
                        {formatJobId(job.id)}
                      </Link>
                    </td>
                    <td className="px-6 py-3 text-gray-700">{job.company_name}</td>
                    <td className="px-6 py-3 text-gray-700 font-mono text-xs">{job.part_no}</td>
                    <td className="px-6 py-3 text-gray-500">{job.batch_no}</td>
                    <td className="px-6 py-3">
                      <PriorityBadge value={job.priority} />
                    </td>
                    <td className="px-6 py-3 text-gray-400 whitespace-nowrap text-xs">{job.updated_at}</td>
                    <td className="px-6 py-3 text-gray-500 text-xs max-w-xs truncate" title={job.barcode_file_path}>
                      {job.barcode_file_path
                        ? job.barcode_file_path.split(/[/\\]/).pop()
                        : job.download_url
                        ? job.download_url.split("/").pop()
                        : "—"}
                    </td>
                    <td className="px-6 py-3">
                      <a
                        href={downloadFileUrl(job.id)}
                        className="inline-flex items-center gap-1 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition-colors"
                      >
                        ⬇ Download
                      </a>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {jobs.length > 0 && (
          <div className="px-6 py-3 border-t border-gray-100">
            <p className="text-xs text-gray-400">
              Files are served directly from the portal's <code>generated/</code> folder via the bot download.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}

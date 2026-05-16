const colours = {
  pending:    "bg-yellow-100 text-yellow-800",
  processing: "bg-blue-100 text-blue-800",
  success:    "bg-green-100 text-green-800",
  failed:     "bg-red-100 text-red-800",
};

export default function StatusBadge({ status }) {
  const cls = colours[status] || "bg-gray-100 text-gray-700";
  return (
    <span className={`inline-block px-2.5 py-0.5 rounded-full text-xs font-semibold capitalize ${cls}`}>
      {status}
    </span>
  );
}

// Formats a numeric job ID as JOB-0001, JOB-0042, etc.
export function formatJobId(id) {
  return `JOB-${String(id).padStart(4, "0")}`;
}

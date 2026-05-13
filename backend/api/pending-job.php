<?php
// ============================================================
// GET /api/pending-job.php
// Used by the Python bot to claim the next pending job atomically.
// Returns: { success, job } or { success: false } if none pending
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$db = getDB();

// Lock row to prevent two bot instances picking the same job
$db->beginTransaction();

$stmt = $db->prepare(
    'SELECT * FROM barcode_jobs WHERE status = "pending" ORDER BY created_at ASC LIMIT 1 FOR UPDATE'
);
$stmt->execute();
$job = $stmt->fetch();

if (!$job) {
    $db->commit();
    echo json_encode(['success' => false, 'message' => 'No pending jobs.']);
    exit;
}

// Immediately mark as processing so no other bot picks it
$upd = $db->prepare(
    'UPDATE barcode_jobs SET status = "processing", attempt_count = attempt_count + 1, updated_at = NOW() WHERE id = ?'
);
$upd->execute([$job['id']]);

$db->commit();

// Return full job details including company credentials + field mappings
$company_stmt = $db->prepare('SELECT * FROM companies WHERE company_name = ? AND status = "active" LIMIT 1');
$company_stmt->execute([$job['company_name']]);
$company = $company_stmt->fetch();

// Field mappings for this company (used by universal bot)
$map_stmt = $db->prepare(
    'SELECT * FROM field_mappings WHERE company_name = ? ORDER BY step_no ASC, sort_order ASC'
);
$map_stmt->execute([$job['company_name']]);
$mappings = $map_stmt->fetchAll();

echo json_encode([
    'success'  => true,
    'job'      => $job,
    'company'  => $company,
    'mappings' => $mappings,
]);

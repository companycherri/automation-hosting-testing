<?php
// ============================================================
// GET /api/field-mappings.php?company=Company+A
// Returns field mappings for a company.
// Used by the bot to know how to fill form fields.
// ============================================================

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$company = trim($_GET['company'] ?? '');

if (empty($company)) {
    echo json_encode(['success' => false, 'message' => 'company parameter required']);
    exit;
}

$db   = getDB();
$stmt = $db->prepare(
    "SELECT * FROM field_mappings WHERE company_name = ? ORDER BY step_no ASC, sort_order ASC"
);
$stmt->execute([$company]);
$mappings = $stmt->fetchAll();

echo json_encode([
    'success'  => true,
    'company'  => $company,
    'mappings' => $mappings,
]);

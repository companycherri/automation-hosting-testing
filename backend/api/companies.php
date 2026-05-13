<?php
// GET /api/companies.php — returns all active companies
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$db   = getDB();
$rows = $db->query("SELECT id, company_name FROM companies WHERE status='active' ORDER BY company_name")->fetchAll();

echo json_encode(['success' => true, 'companies' => $rows]);

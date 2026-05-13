<?php
// GET /api/parts.php — returns all active parts
require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/db.php';

$db   = getDB();
$rows = $db->query("SELECT id, part_name, part_code FROM parts WHERE status='active' ORDER BY id")->fetchAll();

echo json_encode(['success' => true, 'parts' => $rows]);

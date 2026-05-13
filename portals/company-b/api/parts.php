<?php
// Company B — Searchable Parts API
// GET /portals/company-b/api/parts.php?q=eng
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$all_parts = [
    ['code'=>'ENG-001','name'=>'Engine Block'],
    ['code'=>'TRN-002','name'=>'Transmission Case'],
    ['code'=>'BRK-003','name'=>'Brake Caliper'],
    ['code'=>'STR-004','name'=>'Steering Wheel'],
    ['code'=>'FUL-005','name'=>'Fuel Injector'],
    ['code'=>'ALT-006','name'=>'Alternator'],
    ['code'=>'RAD-007','name'=>'Radiator Cap'],
    ['code'=>'OIL-008','name'=>'Oil Filter'],
    ['code'=>'SPK-009','name'=>'Spark Plug'],
    ['code'=>'AIR-010','name'=>'Air Filter'],
    ['code'=>'EXH-011','name'=>'Exhaust Manifold'],
    ['code'=>'CAM-012','name'=>'Camshaft'],
    ['code'=>'CRK-013','name'=>'Crankshaft'],
    ['code'=>'PST-014','name'=>'Piston Assembly'],
    ['code'=>'TIM-015','name'=>'Timing Belt'],
];

$q = strtolower(trim($_GET['q'] ?? ''));
if ($q === '') {
    echo json_encode($all_parts); exit;
}

$results = array_values(array_filter($all_parts, function($p) use ($q) {
    return strpos(strtolower($p['code']), $q) !== false ||
           strpos(strtolower($p['name']), $q) !== false;
}));

echo json_encode($results);

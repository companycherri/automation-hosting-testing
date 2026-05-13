<?php
// Company C — Subcategories API (dependent on selected category)
// GET ?category=AUTO
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

usleep(800000); // simulate 0.8s load delay

$cat = $_GET['category'] ?? '';

$map = [
    'AUTO' => [
        ['code'=>'ENG-001','name'=>'Engine Block'],
        ['code'=>'TRN-002','name'=>'Transmission Case'],
        ['code'=>'BRK-003','name'=>'Brake Caliper'],
        ['code'=>'STR-004','name'=>'Steering Wheel'],
        ['code'=>'FUL-005','name'=>'Fuel Injector'],
    ],
    'ELEC' => [
        ['code'=>'ALT-006','name'=>'Alternator'],
        ['code'=>'SPK-009','name'=>'Spark Plug'],
        ['code'=>'ECU-020','name'=>'Engine Control Unit'],
        ['code'=>'SEN-021','name'=>'Oxygen Sensor'],
        ['code'=>'IGN-022','name'=>'Ignition Coil'],
    ],
    'HYDR' => [
        ['code'=>'PMP-030','name'=>'Hydraulic Pump'],
        ['code'=>'CYL-031','name'=>'Hydraulic Cylinder'],
        ['code'=>'VLV-032','name'=>'Control Valve'],
        ['code'=>'FLT-033','name'=>'Hydraulic Filter'],
    ],
    'MECH' => [
        ['code'=>'BRG-040','name'=>'Bearing Assembly'],
        ['code'=>'GER-041','name'=>'Gear Set'],
        ['code'=>'CHN-042','name'=>'Drive Chain'],
        ['code'=>'BLT-043','name'=>'Drive Belt'],
        ['code'=>'CPL-044','name'=>'Shaft Coupling'],
    ],
    'SAFE' => [
        ['code'=>'SFV-050','name'=>'Safety Valve'],
        ['code'=>'EMG-051','name'=>'Emergency Stop Button'],
        ['code'=>'GRD-052','name'=>'Machine Guard'],
        ['code'=>'SHL-053','name'=>'Safety Shield'],
    ],
];

$items = $map[$cat] ?? [];
echo json_encode(['success'=>true,'items'=>$items,'category'=>$cat]);

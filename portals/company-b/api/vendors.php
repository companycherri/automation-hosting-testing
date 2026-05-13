<?php
// Company B — AJAX Vendor Loader (with simulated delay)
// GET /portals/company-b/api/vendors.php
// Simulates real-world API delay
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Simulate loading delay (1.5 seconds)
usleep(1500000);

$vendors = [
    ['code'=>'VND-B-001','name'=>'AutoParts Global Sdn Bhd'],
    ['code'=>'VND-B-002','name'=>'MechTech Industries'],
    ['code'=>'VND-B-003','name'=>'Precision Parts Co.'],
    ['code'=>'VND-B-004','name'=>'Allied Manufacturing'],
    ['code'=>'VND-B-005','name'=>'TechDrive Suppliers'],
    ['code'=>'VND-B-006','name'=>'PrimeParts Malaysia'],
];

echo json_encode(['success'=>true,'vendors'=>$vendors]);

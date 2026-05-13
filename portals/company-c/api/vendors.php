<?php
// Company C — Multi-select Vendors API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    ['code'=>'VND-C-001','name'=>'PrimeTech Solutions','country'=>'Malaysia'],
    ['code'=>'VND-C-002','name'=>'AutoCraft Industries','country'=>'Thailand'],
    ['code'=>'VND-C-003','name'=>'Apex Engineering','country'=>'Singapore'],
    ['code'=>'VND-C-004','name'=>'ProParts Global','country'=>'Indonesia'],
    ['code'=>'VND-C-005','name'=>'TechDrive Asia','country'=>'Vietnam'],
    ['code'=>'VND-C-006','name'=>'Sigma Manufacturing','country'=>'Philippines'],
    ['code'=>'VND-C-007','name'=>'Allied Components','country'=>'Malaysia'],
]);

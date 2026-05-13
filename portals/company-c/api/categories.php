<?php
// Company C — Categories API (for custom React-style dropdown)
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

echo json_encode([
    ['id'=>'AUTO','name'=>'Automotive Parts','icon'=>'🚗'],
    ['id'=>'ELEC','name'=>'Electrical Components','icon'=>'⚡'],
    ['id'=>'HYDR','name'=>'Hydraulic Systems','icon'=>'💧'],
    ['id'=>'MECH','name'=>'Mechanical Assemblies','icon'=>'⚙️'],
    ['id'=>'SAFE','name'=>'Safety Equipment','icon'=>'🛡️'],
]);

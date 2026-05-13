<?php
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_C_SID');
session_start();
$logged_in = !empty($_SESSION['logged_in']) || ($_COOKIE['co_c_auth'] ?? '') === 'ok';
if (!$logged_in) { header('Location: login.php'); exit; }
$file = basename($_GET['file'] ?? '');
$filepath = __DIR__ . '/generated/' . $file;
if (!$file || !file_exists($filepath)) { http_response_code(404); die('File not found.'); }
header('Content-Type: text/plain');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');
readfile($filepath); exit;

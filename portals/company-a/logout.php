<?php
$sess_dir = __DIR__ . '/sessions';
if (!is_dir($sess_dir)) mkdir($sess_dir, 0755, true);
session_save_path($sess_dir);
session_name('COMPANY_A_SID');
session_start();
session_destroy();
setcookie('co_a_auth', '', time() - 3600, '/');
header('Location: login.php');
exit;

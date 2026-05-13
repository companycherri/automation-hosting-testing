<?php
// ============================================================
// ONE-TIME DATABASE INITIALIZER
// Visit: http://localhost/mini-automation/backend/init.php
// ============================================================

$host   = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'barcode_portal';

try {
    $pdo = new PDO("mysql:host={$host};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$dbName}`");

    // ── users ──────────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100) NOT NULL,
            email      VARCHAR(150) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            role       ENUM('admin','operator') DEFAULT 'operator',
            status     ENUM('active','inactive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ── companies ──────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS companies (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            company_name VARCHAR(150) NOT NULL,
            portal_url   VARCHAR(300) NOT NULL,
            login_url    VARCHAR(300) NOT NULL,
            username     VARCHAR(100) NOT NULL,
            password     VARCHAR(100) NOT NULL,
            status       ENUM('active','inactive') DEFAULT 'active',
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // ── parts ──────────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS parts (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            part_name VARCHAR(150) NOT NULL,
            part_code VARCHAR(50)  NOT NULL UNIQUE,
            status    ENUM('active','inactive') DEFAULT 'active'
        )
    ");

    // ── barcode_jobs ───────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barcode_jobs (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            company_name      VARCHAR(150) NOT NULL,
            part_no           VARCHAR(100) NOT NULL,
            quantity          INT NOT NULL,
            batch_no          VARCHAR(100) NOT NULL,
            vendor_code       VARCHAR(100) NOT NULL,
            status            ENUM('pending','processing','success','failed') DEFAULT 'pending',
            attempt_count     INT DEFAULT 0,
            error_message     TEXT,
            barcode_file_path VARCHAR(500),
            created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    // ── activity_logs ──────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS activity_logs (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            job_id     INT,
            action     VARCHAR(100) NOT NULL,
            message    TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (job_id) REFERENCES barcode_jobs(id) ON DELETE SET NULL
        )
    ");

    // ── Admin user (always reset so password is always correct) ──
    $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
    $pdo->exec("DELETE FROM users WHERE email = 'admin@portal.com'");
    $pdo->prepare("INSERT INTO users (name, email, password, role, status) VALUES ('Admin User', 'admin@portal.com', ?, 'admin', 'active')")
        ->execute([$adminPassword]);

    // ── 3 Companies (all point to the same dummy portal for demo) ──
    $pdo->exec("DELETE FROM companies");
    $companies = [
        [1, 'Toyota Industries',
            'http://localhost/mini-automation/dummy-portal/',
            'http://localhost/mini-automation/dummy-portal/login.php',
            'admin', '123456'],
        [2, 'Honda Logistics',
            'http://localhost/mini-automation/dummy-portal/',
            'http://localhost/mini-automation/dummy-portal/login.php',
            'admin', '123456'],
        [3, 'Suzuki Parts Co.',
            'http://localhost/mini-automation/dummy-portal/',
            'http://localhost/mini-automation/dummy-portal/login.php',
            'admin', '123456'],
    ];
    $cStmt = $pdo->prepare("INSERT INTO companies (id, company_name, portal_url, login_url, username, password, status) VALUES (?,?,?,?,?,?,'active')");
    foreach ($companies as $c) $cStmt->execute($c);

    // ── 10 Parts ───────────────────────────────────────────
    $pdo->exec("DELETE FROM parts");
    $parts = [
        ['Engine Block',       'ENG-001'],
        ['Transmission Case',  'TRN-002'],
        ['Brake Caliper',      'BRK-003'],
        ['Steering Wheel',     'STR-004'],
        ['Fuel Injector',      'FUL-005'],
        ['Alternator',         'ALT-006'],
        ['Radiator Cap',       'RAD-007'],
        ['Oil Filter',         'OIL-008'],
        ['Spark Plug',         'SPK-009'],
        ['Air Filter',         'AIR-010'],
    ];
    $pStmt = $pdo->prepare("INSERT INTO parts (part_name, part_code, status) VALUES (?, ?, 'active')");
    foreach ($parts as $p) $pStmt->execute($p);

    // ── Output ─────────────────────────────────────────────
    echo '<pre style="font-family:monospace;background:#f0fff4;border:1px solid #68d391;padding:24px;border-radius:10px;max-width:640px;margin:40px auto;line-height:1.7">';
    echo "✅  Database initialized successfully!\n\n";
    echo "Database : {$dbName}\n";
    echo "Tables   : users, companies, parts, barcode_jobs, activity_logs\n\n";
    echo "Admin login:\n";
    echo "  Email    : admin@portal.com\n";
    echo "  Password : admin123\n\n";
    echo "Companies seeded (3):\n";
    echo "  1. Toyota Industries\n";
    echo "  2. Honda Logistics\n";
    echo "  3. Suzuki Parts Co.\n\n";
    echo "Parts seeded (10):\n";
    $partList = ['Engine Block','Transmission Case','Brake Caliper','Steering Wheel','Fuel Injector','Alternator','Radiator Cap','Oil Filter','Spark Plug','Air Filter'];
    foreach ($partList as $i => $name) echo "  " . ($i+1) . ". {$name}\n";
    echo "\n⚠️  Delete or protect init.php before going to production.\n";
    echo '</pre>';
    echo '<p style="text-align:center;font-family:sans-serif"><a href="http://localhost:3000/login" style="color:#3182ce">→ Go to React Dashboard</a></p>';

} catch (PDOException $e) {
    echo '<pre style="color:red;padding:20px;font-family:monospace">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}

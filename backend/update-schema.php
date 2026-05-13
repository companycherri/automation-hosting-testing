<?php
// ============================================================
// SCHEMA UPDATER — Run once after upgrading the project
// Visit: http://localhost/mini-automation/backend/update-schema.php
// Safely adds new columns, tables, and seed data.
// ============================================================

$host   = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'barcode_portal';

$log = [];

function run($pdo, $sql, $desc) {
    global $log;
    try {
        $pdo->exec($sql);
        $log[] = "✅ $desc";
    } catch (PDOException $e) {
        $log[] = "⚠️  $desc — " . $e->getMessage();
    }
}

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // ── barcode_jobs: add missing columns ──────────────────
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN delivery_date DATE NULL", "barcode_jobs.delivery_date");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN priority VARCHAR(20) DEFAULT 'normal'", "barcode_jobs.priority");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN notes TEXT NULL", "barcode_jobs.notes");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN screenshot_path VARCHAR(500) NULL", "barcode_jobs.screenshot_path");
    run($pdo, "ALTER TABLE barcode_jobs MODIFY COLUMN status ENUM('pending','processing','success','failed','retry') DEFAULT 'pending'", "barcode_jobs.status enum update");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_1_name VARCHAR(255) NULL", "barcode_jobs.upload_file_1_name");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_1_path VARCHAR(500) NULL", "barcode_jobs.upload_file_1_path");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_2_name VARCHAR(255) NULL", "barcode_jobs.upload_file_2_name");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_2_path VARCHAR(500) NULL", "barcode_jobs.upload_file_2_path");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_3_name VARCHAR(255) NULL", "barcode_jobs.upload_file_3_name");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN upload_file_3_path VARCHAR(500) NULL", "barcode_jobs.upload_file_3_path");

    // ── barcode_jobs: bot error tracking columns ───────────
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN processing_error TEXT NULL",                   "barcode_jobs.processing_error");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN bot_error_field VARCHAR(100) NULL",             "barcode_jobs.bot_error_field");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN bot_error_type VARCHAR(50) NULL",               "barcode_jobs.bot_error_type");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN bot_error_step VARCHAR(50) NULL",               "barcode_jobs.bot_error_step");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN bot_excel_value VARCHAR(255) NULL",             "barcode_jobs.bot_excel_value");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN bot_portal_error_message TEXT NULL",            "barcode_jobs.bot_portal_error_message");
    run($pdo, "ALTER TABLE barcode_jobs ADD COLUMN failed_at DATETIME NULL",                       "barcode_jobs.failed_at");

    // ── bot_error_logs: one row per bot field/step error ───
    run($pdo, "
        CREATE TABLE IF NOT EXISTS bot_error_logs (
            id                   INT AUTO_INCREMENT PRIMARY KEY,
            job_id               INT NOT NULL,
            company_name         VARCHAR(100) NOT NULL DEFAULT '',
            step_name            VARCHAR(50)  NULL,
            field_key            VARCHAR(100) NULL,
            excel_value          TEXT         NULL,
            portal_error_message TEXT         NULL,
            error_type           VARCHAR(50)  NOT NULL DEFAULT 'unknown_error',
            selector             VARCHAR(300) NULL,
            screenshot_path      VARCHAR(500) NULL,
            page_url             VARCHAR(500) NULL,
            created_at           DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_job   (job_id),
            INDEX idx_co    (company_name),
            INDEX idx_etype (error_type),
            INDEX idx_field (field_key)
        )
    ", "bot_error_logs table");

    // ── companies: add portal config columns ───────────────
    run($pdo, "ALTER TABLE companies ADD COLUMN form_url VARCHAR(300) NULL", "companies.form_url");
    run($pdo, "ALTER TABLE companies ADD COLUMN login_type VARCHAR(30) DEFAULT 'simple'", "companies.login_type");
    run($pdo, "ALTER TABLE companies ADD COLUMN portal_type VARCHAR(30) DEFAULT 'simple'", "companies.portal_type");
    run($pdo, "ALTER TABLE companies ADD COLUMN login_username_selector VARCHAR(100) DEFAULT '#username'", "companies.login_username_selector");
    run($pdo, "ALTER TABLE companies ADD COLUMN login_password_selector VARCHAR(100) DEFAULT '#password'", "companies.login_password_selector");
    run($pdo, "ALTER TABLE companies ADD COLUMN login_submit_selector VARCHAR(100) DEFAULT '#login-btn'", "companies.login_submit_selector");
    run($pdo, "ALTER TABLE companies ADD COLUMN form_submit_selector VARCHAR(100) DEFAULT '#submit-btn'", "companies.form_submit_selector");
    run($pdo, "ALTER TABLE companies ADD COLUMN form_success_url VARCHAR(200) DEFAULT '**/generate.php'", "companies.form_success_url");
    run($pdo, "ALTER TABLE companies ADD COLUMN download_selector VARCHAR(100) DEFAULT '#download-btn'", "companies.download_selector");
    run($pdo, "ALTER TABLE companies ADD COLUMN extra_config TEXT NULL", "companies.extra_config");

    // ── field_mappings table ────────────────────────────────
    run($pdo, "
        CREATE TABLE IF NOT EXISTS field_mappings (
            id                INT AUTO_INCREMENT PRIMARY KEY,
            company_name      VARCHAR(100) NOT NULL,
            field_key         VARCHAR(50)  NOT NULL,
            selector          VARCHAR(300) DEFAULT '',
            field_type        VARCHAR(50)  NOT NULL,
            dropdown_type     VARCHAR(50)  NULL,
            required          TINYINT      DEFAULT 1,
            fallback_selector VARCHAR(300) NULL,
            extra_config      TEXT         NULL,
            step_no           INT          DEFAULT 1,
            sort_order        INT          DEFAULT 0,
            created_at        DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_company (company_name)
        )
    ", "field_mappings table");

    // ── Re-seed companies with portal configs ───────────────
    $pdo->exec("DELETE FROM companies");
    $companies = [
        [
            'company_name'             => 'Company A',
            'portal_url'               => 'http://localhost/mini-automation/portals/company-a/',
            'login_url'                => 'http://localhost/mini-automation/portals/company-a/login.php',
            'form_url'                 => 'http://localhost/mini-automation/portals/company-a/form.php',
            'username'                 => 'operator_a',
            'password'                 => 'pass_a123',
            'login_type'               => 'simple',
            'portal_type'              => 'simple',
            'login_username_selector'  => '#username',
            'login_password_selector'  => '#password',
            'login_submit_selector'    => '#login-btn',
            'form_submit_selector'     => '#submit-btn',
            'form_success_url'         => '**/generate.php',
            'download_selector'        => '#download-btn',
            'extra_config'             => null,
        ],
        [
            'company_name'             => 'Company B',
            'portal_url'               => 'http://localhost/mini-automation/portals/company-b/',
            'login_url'                => 'http://localhost/mini-automation/portals/company-b/login.php',
            'form_url'                 => 'http://localhost/mini-automation/portals/company-b/form.php',
            'username'                 => 'b.operator@company.com',
            'password'                 => 'Bpass@2024',
            'login_type'               => 'simple',
            'portal_type'              => 'modal',
            'login_username_selector'  => '#email',
            'login_password_selector'  => '#password',
            'login_submit_selector'    => '#login-btn',
            'form_submit_selector'     => '[data-testid="submit-form"]',
            'form_success_url'         => '**/generate.php',
            'download_selector'        => '#download-btn',
            'extra_config'             => json_encode([
                'modal' => [
                    'overlay' => '.modal-overlay',
                    'confirm' => '[data-testid="confirm-order"]',
                ],
            ]),
        ],
        [
            'company_name'             => 'Company C',
            'portal_url'               => 'http://localhost/mini-automation/portals/company-c/',
            'login_url'                => 'http://localhost/mini-automation/portals/company-c/login.php',
            'form_url'                 => 'http://localhost/mini-automation/portals/company-c/form.php',
            'username'                 => 'admin',
            'password'                 => 'CompanyC#123',
            'login_type'               => 'spinner',
            'portal_type'              => 'multistep',
            'login_username_selector'  => 'input[name="username"]',
            'login_password_selector'  => 'input[name="password"]',
            'login_submit_selector'    => 'button.login-submit',
            'form_submit_selector'     => '[data-testid="final-submit"]',
            'form_success_url'         => '**/generate.php',
            'download_selector'        => '#download-btn',
            'extra_config'             => json_encode([
                'steps' => [
                    ['step' => 1, 'panel' => "[data-testid='step-1']", 'next' => "[data-testid='next-step-1']"],
                    ['step' => 2, 'panel' => "[data-testid='step-2']", 'next' => "[data-testid='next-step-2']"],
                    ['step' => 3, 'panel' => "[data-testid='step-3']", 'submit' => true],
                ],
                'terms_selector' => "[data-testid='terms-checkbox']",
            ]),
        ],
    ];

    $cStmt = $pdo->prepare("
        INSERT INTO companies (company_name, portal_url, login_url, form_url, username, password,
            login_type, portal_type, login_username_selector, login_password_selector,
            login_submit_selector, form_submit_selector, form_success_url,
            download_selector, extra_config, status)
        VALUES (:company_name,:portal_url,:login_url,:form_url,:username,:password,
            :login_type,:portal_type,:login_username_selector,:login_password_selector,
            :login_submit_selector,:form_submit_selector,:form_success_url,
            :download_selector,:extra_config,'active')
    ");
    foreach ($companies as $c) $cStmt->execute($c);
    $log[] = "✅ Companies seeded (3)";

    // ── Field mappings ──────────────────────────────────────
    $pdo->exec("DELETE FROM field_mappings");

    $mappings = [
        // ── Company A — simple normal selects ───────────────
        ['Company A', 'part_no',        '#part_no',        'select',   'normal_select',      1, null,    null,                                   1, 1],
        ['Company A', 'quantity',        '#quantity',       'number',   null,                 1, null,    null,                                   1, 2],
        ['Company A', 'batch_no',        '#batch_no',       'text',     null,                 1, null,    null,                                   1, 3],
        ['Company A', 'vendor_code',     '#vendor_code',    'select',   'normal_select',      1, null,    null,                                   1, 4],
        ['Company A', 'delivery_date',   '#delivery_date',  'date',     null,                 0, null,    null,                                   1, 5],
        ['Company A', 'notes',           '#notes',          'textarea', null,                 0, null,    null,                                   1, 6],
        ['Company A', 'upload_file_1_path', '[data-testid="upload-file-1"]', 'file_upload', null, 0, null, null, 1, 7],
        ['Company A', 'upload_file_2_path', '[data-testid="upload-file-2"]', 'file_upload', null, 0, null, null, 1, 8],
        ['Company A', 'upload_file_3_path', '[data-testid="upload-file-3"]', 'file_upload', null, 0, null, null, 1, 9],

        // ── Company B — searchable + ajax + modal ────────────
        ['Company B', 'part_no', '', 'searchable_dropdown', 'searchable_dropdown', 1, null,
            json_encode(['search_input' => "[data-testid='part-search-input']", 'option' => "[data-testid='part-option']", 'hidden' => '#hidden-part-no']),
            1, 1],
        ['Company B', 'quantity',    '#quantity', 'number', null, 1, null, null, 1, 2],
        ['Company B', 'batch_no',    '#batch_no', 'text',   null, 1, null, null, 1, 3],
        ['Company B', 'vendor_code', "[data-testid='vendor-select']", 'select', 'ajax_select', 1, null,
            json_encode(['loading' => '#vendor-loading']),
            1, 4],
        ['Company B', 'upload_file_1_path', '[data-testid="upload-file-1"]', 'file_upload', null, 0, null, null, 1, 5],
        ['Company B', 'upload_file_2_path', '[data-testid="upload-file-2"]', 'file_upload', null, 0, null, null, 1, 6],
        ['Company B', 'upload_file_3_path', '[data-testid="upload-file-3"]', 'file_upload', null, 0, null, null, 1, 7],

        // ── Company C — react dropdown + multistep + multi-select ──
        // category derives from part_no prefix: "GER-041" → prefix "GER" → value_map → "MECH"
        ['Company C', 'category', '', 'react_dropdown', 'react_dropdown', 1, null,
            json_encode([
                'control'    => '#cat-control', 'menu'   => '#cat-menu',
                'search'     => '#cat-search',  'option' => "[data-testid='category-option']",
                'attr'       => 'data-id',      'hidden' => '#hid-category',
                'derive_from'=> 'part_no',  'transform' => 'split_prefix', 'separator' => '-',
                'value_map'  => [
                    'ENG'=>'AUTO','TRN'=>'AUTO','BRK'=>'AUTO','STR'=>'AUTO','FUL'=>'AUTO',
                    'ALT'=>'ELEC','SPK'=>'ELEC','ECU'=>'ELEC','SEN'=>'ELEC','IGN'=>'ELEC',
                    'PMP'=>'HYDR','CYL'=>'HYDR','VLV'=>'HYDR','FLT'=>'HYDR',
                    'BRG'=>'MECH','GER'=>'MECH','CHN'=>'MECH','BLT'=>'MECH','CPL'=>'MECH',
                    'SFV'=>'SAFE','EMG'=>'SAFE','GRD'=>'SAFE','SHL'=>'SAFE',
                ],
            ]),
            1, 1],
        ['Company C', 'part_no', '#sub-select', 'select', 'dependent_select', 1, null,
            json_encode(['wait_selector' => '#sub-select.loaded']),
            1, 2],
        ['Company C', 'batch_no',   "[data-testid='batch-input']",    'text',   null, 1, null, null, 1, 3],
        ['Company C', 'quantity',   "[data-testid='quantity-input']", 'number', null, 1, null, null, 2, 1],
        ['Company C', 'delivery_date', "[data-testid='delivery-date']", 'date', null, 0, null, null, 2, 2],
        ['Company C', 'vendor_code', '', 'multi_select', 'multi_select', 1, null,
            json_encode(['option' => "[data-testid='vendor-option']", 'attr' => 'data-code']),
            3, 1],
        ['Company C', 'upload_file_1_path', '[data-testid="upload-file-1"]', 'file_upload', null, 0, null, null, 3, 2],
        ['Company C', 'upload_file_2_path', '[data-testid="upload-file-2"]', 'file_upload', null, 0, null, null, 3, 3],
        ['Company C', 'upload_file_3_path', '[data-testid="upload-file-3"]', 'file_upload', null, 0, null, null, 3, 4],
    ];

    $mStmt = $pdo->prepare("
        INSERT INTO field_mappings (company_name, field_key, selector, field_type, dropdown_type, required,
            fallback_selector, extra_config, step_no, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ");
    foreach ($mappings as $m) $mStmt->execute($m);
    $log[] = "✅ Field mappings seeded (" . count($mappings) . " rows)";

    // ── Output ─────────────────────────────────────────────
    echo '<pre style="font-family:monospace;background:#f0fff4;border:1px solid #68d391;padding:24px;border-radius:10px;max-width:700px;margin:40px auto;line-height:1.8">';
    echo "✅  Schema Updated Successfully!\n\n";
    foreach ($log as $l) echo "$l\n";
    echo "\n📋 Next Steps:\n";
    echo "  1. Upload sample_jobs.csv from dashboard Upload page\n";
    echo "  2. Run bot: cd bot && python bot.py\n";
    echo "  3. Watch dashboard auto-refresh every 3s\n";
    echo '</pre>';

} catch (PDOException $e) {
    echo '<pre style="color:red;padding:20px;font-family:monospace">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '<pre>' . implode("\n", $log) . '</pre>';
}

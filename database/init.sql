-- ============================================================
-- Barcode Portal Automation System — Complete Database Schema
-- Version: 2.0 (merged base + all migrations)
-- ============================================================
--
-- DOCKER (automatic):
--   MySQL container runs this file automatically on first start.
--   It is mounted into /docker-entrypoint-initdb.d/init.sql.
--   The MYSQL_DATABASE env var selects the target database.
--   No manual steps needed — just: docker compose up -d --build
--
-- MANUAL (XAMPP / bare-metal):
--   Create the database first, then run:
--     mysql -u root -p barcode_portal < database/init.sql
--
-- RESET (Docker — wipes all data):
--   docker compose down -v
--   docker compose up -d --build
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET foreign_key_checks = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';


-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `users` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `name`       VARCHAR(100)                        NOT NULL,
    `email`      VARCHAR(150)                        NOT NULL UNIQUE,
    `password`   VARCHAR(255)                        NOT NULL,
    `role`       ENUM('admin','operator')            DEFAULT 'operator',
    `status`     ENUM('active','inactive')           DEFAULT 'active',
    `created_at` DATETIME                            DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin — password: admin123
-- Regenerate hash: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
INSERT IGNORE INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES
(
    'Admin User',
    'admin@portal.com',
    '$2y$10$KIBKBCPBOQHnAiM3GN6FxOFBjE8qGJzW1J0UyBQoZ6ZPmKoE0Di0S',
    'admin',
    'active'
);


-- ============================================================
-- COMPANIES
-- Includes all columns added by migrations.
-- Portal URLs use http://nginx/portals/... (Docker internal hostname).
-- ============================================================
CREATE TABLE IF NOT EXISTS `companies` (
    `id`                       INT AUTO_INCREMENT PRIMARY KEY,
    `company_name`             VARCHAR(150)                  NOT NULL,
    `portal_url`               VARCHAR(300)                  NOT NULL,
    `login_url`                VARCHAR(300)                  NOT NULL,
    `form_url`                 VARCHAR(300)                  NULL,
    `username`                 VARCHAR(100)                  NOT NULL,
    `password`                 VARCHAR(100)                  NOT NULL,
    `login_type`               VARCHAR(30)                   DEFAULT 'simple',
    `portal_type`              VARCHAR(30)                   DEFAULT 'simple',
    `login_username_selector`  VARCHAR(100)                  DEFAULT '#username',
    `login_password_selector`  VARCHAR(100)                  DEFAULT '#password',
    `login_submit_selector`    VARCHAR(100)                  DEFAULT '#login-btn',
    `form_submit_selector`     VARCHAR(100)                  DEFAULT '#submit-btn',
    `form_success_url`         VARCHAR(200)                  DEFAULT '**/generate.php',
    `download_selector`        VARCHAR(100)                  DEFAULT '#download-btn',
    `extra_config`             TEXT                          NULL,
    `status`                   ENUM('active','inactive')     DEFAULT 'active',
    `created_at`               DATETIME                      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `companies`
    (`company_name`,`portal_url`,`login_url`,`form_url`,
     `username`,`password`,
     `login_type`,`portal_type`,
     `login_username_selector`,`login_password_selector`,`login_submit_selector`,
     `form_submit_selector`,`form_success_url`,`download_selector`,
     `extra_config`,`status`)
VALUES
-- ── Company A — simple form, normal selects ───────────────
(
    'Company A',
    'http://nginx/portals/company-a/',
    'http://nginx/portals/company-a/login.php',
    'http://nginx/portals/company-a/form.php',
    'operator_a', 'pass_a123',
    'simple', 'simple',
    '#username', '#password', '#login-btn',
    '#submit-btn', '**/generate.php', '#download-btn',
    NULL, 'active'
),
-- ── Company B — searchable dropdown, AJAX vendor, modal submit ─
(
    'Company B',
    'http://nginx/portals/company-b/',
    'http://nginx/portals/company-b/login.php',
    'http://nginx/portals/company-b/form.php',
    'b.operator@company.com', 'Bpass@2024',
    'simple', 'modal',
    '#email', '#password', '#login-btn',
    '[data-testid="submit-form"]', '**/generate.php', '#download-btn',
    '{"modal":{"overlay":".modal-overlay","confirm":"[data-testid=\\"confirm-order\\"]"}}',
    'active'
),
-- ── Company C — multi-step form, React dropdowns ──────────
(
    'Company C',
    'http://nginx/portals/company-c/',
    'http://nginx/portals/company-c/login.php',
    'http://nginx/portals/company-c/form.php',
    'admin', 'CompanyC#123',
    'spinner', 'multistep',
    'input[name="username"]', 'input[name="password"]', 'button.login-submit',
    '[data-testid="final-submit"]', '**/generate.php', '#download-btn',
    '{"steps":[{"step":1,"panel":"[data-testid=\\"step-1\\"]","next":"[data-testid=\\"next-step-1\\"]"},{"step":2,"panel":"[data-testid=\\"step-2\\"]","next":"[data-testid=\\"next-step-2\\"]"},{"step":3,"panel":"[data-testid=\\"step-3\\"]","submit":true}],"terms_selector":"[data-testid=\\"terms-checkbox\\"]"}',
    'active'
);


-- ============================================================
-- BARCODE JOBS
-- Includes all columns from base schema + all migrations.
-- ============================================================
CREATE TABLE IF NOT EXISTS `barcode_jobs` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `company_name`          VARCHAR(150)                                          NOT NULL,
    `part_no`               VARCHAR(100)                                          NOT NULL,
    `quantity`              INT                                                   NOT NULL,
    `batch_no`              VARCHAR(100)                                          NOT NULL,
    `vendor_code`           VARCHAR(100)                                          NOT NULL,
    `delivery_date`         DATE                                                  NULL,
    `priority`              VARCHAR(20)                                           DEFAULT 'normal',
    `notes`                 TEXT                                                  NULL,
    -- Status
    `status`                ENUM('pending','processing','success','failed','retry') DEFAULT 'pending',
    `attempt_count`         INT                                                   DEFAULT 0,
    `error_message`         TEXT                                                  NULL,
    -- Output files
    `barcode_file_path`     VARCHAR(500)                                          NULL,
    `screenshot_path`       VARCHAR(500)                                          NULL,
    -- Upload slots (filename + resolved server path)
    `upload_file_1_name`    VARCHAR(255)                                          NULL,
    `upload_file_1_path`    VARCHAR(500)                                          NULL,
    `upload_file_2_name`    VARCHAR(255)                                          NULL,
    `upload_file_2_path`    VARCHAR(500)                                          NULL,
    `upload_file_3_name`    VARCHAR(255)                                          NULL,
    `upload_file_3_path`    VARCHAR(500)                                          NULL,
    -- Bot error tracking (populated by save-bot-error.php)
    `processing_error`      TEXT                                                  NULL,
    `bot_error_field`       VARCHAR(100)                                          NULL,
    `bot_error_type`        VARCHAR(50)                                           NULL,
    `bot_error_step`        VARCHAR(50)                                           NULL,
    `bot_excel_value`       VARCHAR(255)                                          NULL,
    `bot_portal_error_message` TEXT                                               NULL,
    `failed_at`             DATETIME                                              NULL,
    -- Timestamps
    `created_at`            DATETIME                                              DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            DATETIME                                              DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- ACTIVITY LOGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `job_id`     INT          NULL,
    `action`     VARCHAR(100) NOT NULL,
    `message`    TEXT         NOT NULL,
    `created_at` DATETIME     DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_log_job`
        FOREIGN KEY (`job_id`) REFERENCES `barcode_jobs` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BOT ERROR LOGS
-- One row per field/step error detected during bot execution.
-- ============================================================
CREATE TABLE IF NOT EXISTS `bot_error_logs` (
    `id`                   INT AUTO_INCREMENT PRIMARY KEY,
    `job_id`               INT          NOT NULL,
    `company_name`         VARCHAR(100) NOT NULL DEFAULT '',
    `step_name`            VARCHAR(50)  NULL,
    `field_key`            VARCHAR(100) NULL,
    `excel_value`          TEXT         NULL,
    `portal_error_message` TEXT         NULL,
    `error_type`           VARCHAR(50)  NOT NULL DEFAULT 'unknown_error',
    `selector`             VARCHAR(300) NULL,
    `screenshot_path`      VARCHAR(500) NULL,
    `page_url`             VARCHAR(500) NULL,
    `created_at`           DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_job_id`     (`job_id`),
    INDEX `idx_company`    (`company_name`),
    INDEX `idx_error_type` (`error_type`),
    INDEX `idx_field_key`  (`field_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- FIELD MAPPINGS
-- Drives the bot's form-fill logic — one row per portal field.
-- ============================================================
CREATE TABLE IF NOT EXISTS `field_mappings` (
    `id`                INT AUTO_INCREMENT PRIMARY KEY,
    `company_name`      VARCHAR(100) NOT NULL,
    `field_key`         VARCHAR(50)  NOT NULL,
    `selector`          VARCHAR(300) DEFAULT '',
    `field_type`        VARCHAR(50)  NOT NULL,
    `dropdown_type`     VARCHAR(50)  NULL,
    `required`          TINYINT      DEFAULT 1,
    `fallback_selector` VARCHAR(300) NULL,
    `extra_config`      TEXT         NULL,
    `step_no`           INT          DEFAULT 1,
    `sort_order`        INT          DEFAULT 0,
    `created_at`        DATETIME     DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_company` (`company_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `field_mappings`
    (`company_name`,`field_key`,`selector`,`field_type`,`dropdown_type`,
     `required`,`fallback_selector`,`extra_config`,`step_no`,`sort_order`)
VALUES

-- ── Company A ────────────────────────────────────────────────
('Company A','part_no',         '#part_no',        'select',   'normal_select', 1, NULL, NULL, 1, 1),
('Company A','quantity',        '#quantity',        'number',   NULL,            1, NULL, NULL, 1, 2),
('Company A','batch_no',        '#batch_no',        'text',     NULL,            1, NULL, NULL, 1, 3),
('Company A','vendor_code',     '#vendor_code',     'select',   'normal_select', 1, NULL, NULL, 1, 4),
('Company A','delivery_date',   '#delivery_date',   'date',     NULL,            0, NULL, NULL, 1, 5),
('Company A','notes',           '#notes',           'textarea', NULL,            0, NULL, NULL, 1, 6),
('Company A','upload_file_1_path','[data-testid="upload-file-1"]','file_upload',NULL,0,NULL,NULL,1,7),
('Company A','upload_file_2_path','[data-testid="upload-file-2"]','file_upload',NULL,0,NULL,NULL,1,8),
('Company A','upload_file_3_path','[data-testid="upload-file-3"]','file_upload',NULL,0,NULL,NULL,1,9),

-- ── Company B ────────────────────────────────────────────────
('Company B','part_no','','searchable_dropdown','searchable_dropdown',1,NULL,
    '{"search_input":"[data-testid=\'part-search-input\']","option":"[data-testid=\'part-option\']","hidden":"#hidden-part-no"}',
    1,1),
('Company B','quantity',    '#quantity',                       'number', NULL,         1, NULL, NULL, 1, 2),
('Company B','batch_no',    '#batch_no',                       'text',   NULL,         1, NULL, NULL, 1, 3),
('Company B','vendor_code', '[data-testid="vendor-select"]',   'select', 'ajax_select',1, NULL,
    '{"loading":"#vendor-loading"}',
    1,4),
('Company B','upload_file_1_path','[data-testid="upload-file-1"]','file_upload',NULL,0,NULL,NULL,1,5),
('Company B','upload_file_2_path','[data-testid="upload-file-2"]','file_upload',NULL,0,NULL,NULL,1,6),
('Company B','upload_file_3_path','[data-testid="upload-file-3"]','file_upload',NULL,0,NULL,NULL,1,7),

-- ── Company C — step 1 ────────────────────────────────────────
('Company C','category','','react_dropdown','react_dropdown',1,NULL,
    '{"control":"#cat-control","menu":"#cat-menu","search":"#cat-search","option":"[data-testid=\'category-option\']","attr":"data-id","hidden":"#hid-category","derive_from":"part_no","transform":"split_prefix","separator":"-","value_map":{"ENG":"AUTO","TRN":"AUTO","BRK":"AUTO","STR":"AUTO","FUL":"AUTO","ALT":"ELEC","SPK":"ELEC","ECU":"ELEC","SEN":"ELEC","IGN":"ELEC","PMP":"HYDR","CYL":"HYDR","VLV":"HYDR","FLT":"HYDR","BRG":"MECH","GER":"MECH","CHN":"MECH","BLT":"MECH","CPL":"MECH","SFV":"SAFE","EMG":"SAFE","GRD":"SAFE","SHL":"SAFE"}}',
    1,1),
('Company C','part_no',      '#sub-select',                  'select','dependent_select',1,NULL,
    '{"wait_selector":"#sub-select.loaded"}',
    1,2),
('Company C','batch_no',     '[data-testid="batch-input"]',   'text',  NULL,1,NULL,NULL,1,3),

-- ── Company C — step 2 ────────────────────────────────────────
('Company C','quantity',     '[data-testid="quantity-input"]','number',NULL,1,NULL,NULL,2,1),
('Company C','delivery_date','[data-testid="delivery-date"]', 'date',  NULL,0,NULL,NULL,2,2),

-- ── Company C — step 3 ────────────────────────────────────────
('Company C','vendor_code','','multi_select','multi_select',1,NULL,
    '{"option":"[data-testid=\'vendor-option\']","attr":"data-code"}',
    3,1),
('Company C','upload_file_1_path','[data-testid="upload-file-1"]','file_upload',NULL,0,NULL,NULL,3,2),
('Company C','upload_file_2_path','[data-testid="upload-file-2"]','file_upload',NULL,0,NULL,NULL,3,3),
('Company C','upload_file_3_path','[data-testid="upload-file-3"]','file_upload',NULL,0,NULL,NULL,3,4);


SET foreign_key_checks = 1;

-- ============================================================
-- Multi-Company Barcode Portal Automation System
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS barcode_portal;
USE barcode_portal;

-- ============================================================
-- USERS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    email       VARCHAR(150) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('admin','operator') DEFAULT 'operator',
    status      ENUM('active','inactive') DEFAULT 'active',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Default admin user (password: admin123)
-- Hash generated via: password_hash('admin123', PASSWORD_BCRYPT)
-- NOTE: If login fails, use backend/init.php instead — it generates the hash live via PHP.
INSERT INTO users (name, email, password, role, status) VALUES
('Admin User', 'admin@portal.com', '$2y$10$KIBKBCPBOQHnAiM3GN6FxOFBjE8qGJzW1J0UyBQoZ6ZPmKoE0Di0S', 'admin', 'active');

-- ============================================================
-- COMPANIES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS companies (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(150) NOT NULL,
    portal_url   VARCHAR(300) NOT NULL,
    login_url    VARCHAR(300) NOT NULL,
    username     VARCHAR(100) NOT NULL,
    password     VARCHAR(100) NOT NULL,
    status       ENUM('active','inactive') DEFAULT 'active',
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Demo company pointing at our dummy portal
INSERT INTO companies (company_name, portal_url, login_url, username, password, status) VALUES
('Demo Company', 'http://localhost/mini-automation/dummy-portal/', 'http://localhost/mini-automation/dummy-portal/login.php', 'admin', '123456', 'active');

-- ============================================================
-- BARCODE JOBS TABLE
-- ============================================================
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
);

-- ============================================================
-- ACTIVITY LOGS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS activity_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    job_id     INT,
    action     VARCHAR(100) NOT NULL,
    message    TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES barcode_jobs(id) ON DELETE SET NULL
);

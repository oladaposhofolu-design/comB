-- ============================================================
-- MIST Attendance Management System — Database Schema
-- Database name (local/XAMPP): mist_attendance
-- Database name (InfinityFree, prefixed): if0_42306968_mist_attendance
--
-- This schema is reconstructed from the tables and columns
-- referenced throughout the PHP codebase (db.php, login.php,
-- mark_attendance.php, get_records.php, update_pin.php,
-- verify_pin.php, dashboard.php). Run this FIRST on a fresh
-- database before running add_pin_column.sql and
-- add_unique_check.sql (or simply use this file alone, since
-- the pin column and index are already included below).
-- ============================================================

CREATE DATABASE IF NOT EXISTS mist_attendance;
USE mist_attendance;

-- ------------------------------------------------------------
-- Table: students
-- Stores each SIWES student's identity, allowed attendance
-- days, and their 4-digit manual-attendance PIN.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    student_code  VARCHAR(20)  NOT NULL UNIQUE,   -- e.g. MIST001
    full_name     VARCHAR(150) NOT NULL,
    allowed_days  VARCHAR(100) NOT NULL,           -- e.g. Monday,Wednesday,Friday
    pin           VARCHAR(10)  DEFAULT NULL,       -- 4-digit PIN for manual attendance
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Table: attendance
-- One row per student per day. full_name + attendance_date
-- is used as the logical "one attendance per day" key,
-- enforced in PHP (mark_attendance.php) and sped up by the
-- index added below.
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS attendance (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    student_code     VARCHAR(20)  NOT NULL,
    full_name        VARCHAR(150) NOT NULL,
    allowed_days     VARCHAR(100),
    status           VARCHAR(20)  NOT NULL,        -- ALLOWED or DENIED
    attendance_day   VARCHAR(20),                  -- e.g. Monday
    attendance_date  DATE         NOT NULL,
    attendance_time  TIME         NOT NULL,
    source           VARCHAR(20)  DEFAULT 'qr',    -- 'qr' or 'manual'
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Speeds up the "already marked today" duplicate check
-- performed in mark_attendance.php on every submission.
ALTER TABLE attendance ADD INDEX idx_name_date (full_name, attendance_date);

-- ------------------------------------------------------------
-- Table: admins
-- Stores admin login credentials. Passwords are hashed with
-- MD5 in login.php (see code explanation document for a note
-- on upgrading this to password_hash() for stronger security).
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(32)  NOT NULL,   -- MD5 hash (32 hex characters)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ------------------------------------------------------------
-- Sample admin account (username: admin / password: admin123)
-- CHANGE THIS PASSWORD before using in production.
-- MD5('admin123') = 0192023a7bbd73250516f069df18b500
-- ------------------------------------------------------------
INSERT INTO admins (username, password)
VALUES ('admin', '0192023a7bbd73250516f069df18b500')
ON DUPLICATE KEY UPDATE username = username;

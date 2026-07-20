-- ============================================================
-- webauthn_credentials.sql
-- Stores one row per enrolled fingerprint/Face ID credential.
-- A student could in theory enroll more than one device, so
-- this is a separate table rather than columns on `students`.
-- Run this on the same database as the rest of the schema.
-- ============================================================

CREATE TABLE IF NOT EXISTS webauthn_credentials (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    student_code     VARCHAR(20)   NOT NULL,
    credential_id    VARCHAR(255)  NOT NULL UNIQUE,  -- base64url-encoded credential ID
    public_key_pem   TEXT          NOT NULL,          -- PEM-encoded P-256 public key
    sign_count       INT UNSIGNED  NOT NULL DEFAULT 0,
    device_label     VARCHAR(100)  DEFAULT NULL,       -- optional, e.g. "Pelumi's Phone"
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_code) REFERENCES students(student_code)
        ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_webauthn_student ON webauthn_credentials (student_code);

-- AtteCert Pending Migrations (003 through 012)
-- Run this in phpMyAdmin if you cannot access SSH or cPanel Terminal.
-- These apply all updates for case tracking, simple case workflow, quotations, and OTP verification.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 003_mail_outbox.sql
CREATE TABLE IF NOT EXISTS mail_outbox (
    email_log_id BIGINT UNSIGNED PRIMARY KEY,
    payload MEDIUMTEXT NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 3,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claim_token CHAR(48) NULL,
    claimed_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    finished_at DATETIME NULL,
    KEY idx_mail_outbox_available (available_at, email_log_id),
    CONSTRAINT fk_mail_outbox_log FOREIGN KEY (email_log_id) REFERENCES email_logs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 004_quote_request_assignments.sql
ALTER TABLE quote_requests
    ADD COLUMN assigned_to BIGINT UNSIGNED NULL,
    ADD KEY idx_quote_requests_assignee (assigned_to, status, created_at),
    ADD CONSTRAINT fk_quote_requests_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL;

-- 005_uploaded_quotations.sql
ALTER TABLE quotations
    ADD COLUMN uploaded_document_id BIGINT UNSIGNED NULL,
    ADD UNIQUE KEY idx_quotation_uploaded_document (uploaded_document_id),
    ADD CONSTRAINT fk_quotation_uploaded_document FOREIGN KEY (uploaded_document_id) REFERENCES documents (id);

-- 006_case_tracking.sql
ALTER TABLE cases
    ADD COLUMN tracking_expected_on DATE NULL,
    ADD COLUMN tracking_message VARCHAR(600) NULL;

CREATE TABLE IF NOT EXISTS case_tracking_steps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    public_description VARCHAR(1000) NULL,
    step_date DATE NULL,
    state VARCHAR(20) NOT NULL DEFAULT 'pending',
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tracking_steps_case (case_id, sort_order, id),
    CONSTRAINT fk_tracking_steps_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_tracking_steps_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_tracking_steps_updater FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 007_request_quotations.sql
ALTER TABLE quotations
    ADD COLUMN quote_request_id BIGINT UNSIGNED NULL,
    ADD KEY idx_quotation_request (quote_request_id, id),
    ADD CONSTRAINT fk_quotation_request FOREIGN KEY (quote_request_id) REFERENCES quote_requests (id) ON DELETE SET NULL;

UPDATE quotations q INNER JOIN cases c ON c.id = q.case_id
    SET q.quote_request_id = c.quote_request_id
    WHERE q.quote_request_id IS NULL AND c.quote_request_id IS NOT NULL;

-- 008_customer_otp_sms.sql
ALTER TABLE users
    ADD COLUMN phone_verified_at DATETIME NULL,
    ADD COLUMN verified_phone VARCHAR(32) NULL;

CREATE TABLE IF NOT EXISTS sms_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(32) NULL,
    contact_name VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL,
    error_code VARCHAR(80) NULL,
    related_type VARCHAR(40) NULL,
    related_id BIGINT UNSIGNED NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    claim_token VARCHAR(64) NULL,
    claimed_at DATETIME NULL,
    expires_at DATETIME NULL,
    campaign_id VARCHAR(80) NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sms_queue (status, id),
    KEY idx_sms_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(10) NOT NULL,
    destination VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    password_fingerprint VARCHAR(64) NOT NULL,
    attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_otp_user (user_id, created_at),
    CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 009_otp_verification_proof.sql
ALTER TABLE users
    ADD COLUMN otp_verified_at DATETIME NULL,
    ADD COLUMN otp_verified_channel VARCHAR(10) NULL,
    ADD COLUMN otp_verified_destination VARCHAR(190) NULL;

-- 010_simple_case_workflow.sql
ALTER TABLE cases ADD COLUMN custom_status VARCHAR(120) NULL;
ALTER TABLE case_status_history ADD COLUMN custom_status VARCHAR(120) NULL;

ALTER TABLE payments
    MODIFY COLUMN invoice_id BIGINT UNSIGNED NULL,
    ADD COLUMN confirmed_at DATETIME NULL,
    ADD COLUMN confirmed_by BIGINT UNSIGNED NULL,
    ADD COLUMN request_key CHAR(32) NULL,
    ADD UNIQUE KEY uq_payment_request (request_key),
    ADD CONSTRAINT fk_payment_confirmer FOREIGN KEY (confirmed_by) REFERENCES users (id) ON DELETE SET NULL;

UPDATE payments SET confirmed_at = created_at, confirmed_by = recorded_by WHERE status IN ('completed', 'refunded');

-- 011_step_documents_payment_receipts.sql
CREATE TABLE IF NOT EXISTS case_progress_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    step_id BIGINT UNSIGNED NULL,
    status_history_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(30) NOT NULL,
    title VARCHAR(200) NOT NULL,
    public_description VARCHAR(1000) NULL,
    state VARCHAR(20) NOT NULL,
    step_date DATE NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_case_progress (case_id, created_at, id),
    UNIQUE KEY uq_progress_status (status_history_id),
    CONSTRAINT fk_progress_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_progress_step FOREIGN KEY (step_id) REFERENCES case_tracking_steps (id) ON DELETE SET NULL,
    CONSTRAINT fk_progress_status FOREIGN KEY (status_history_id) REFERENCES case_status_history (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO case_progress_history (case_id, status_history_id, event_type, title, state, step_date, created_at)
SELECT h.case_id, h.id, 'status_updated', COALESCE(h.custom_status, t.name, s.code), 'status', DATE(h.created_at), h.created_at
FROM case_status_history h INNER JOIN workflow_statuses s ON s.id = h.status_id
LEFT JOIN workflow_status_translations t ON t.status_id = s.id AND t.locale = 'en'
WHERE h.customer_visible = 1;

INSERT INTO case_progress_history (case_id, step_id, event_type, title, public_description, state, step_date, created_at)
SELECT case_id, id, 'step_updated', title, public_description, state, step_date, updated_at FROM case_tracking_steps;

ALTER TABLE documents ADD COLUMN tracking_step_id BIGINT UNSIGNED NULL,
    ADD KEY idx_document_tracking_step (tracking_step_id),
    ADD CONSTRAINT fk_document_tracking_step FOREIGN KEY (tracking_step_id) REFERENCES case_tracking_steps (id) ON DELETE SET NULL;

ALTER TABLE payments ADD COLUMN receipt_document_id BIGINT UNSIGNED NULL,
    ADD CONSTRAINT fk_payment_receipt_document FOREIGN KEY (receipt_document_id) REFERENCES documents (id) ON DELETE RESTRICT;

-- 012_public_action_otps.sql
CREATE TABLE IF NOT EXISTS public_action_otps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_hash CHAR(64) NOT NULL,
    purpose VARCHAR(12) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    channel VARCHAR(8) NOT NULL,
    destination VARCHAR(190) NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    verified_at DATETIME NULL,
    grant_expires_at DATETIME NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_public_otp_session (session_hash, purpose, created_at),
    KEY idx_public_otp_subject (subject_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update schema_migrations table
CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(80) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO schema_migrations (version) VALUES
('001_schema.sql'),
('002_contact_accounts.sql'),
('003_mail_outbox.sql'),
('004_quote_request_assignments.sql'),
('005_uploaded_quotations.sql'),
('006_case_tracking.sql'),
('007_request_quotations.sql'),
('008_customer_otp_sms.sql'),
('009_otp_verification_proof.sql'),
('010_simple_case_workflow.sql'),
('011_step_documents_payment_receipts.sql'),
('012_public_action_otps.sql');

SET FOREIGN_KEY_CHECKS = 1;


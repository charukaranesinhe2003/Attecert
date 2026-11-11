CREATE TABLE public_action_otps (
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

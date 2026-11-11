ALTER TABLE users
    ADD COLUMN phone_verified_at DATETIME NULL,
    ADD COLUMN verified_phone VARCHAR(32) NULL;

CREATE TABLE sms_messages (
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

CREATE TABLE customer_otps (
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

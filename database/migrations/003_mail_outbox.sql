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

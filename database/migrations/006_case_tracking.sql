ALTER TABLE cases
    ADD COLUMN tracking_expected_on DATE NULL,
    ADD COLUMN tracking_message VARCHAR(600) NULL;

CREATE TABLE case_tracking_steps (
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

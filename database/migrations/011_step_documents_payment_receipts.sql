CREATE TABLE case_progress_history (
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

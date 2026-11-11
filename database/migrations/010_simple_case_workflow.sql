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

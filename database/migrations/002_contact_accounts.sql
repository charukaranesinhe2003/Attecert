ALTER TABLE contact_messages
    ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'guest' AFTER status,
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER source,
    ADD COLUMN customer_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD KEY idx_contact_source (source, created_at),
    ADD CONSTRAINT fk_contact_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_contact_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL;

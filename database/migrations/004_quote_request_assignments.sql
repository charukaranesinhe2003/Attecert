ALTER TABLE quote_requests
    ADD COLUMN assigned_to BIGINT UNSIGNED NULL,
    ADD KEY idx_quote_requests_assignee (assigned_to, status, created_at),
    ADD CONSTRAINT fk_quote_requests_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL;

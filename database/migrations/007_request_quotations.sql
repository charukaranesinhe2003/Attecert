ALTER TABLE quotations
    ADD COLUMN quote_request_id BIGINT UNSIGNED NULL,
    ADD KEY idx_quotation_request (quote_request_id, id),
    ADD CONSTRAINT fk_quotation_request FOREIGN KEY (quote_request_id) REFERENCES quote_requests (id) ON DELETE SET NULL;

UPDATE quotations q INNER JOIN cases c ON c.id = q.case_id
    SET q.quote_request_id = c.quote_request_id
    WHERE q.quote_request_id IS NULL AND c.quote_request_id IS NOT NULL;

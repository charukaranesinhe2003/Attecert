ALTER TABLE quotations
    ADD COLUMN uploaded_document_id BIGINT UNSIGNED NULL,
    ADD UNIQUE KEY idx_quotation_uploaded_document (uploaded_document_id),
    ADD CONSTRAINT fk_quotation_uploaded_document FOREIGN KEY (uploaded_document_id) REFERENCES documents (id);

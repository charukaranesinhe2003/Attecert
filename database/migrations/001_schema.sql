-- AtteCert platform schema. MySQL 8+ / utf8mb4.

CREATE TABLE roles (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    UNIQUE KEY uq_roles_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    UNIQUE KEY uq_permissions_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id SMALLINT UNSIGNED NOT NULL,
    permission_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    password_hash VARCHAR(255) NOT NULL,
    locale CHAR(5) NOT NULL DEFAULT 'en',
    email_verified_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    last_login_at DATETIME NULL,
    last_login_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_active (is_active, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id BIGINT UNSIGNED NOT NULL,
    role_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    whatsapp VARCHAR(40) NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(80) NULL,
    notes TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_customers_user (user_id),
    KEY idx_customers_email (email),
    KEY idx_customers_phone (phone),
    KEY idx_customers_name (name),
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    purpose VARCHAR(20) NOT NULL DEFAULT 'reset',
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_password_resets_email (email),
    KEY idx_password_resets_token (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_attempts_lookup (email, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    action_key VARCHAR(120) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME NOT NULL,
    UNIQUE KEY uq_rate_limits (action_key, ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sequences (
    name VARCHAR(40) NOT NULL,
    year SMALLINT UNSIGNED NOT NULL,
    current_value INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (name, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_statuses (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    customer_visible TINYINT(1) NOT NULL DEFAULT 1,
    is_terminal TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    badge_tone VARCHAR(20) NOT NULL DEFAULT 'neutral',
    UNIQUE KEY uq_workflow_code (code),
    KEY idx_workflow_sort (sort_order, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE workflow_status_translations (
    status_id SMALLINT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) NULL,
    PRIMARY KEY (status_id, locale),
    CONSTRAINT fk_wst_status FOREIGN KEY (status_id) REFERENCES workflow_statuses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    pricing_type ENUM('fixed', 'custom') NOT NULL DEFAULT 'custom',
    fixed_price DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    processing_days VARCHAR(40) NULL,
    category VARCHAR(60) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    show_price TINYINT(1) NOT NULL DEFAULT 0,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    icon VARCHAR(40) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_services_code (code),
    UNIQUE KEY uq_services_slug (slug),
    KEY idx_services_public (is_active, is_public, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_translations (
    service_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    name VARCHAR(160) NOT NULL,
    summary VARCHAR(300) NOT NULL,
    description TEXT NULL,
    process_text TEXT NULL,
    documents_required TEXT NULL,
    seo_title VARCHAR(180) NULL,
    meta_description VARCHAR(300) NULL,
    PRIMARY KEY (service_id, locale),
    CONSTRAINT fk_service_tr FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE countries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code CHAR(2) NOT NULL,
    slug VARCHAR(80) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_countries_code (code),
    UNIQUE KEY uq_countries_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE country_translations (
    country_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    name VARCHAR(120) NOT NULL,
    summary VARCHAR(300) NULL,
    requirements TEXT NULL,
    documents_required TEXT NULL,
    process_text TEXT NULL,
    notes TEXT NULL,
    processing_info VARCHAR(180) NULL,
    seo_title VARCHAR(180) NULL,
    meta_description VARCHAR(300) NULL,
    PRIMARY KEY (country_id, locale),
    CONSTRAINT fk_country_tr FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE country_services (
    country_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    price_override DECIMAL(12,2) NULL,
    notes VARCHAR(255) NULL,
    PRIMARY KEY (country_id, service_id),
    CONSTRAINT fk_cs_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE,
    CONSTRAINT fk_cs_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_types (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE KEY uq_document_types_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_type_translations (
    document_type_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    name VARCHAR(160) NOT NULL,
    PRIMARY KEY (document_type_id, locale),
    CONSTRAINT fk_dtt FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cases (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(24) NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    country_id INT UNSIGNED NULL,
    document_type_id INT UNSIGNED NULL,
    document_title VARCHAR(180) NOT NULL,
    summary TEXT NULL,
    status_id SMALLINT UNSIGNED NOT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'normal',
    assigned_to BIGINT UNSIGNED NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'admin',
    quote_request_id BIGINT UNSIGNED NULL,
    estimated_next VARCHAR(180) NULL,
    internal_notes TEXT NULL,
    customer_notes TEXT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    created_by BIGINT UNSIGNED NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_cases_number (number),
    KEY idx_cases_customer (customer_id, created_at),
    KEY idx_cases_status (status_id, deleted_at),
    KEY idx_cases_country (country_id),
    KEY idx_cases_created (created_at),
    CONSTRAINT fk_cases_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_cases_country FOREIGN KEY (country_id) REFERENCES countries (id),
    CONSTRAINT fk_cases_doctype FOREIGN KEY (document_type_id) REFERENCES document_types (id),
    CONSTRAINT fk_cases_status FOREIGN KEY (status_id) REFERENCES workflow_statuses (id),
    CONSTRAINT fk_cases_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_cases_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_services (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    pricing_type ENUM('fixed', 'custom') NOT NULL,
    unit_price DECIMAL(12,2) NULL,
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    KEY idx_case_services_case (case_id),
    CONSTRAINT fk_case_services_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_case_services_service FOREIGN KEY (service_id) REFERENCES services (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE case_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    status_id SMALLINT UNSIGNED NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    customer_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_case_history_case (case_id, created_at),
    CONSTRAINT fk_csh_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_csh_status FOREIGN KEY (status_id) REFERENCES workflow_statuses (id),
    CONSTRAINT fk_csh_staff FOREIGN KEY (staff_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    whatsapp VARCHAR(40) NULL,
    country_id INT UNSIGNED NULL,
    document_type_id INT UNSIGNED NULL,
    document_count INT UNSIGNED NOT NULL DEFAULT 1,
    message TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    converted_case_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_quote_requests_status (status, created_at),
    KEY idx_quote_requests_email (email),
    CONSTRAINT fk_qr_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL,
    CONSTRAINT fk_qr_doctype FOREIGN KEY (document_type_id) REFERENCES document_types (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_request_services (
    quote_request_id BIGINT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (quote_request_id, service_id),
    CONSTRAINT fk_qrs_request FOREIGN KEY (quote_request_id) REFERENCES quote_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_qrs_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cases
    ADD CONSTRAINT fk_cases_quote_request FOREIGN KEY (quote_request_id) REFERENCES quote_requests (id) ON DELETE SET NULL;

ALTER TABLE quote_requests
    ADD CONSTRAINT fk_qr_case FOREIGN KEY (converted_case_id) REFERENCES cases (id) ON DELETE SET NULL;

CREATE TABLE quotations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(24) NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    additional_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    valid_until DATE NULL,
    notes TEXT NULL,
    terms TEXT NULL,
    issued_at DATETIME NULL,
    sent_at DATETIME NULL,
    viewed_at DATETIME NULL,
    accepted_at DATETIME NULL,
    rejected_at DATETIME NULL,
    rejection_reason VARCHAR(500) NULL,
    accepted_ip VARCHAR(45) NULL,
    accepted_by BIGINT UNSIGNED NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quotations_number (number),
    KEY idx_quotations_case (case_id, version),
    KEY idx_quotations_customer (customer_id, status),
    KEY idx_quotations_status (status, created_at),
    CONSTRAINT fk_quotations_case FOREIGN KEY (case_id) REFERENCES cases (id),
    CONSTRAINT fk_quotations_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_quotations_parent FOREIGN KEY (parent_id) REFERENCES quotations (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_quotations_acceptor FOREIGN KEY (accepted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id BIGINT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pricing_type VARCHAR(20) NOT NULL DEFAULT 'fixed',
    KEY idx_qi_quotation (quotation_id),
    CONSTRAINT fk_qi_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE,
    CONSTRAINT fk_qi_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotation_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_id BIGINT UNSIGNED NOT NULL,
    version SMALLINT UNSIGNED NOT NULL,
    snapshot JSON NOT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_qv (quotation_id, version),
    CONSTRAINT fk_qv_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(24) NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    quotation_id BIGINT UNSIGNED NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    additional_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_due DECIMAL(12,2) GENERATED ALWAYS AS (total - amount_paid) STORED,
    issued_at DATETIME NULL,
    due_at DATE NULL,
    sent_at DATETIME NULL,
    notes TEXT NULL,
    terms TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    cancelled_at DATETIME NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_invoices_number (number),
    KEY idx_invoices_case (case_id),
    KEY idx_invoices_customer (customer_id, status),
    KEY idx_invoices_status (status, due_at),
    CONSTRAINT fk_invoices_case FOREIGN KEY (case_id) REFERENCES cases (id),
    CONSTRAINT fk_invoices_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_invoices_quotation FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id BIGINT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(8,2) NOT NULL DEFAULT 1.00,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    KEY idx_ii_invoice (invoice_id),
    CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_ii_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    number VARCHAR(24) NOT NULL,
    invoice_id BIGINT UNSIGNED NOT NULL,
    case_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'LKR',
    method VARCHAR(40) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'completed',
    paid_at DATE NOT NULL,
    reference VARCHAR(80) NULL,
    receipt_reference VARCHAR(80) NULL,
    notes TEXT NULL,
    recorded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payments_number (number),
    KEY idx_payments_invoice (invoice_id),
    KEY idx_payments_case (case_id),
    KEY idx_payments_paid (paid_at),
    CONSTRAINT fk_payments_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id),
    CONSTRAINT fk_payments_case FOREIGN KEY (case_id) REFERENCES cases (id),
    CONSTRAINT fk_payments_customer FOREIGN KEY (customer_id) REFERENCES customers (id),
    CONSTRAINT fk_payments_user FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NULL,
    quote_request_id BIGINT UNSIGNED NULL,
    category VARCHAR(30) NOT NULL,
    subcategory VARCHAR(40) NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(180) NOT NULL,
    mime VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    description VARCHAR(255) NULL,
    customer_visible TINYINT(1) NOT NULL DEFAULT 0,
    version SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_documents_case (case_id, category, deleted_at),
    KEY idx_documents_request (quote_request_id),
    CONSTRAINT fk_documents_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_request FOREIGN KEY (quote_request_id) REFERENCES quote_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_documents_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_download_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_download_document (document_id, created_at),
    CONSTRAINT fk_ddl_document FOREIGN KEY (document_id) REFERENCES documents (id) ON DELETE CASCADE,
    CONSTRAINT fk_ddl_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mofa_processes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    reference_no VARCHAR(80) NULL,
    submitted_at DATE NULL,
    expected_at DATE NULL,
    completed_at DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'not_started',
    notes TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_mofa_case (case_id),
    KEY idx_mofa_status (status),
    CONSTRAINT fk_mofa_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_mofa_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE embassy_processes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id BIGINT UNSIGNED NOT NULL,
    embassy_name VARCHAR(160) NOT NULL,
    country_id INT UNSIGNED NULL,
    reference_no VARCHAR(80) NULL,
    submitted_at DATE NULL,
    expected_at DATE NULL,
    completed_at DATE NULL,
    status VARCHAR(40) NOT NULL DEFAULT 'not_started',
    notes TEXT NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_embassy_case (case_id),
    KEY idx_embassy_status (status),
    CONSTRAINT fk_embassy_case FOREIGN KEY (case_id) REFERENCES cases (id) ON DELETE CASCADE,
    CONSTRAINT fk_embassy_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE SET NULL,
    CONSTRAINT fk_embassy_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(500) NULL,
    link VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notifications_user (user_id, read_at, created_at),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(190) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    template VARCHAR(80) NULL,
    status VARCHAR(30) NOT NULL,
    error_message VARCHAR(500) NULL,
    related_type VARCHAR(40) NULL,
    related_id BIGINT UNSIGNED NULL,
    body_path VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    KEY idx_email_logs_related (related_type, related_id),
    KEY idx_email_logs_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(80) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    show_on_home TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faq_translations (
    faq_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    PRIMARY KEY (faq_id, locale),
    CONSTRAINT fk_faq_tr FOREIGN KEY (faq_id) REFERENCES faqs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(160) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    featured TINYINT(1) NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NULL,
    author_id BIGINT UNSIGNED NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_articles_slug (slug),
    KEY idx_articles_public (status, published_at),
    CONSTRAINT fk_articles_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE article_translations (
    article_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    title VARCHAR(200) NOT NULL,
    excerpt VARCHAR(400) NULL,
    content MEDIUMTEXT NULL,
    seo_title VARCHAR(180) NULL,
    meta_description VARCHAR(300) NULL,
    PRIMARY KEY (article_id, locale),
    CONSTRAINT fk_article_tr FOREIGN KEY (article_id) REFERENCES articles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_blocks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    block_key VARCHAR(80) NOT NULL,
    group_name VARCHAR(40) NOT NULL DEFAULT 'home',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_content_key (block_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_block_translations (
    block_id INT UNSIGNED NOT NULL,
    locale CHAR(5) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (block_id, locale),
    CONSTRAINT fk_cbt FOREIGN KEY (block_id) REFERENCES content_blocks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ui_translations (
    locale CHAR(5) NOT NULL,
    trans_key VARCHAR(160) NOT NULL,
    value TEXT NOT NULL,
    PRIMARY KEY (locale, trans_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    subject VARCHAR(180) NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_contact_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    entity VARCHAR(40) NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_audit_entity (entity, entity_id),
    KEY idx_audit_user (user_id, created_at),
    KEY idx_audit_action (action, created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

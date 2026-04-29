CREATE TABLE IF NOT EXISTS document_compliance_validations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    generated_document_id BIGINT UNSIGNED NOT NULL,
    generated_document_version INT UNSIGNED NOT NULL,
    is_compliant TINYINT(1) NOT NULL DEFAULT 0,
    critical_count INT UNSIGNED NOT NULL DEFAULT 0,
    major_count INT UNSIGNED NOT NULL DEFAULT 0,
    minor_count INT UNSIGNED NOT NULL DEFAULT 0,
    non_conformities_json JSON NOT NULL,
    created_at TIMESTAMP NULL,
    KEY idx_doc_compliance_document (generated_document_id, generated_document_version),
    KEY idx_doc_compliance_severity (is_compliant, critical_count),
    CONSTRAINT fk_doc_compliance_document FOREIGN KEY (generated_document_id) REFERENCES generated_documents(id) ON DELETE CASCADE
);

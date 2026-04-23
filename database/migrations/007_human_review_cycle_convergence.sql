ALTER TABLE human_review_queue
    ADD COLUMN IF NOT EXISTS generated_document_id BIGINT UNSIGNED NULL AFTER order_id,
    ADD COLUMN IF NOT EXISTS generated_document_version INT NULL AFTER generated_document_id,
    ADD INDEX IF NOT EXISTS idx_hrq_order_document (order_id, generated_document_id);

ALTER TABLE revisions
    ADD COLUMN IF NOT EXISTS generated_document_id BIGINT UNSIGNED NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS generated_document_version INT NULL AFTER generated_document_id;

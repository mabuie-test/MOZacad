ALTER TABLE revisions
    ADD INDEX IF NOT EXISTS idx_revisions_order_id (order_id),
    ADD INDEX IF NOT EXISTS idx_revisions_user_id (user_id),
    ADD INDEX IF NOT EXISTS idx_revisions_order_document (order_id, generated_document_id);

SET @has_fk_revisions_order := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'revisions'
      AND CONSTRAINT_NAME = 'fk_revisions_order'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_revisions_order := IF(
    @has_fk_revisions_order = 0,
    'ALTER TABLE revisions ADD CONSTRAINT fk_revisions_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_revisions_order FROM @sql_fk_revisions_order;
EXECUTE stmt_fk_revisions_order;
DEALLOCATE PREPARE stmt_fk_revisions_order;

SET @has_fk_revisions_user := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'revisions'
      AND CONSTRAINT_NAME = 'fk_revisions_user'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_revisions_user := IF(
    @has_fk_revisions_user = 0,
    'ALTER TABLE revisions ADD CONSTRAINT fk_revisions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_revisions_user FROM @sql_fk_revisions_user;
EXECUTE stmt_fk_revisions_user;
DEALLOCATE PREPARE stmt_fk_revisions_user;

SET @has_fk_revisions_generated_document := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'revisions'
      AND CONSTRAINT_NAME = 'fk_revisions_generated_document'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_revisions_generated_document := IF(
    @has_fk_revisions_generated_document = 0,
    'ALTER TABLE revisions ADD CONSTRAINT fk_revisions_generated_document FOREIGN KEY (generated_document_id) REFERENCES generated_documents(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_revisions_generated_document FROM @sql_fk_revisions_generated_document;
EXECUTE stmt_fk_revisions_generated_document;
DEALLOCATE PREPARE stmt_fk_revisions_generated_document;

SET @has_fk_hrq_generated_document := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'human_review_queue'
      AND CONSTRAINT_NAME = 'fk_hrq_generated_document'
      AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_hrq_generated_document := IF(
    @has_fk_hrq_generated_document = 0,
    'ALTER TABLE human_review_queue ADD CONSTRAINT fk_hrq_generated_document FOREIGN KEY (generated_document_id) REFERENCES generated_documents(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_hrq_generated_document FROM @sql_fk_hrq_generated_document;
EXECUTE stmt_fk_hrq_generated_document;
DEALLOCATE PREPARE stmt_fk_hrq_generated_document;

CREATE TABLE IF NOT EXISTS template_artifacts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  institution_id BIGINT UNSIGNED NOT NULL,
  work_type_id BIGINT UNSIGNED NULL,
  artifact_type VARCHAR(40) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  published_by_user_id BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NULL,
  INDEX idx_template_artifacts_lookup (institution_id, work_type_id, artifact_type, is_active),
  INDEX idx_template_artifacts_actor (published_by_user_id),
  CONSTRAINT fk_template_artifacts_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_artifacts_work_type FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE CASCADE,
  CONSTRAINT fk_template_artifacts_actor FOREIGN KEY (published_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @idx_templates := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND INDEX_NAME = 'idx_templates_institution_work_type'
);
SET @sql_idx_templates := IF(@idx_templates = 0,
  'ALTER TABLE templates ADD INDEX idx_templates_institution_work_type (institution_id, work_type_id)',
  'SELECT 1'
);
PREPARE stmt_idx_templates FROM @sql_idx_templates; EXECUTE stmt_idx_templates; DEALLOCATE PREPARE stmt_idx_templates;

SET @fk_templates_institution := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND CONSTRAINT_NAME = 'fk_templates_institution' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_templates_institution := IF(@fk_templates_institution = 0,
  'ALTER TABLE templates ADD CONSTRAINT fk_templates_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_templates_institution FROM @sql_fk_templates_institution; EXECUTE stmt_fk_templates_institution; DEALLOCATE PREPARE stmt_fk_templates_institution;

SET @fk_templates_work_type := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'templates' AND CONSTRAINT_NAME = 'fk_templates_work_type' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_templates_work_type := IF(@fk_templates_work_type = 0,
  'ALTER TABLE templates ADD CONSTRAINT fk_templates_work_type FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_templates_work_type FROM @sql_fk_templates_work_type; EXECUTE stmt_fk_templates_work_type; DEALLOCATE PREPARE stmt_fk_templates_work_type;

DELETE orq FROM order_requirements orq LEFT JOIN orders o ON o.id = orq.order_id WHERE o.id IS NULL;
SET @fk_order_requirements := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'order_requirements' AND CONSTRAINT_NAME = 'fk_order_requirements_order' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_order_requirements := IF(@fk_order_requirements = 0,
  'ALTER TABLE order_requirements ADD CONSTRAINT fk_order_requirements_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_order_requirements FROM @sql_fk_order_requirements; EXECUTE stmt_fk_order_requirements; DEALLOCATE PREPARE stmt_fk_order_requirements;

DELETE oa FROM order_attachments oa LEFT JOIN orders o ON o.id = oa.order_id WHERE o.id IS NULL;
SET @fk_order_attachments := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'order_attachments' AND CONSTRAINT_NAME = 'fk_order_attachments_order' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_order_attachments := IF(@fk_order_attachments = 0,
  'ALTER TABLE order_attachments ADD CONSTRAINT fk_order_attachments_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_order_attachments FROM @sql_fk_order_attachments; EXECUTE stmt_fk_order_attachments; DEALLOCATE PREPARE stmt_fk_order_attachments;

DELETE dt FROM debito_transactions dt LEFT JOIN payments p ON p.id = dt.payment_id WHERE dt.payment_id IS NOT NULL AND p.id IS NULL;
SET @fk_debito_transactions_payment := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'debito_transactions' AND CONSTRAINT_NAME = 'fk_debito_transactions_payment' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_debito_transactions_payment := IF(@fk_debito_transactions_payment = 0,
  'ALTER TABLE debito_transactions ADD CONSTRAINT fk_debito_transactions_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE stmt_fk_debito_transactions_payment FROM @sql_fk_debito_transactions_payment; EXECUTE stmt_fk_debito_transactions_payment; DEALLOCATE PREPARE stmt_fk_debito_transactions_payment;

SET @fk_institution_rules := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_rules' AND CONSTRAINT_NAME = 'fk_institution_rules_institution' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_institution_rules := IF(@fk_institution_rules = 0,
  'ALTER TABLE institution_rules ADD CONSTRAINT fk_institution_rules_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_institution_rules FROM @sql_fk_institution_rules; EXECUTE stmt_fk_institution_rules; DEALLOCATE PREPARE stmt_fk_institution_rules;

SET @fk_iwtr_institution := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_work_type_rules' AND CONSTRAINT_NAME = 'fk_iwtr_institution' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_iwtr_institution := IF(@fk_iwtr_institution = 0,
  'ALTER TABLE institution_work_type_rules ADD CONSTRAINT fk_iwtr_institution FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_iwtr_institution FROM @sql_fk_iwtr_institution; EXECUTE stmt_fk_iwtr_institution; DEALLOCATE PREPARE stmt_fk_iwtr_institution;

SET @fk_iwtr_work_type := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'institution_work_type_rules' AND CONSTRAINT_NAME = 'fk_iwtr_work_type' AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);
SET @sql_fk_iwtr_work_type := IF(@fk_iwtr_work_type = 0,
  'ALTER TABLE institution_work_type_rules ADD CONSTRAINT fk_iwtr_work_type FOREIGN KEY (work_type_id) REFERENCES work_types(id) ON DELETE CASCADE',
  'SELECT 1'
);
PREPARE stmt_fk_iwtr_work_type FROM @sql_fk_iwtr_work_type; EXECUTE stmt_fk_iwtr_work_type; DEALLOCATE PREPARE stmt_fk_iwtr_work_type;

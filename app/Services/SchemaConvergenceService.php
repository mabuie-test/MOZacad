<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class SchemaConvergenceService
{
    /**
     * @return array{applied_repairs:array<int,string>,issues:array<int,string>}
     */
    public function enforce(PDO $db, bool $applyRepairs = true): array
    {
        $repairs = [];
        $issues = [];

        $ddl = [
            "ALTER TABLE institutions ADD COLUMN IF NOT EXISTS slug VARCHAR(120) NOT NULL AFTER short_name",
            "ALTER TABLE institutions ADD UNIQUE KEY IF NOT EXISTS uq_institutions_slug (slug)",
            "ALTER TABLE payments ADD UNIQUE KEY IF NOT EXISTS uq_payments_provider_external_reference (provider, external_reference)",
            "ALTER TABLE payments ADD INDEX IF NOT EXISTS idx_payments_order_status_updated (order_id, status, updated_at)",
            "ALTER TABLE invoices ADD INDEX IF NOT EXISTS idx_invoices_order_status (order_id, status)",
            "ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS reservation_token VARCHAR(64) NULL AFTER error_text",
            "ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS reserved_at DATETIME NULL AFTER reservation_token",
            "ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS processing_started_at DATETIME NULL AFTER reserved_at",
            "ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS attempts INT NOT NULL DEFAULT 0 AFTER processing_started_at",
            "ALTER TABLE ai_jobs ADD COLUMN IF NOT EXISTS next_retry_at DATETIME NULL AFTER attempts",
            "ALTER TABLE ai_jobs ADD INDEX IF NOT EXISTS idx_ai_jobs_status_created (status, next_retry_at, created_at)",
            "ALTER TABLE ai_jobs ADD INDEX IF NOT EXISTS idx_ai_jobs_reservation_token (reservation_token)",
            "ALTER TABLE ai_jobs ADD INDEX IF NOT EXISTS idx_ai_jobs_processing_started (processing_started_at)",
            "ALTER TABLE debito_transactions MODIFY COLUMN payment_id BIGINT UNSIGNED NULL",
            "ALTER TABLE generated_documents ADD UNIQUE KEY IF NOT EXISTS uq_generated_documents_order_version (order_id, version)",
            "ALTER TABLE generated_documents ADD INDEX IF NOT EXISTS idx_generated_documents_order_status_version (order_id, status, version)",
            "ALTER TABLE human_review_queue ADD COLUMN IF NOT EXISTS generated_document_id BIGINT UNSIGNED NULL AFTER order_id",
            "ALTER TABLE human_review_queue ADD COLUMN IF NOT EXISTS generated_document_version INT NULL AFTER generated_document_id",
            "ALTER TABLE human_review_queue ADD INDEX IF NOT EXISTS idx_hrq_order_status_updated (order_id, status, updated_at)",
            "ALTER TABLE human_review_queue ADD INDEX IF NOT EXISTS idx_hrq_order_document (order_id, generated_document_id)",
            "ALTER TABLE revisions ADD COLUMN IF NOT EXISTS generated_document_id BIGINT UNSIGNED NULL AFTER user_id",
            "ALTER TABLE revisions ADD COLUMN IF NOT EXISTS generated_document_version INT NULL AFTER generated_document_id",
            "ALTER TABLE revisions ADD INDEX IF NOT EXISTS idx_revisions_order_id (order_id)",
            "ALTER TABLE revisions ADD INDEX IF NOT EXISTS idx_revisions_user_id (user_id)",
            "ALTER TABLE revisions ADD INDEX IF NOT EXISTS idx_revisions_order_document (order_id, generated_document_id)",
            "CREATE TABLE IF NOT EXISTS coupon_usage_logs (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              order_id BIGINT UNSIGNED NOT NULL,
              coupon_id BIGINT UNSIGNED NOT NULL,
              user_id BIGINT UNSIGNED NULL,
              coupon_code VARCHAR(50) NOT NULL,
              created_at TIMESTAMP NULL,
              UNIQUE KEY uq_coupon_usage_order_coupon (order_id, coupon_id),
              INDEX idx_coupon_usage_coupon (coupon_id),
              INDEX idx_coupon_usage_user (user_id),
              CONSTRAINT fk_coupon_usage_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
              CONSTRAINT fk_coupon_usage_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
              CONSTRAINT fk_coupon_usage_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )",

            "CREATE TABLE IF NOT EXISTS template_artifacts (
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
            )",

            "CREATE TABLE IF NOT EXISTS auth_login_attempts (
              id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              email VARCHAR(190) NOT NULL,
              ip_address VARCHAR(64) NOT NULL,
              failed_attempts INT NOT NULL DEFAULT 0,
              last_failed_at DATETIME NULL,
              locked_until DATETIME NULL,
              created_at TIMESTAMP NULL,
              updated_at TIMESTAMP NULL,
              UNIQUE KEY uq_auth_login_attempts_email_ip (email, ip_address),
              INDEX idx_auth_login_attempts_locked_until (locked_until)
            )",
        ];

        if ($applyRepairs) {
            foreach ($ddl as $statement) {
                $db->exec($statement);
                $repairs[] = $statement;
            }

            $this->ensureForeignKey($db, 'human_review_queue', 'fk_hrq_generated_document', 'generated_document_id', 'generated_documents', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'revisions', 'fk_revisions_order', 'order_id', 'orders', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'revisions', 'fk_revisions_user', 'user_id', 'users', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'revisions', 'fk_revisions_generated_document', 'generated_document_id', 'generated_documents', 'id', 'SET NULL');
            $this->ensureForeignKey($db, 'templates', 'fk_templates_institution', 'institution_id', 'institutions', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'templates', 'fk_templates_work_type', 'work_type_id', 'work_types', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'order_requirements', 'fk_order_requirements_order', 'order_id', 'orders', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'order_attachments', 'fk_order_attachments_order', 'order_id', 'orders', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'debito_transactions', 'fk_debito_transactions_payment', 'payment_id', 'payments', 'id', 'SET NULL');
            $this->ensureForeignKey($db, 'institution_rules', 'fk_institution_rules_institution', 'institution_id', 'institutions', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'institution_work_type_rules', 'fk_iwtr_institution', 'institution_id', 'institutions', 'id', 'CASCADE');
            $this->ensureForeignKey($db, 'institution_work_type_rules', 'fk_iwtr_work_type', 'work_type_id', 'work_types', 'id', 'CASCADE');
        }

        $checks = [
            ['table' => 'institutions', 'column' => 'slug'],
            ['table' => 'ai_jobs', 'column' => 'reservation_token'],
            ['table' => 'ai_jobs', 'column' => 'attempts'],
            ['table' => 'ai_jobs', 'column' => 'next_retry_at'],
            ['table' => 'human_review_queue', 'column' => 'generated_document_id'],
            ['table' => 'human_review_queue', 'column' => 'generated_document_version'],
            ['table' => 'revisions', 'column' => 'generated_document_id'],
            ['table' => 'revisions', 'column' => 'generated_document_version'],
            ['table' => 'template_artifacts', 'column' => 'artifact_type'],
            ['table' => 'auth_login_attempts', 'column' => 'email'],
            ['table' => 'auth_login_attempts', 'column' => 'locked_until'],
        ];
        foreach ($checks as $check) {
            if (!$this->columnExists($db, $check['table'], $check['column'])) {
                $issues[] = sprintf('Coluna ausente: %s.%s', $check['table'], $check['column']);
            }
        }

        $indexChecks = [
            ['table' => 'payments', 'index' => 'uq_payments_provider_external_reference'],
            ['table' => 'human_review_queue', 'index' => 'idx_hrq_order_document'],
            ['table' => 'generated_documents', 'index' => 'uq_generated_documents_order_version'],
            ['table' => 'ai_jobs', 'index' => 'idx_ai_jobs_status_created'],
            ['table' => 'revisions', 'index' => 'idx_revisions_order_document'],
            ['table' => 'template_artifacts', 'index' => 'idx_template_artifacts_lookup'],
            ['table' => 'auth_login_attempts', 'index' => 'uq_auth_login_attempts_email_ip'],
        ];
        foreach ($indexChecks as $check) {
            if (!$this->indexExists($db, $check['table'], $check['index'])) {
                $issues[] = sprintf('Índice ausente: %s.%s', $check['table'], $check['index']);
            }
        }

        return ['applied_repairs' => $repairs, 'issues' => $issues];
    }

    private function ensureForeignKey(PDO $db, string $table, string $constraintName, string $column, string $refTable, string $refColumn, string $onDelete): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND CONSTRAINT_NAME = :constraint_name AND CONSTRAINT_TYPE = "FOREIGN KEY"');
        $stmt->execute(['table_name' => $table, 'constraint_name' => $constraintName]);
        if ((int) $stmt->fetchColumn() > 0) {
            return;
        }

        $sql = sprintf(
            'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s(%s) ON DELETE %s',
            $table,
            $constraintName,
            $column,
            $refTable,
            $refColumn,
            $onDelete
        );
        $db->exec($sql);
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name');
        $stmt->execute(['table_name' => $table, 'column_name' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(PDO $db, string $table, string $index): bool
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name');
        $stmt->execute(['table_name' => $table, 'index_name' => $index]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

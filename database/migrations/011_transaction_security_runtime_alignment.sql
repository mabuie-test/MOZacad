ALTER TABLE debito_transactions
    MODIFY COLUMN payment_id BIGINT UNSIGNED NULL;

ALTER TABLE ai_jobs
    ADD COLUMN IF NOT EXISTS next_retry_at DATETIME NULL AFTER attempts;

ALTER TABLE ai_jobs
    DROP INDEX IF EXISTS idx_ai_jobs_status_created,
    ADD INDEX idx_ai_jobs_status_created (status, next_retry_at, created_at);

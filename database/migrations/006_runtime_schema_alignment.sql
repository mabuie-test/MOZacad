ALTER TABLE ai_jobs
    ADD COLUMN IF NOT EXISTS reservation_token VARCHAR(64) NULL AFTER error_text,
    ADD COLUMN IF NOT EXISTS reserved_at DATETIME NULL AFTER reservation_token,
    ADD COLUMN IF NOT EXISTS processing_started_at DATETIME NULL AFTER reserved_at,
    ADD COLUMN IF NOT EXISTS attempts INT NOT NULL DEFAULT 0 AFTER processing_started_at;

ALTER TABLE ai_jobs
    DROP INDEX IF EXISTS idx_ai_jobs_status_created,
    DROP INDEX IF EXISTS idx_ai_jobs_reservation_token,
    DROP INDEX IF EXISTS idx_ai_jobs_processing_started,
    ADD INDEX idx_ai_jobs_status_created (status, created_at),
    ADD INDEX idx_ai_jobs_reservation_token (reservation_token),
    ADD INDEX idx_ai_jobs_processing_started (processing_started_at);

CREATE TABLE IF NOT EXISTS coupon_usage_logs (
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
);

ALTER TABLE payments
    DROP INDEX IF EXISTS uq_payments_provider_external_reference,
    ADD UNIQUE KEY uq_payments_provider_external_reference (provider, external_reference);

ALTER TABLE debito_transactions
    DROP INDEX IF EXISTS uq_debito_transactions_payment,
    DROP INDEX IF EXISTS uq_debito_transactions_reference,
    ADD UNIQUE KEY uq_debito_transactions_payment (payment_id),
    ADD UNIQUE KEY uq_debito_transactions_reference (debito_reference);

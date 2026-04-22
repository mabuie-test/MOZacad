ALTER TABLE ai_jobs
    ADD COLUMN reservation_token VARCHAR(64) NULL AFTER error_text,
    ADD COLUMN reserved_at DATETIME NULL AFTER reservation_token,
    ADD COLUMN processing_started_at DATETIME NULL AFTER reserved_at,
    ADD COLUMN attempts INT NOT NULL DEFAULT 0 AFTER processing_started_at;

ALTER TABLE ai_jobs
    ADD INDEX idx_ai_jobs_status_created (status, created_at),
    ADD INDEX idx_ai_jobs_reservation_token (reservation_token),
    ADD INDEX idx_ai_jobs_processing_started (processing_started_at);

ALTER TABLE coupons
    ADD INDEX idx_coupons_active_window (is_active, starts_at, ends_at),
    ADD INDEX idx_coupons_usage_limit (usage_limit, used_count);

ALTER TABLE user_discounts
    ADD INDEX idx_user_discounts_window (is_active, starts_at, ends_at, usage_limit, used_count);


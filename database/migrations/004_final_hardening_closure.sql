CREATE TABLE coupon_usage_logs (
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

ALTER TABLE discount_usage_logs
    ADD UNIQUE KEY uq_discount_usage_discount_order (user_discount_id, order_id),
    ADD INDEX idx_discount_usage_order (order_id),
    ADD INDEX idx_discount_usage_user (user_id);

ALTER TABLE generated_documents
    ADD INDEX idx_generated_documents_order_status_version (order_id, status, version);

ALTER TABLE human_review_queue
    ADD INDEX idx_hrq_order_status (order_id, status);

ALTER TABLE payment_status_logs
    ADD INDEX idx_payment_status_logs_payment_created (payment_id, created_at),
    ADD INDEX idx_payment_status_logs_status (status);

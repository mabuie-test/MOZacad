CREATE TABLE IF NOT EXISTS post_payment_exceptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NULL,
  review_queue_id BIGINT UNSIGNED NULL,
  category VARCHAR(40) NOT NULL,
  state VARCHAR(40) NOT NULL DEFAULT 'open',
  owner_user_id BIGINT UNSIGNED NULL,
  sla_due_at DATETIME NULL,
  escalation_level TINYINT UNSIGNED NOT NULL DEFAULT 0,
  blocked_delivery TINYINT(1) NOT NULL DEFAULT 0,
  resolution_code VARCHAR(60) NULL,
  resolution_notes TEXT NULL,
  auto_reconciled TINYINT(1) NOT NULL DEFAULT 0,
  resolved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_ppe_state_sla (state, sla_due_at),
  INDEX idx_ppe_owner_state (owner_user_id, state),
  INDEX idx_ppe_order_payment (order_id, payment_id),
  CONSTRAINT fk_ppe_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppe_payment FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL,
  CONSTRAINT fk_ppe_queue FOREIGN KEY (review_queue_id) REFERENCES human_review_queue(id) ON DELETE SET NULL,
  CONSTRAINT fk_ppe_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS post_payment_exception_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exception_id BIGINT UNSIGNED NOT NULL,
  actor_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  payload_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ppee_exception_created (exception_id, created_at),
  CONSTRAINT fk_ppee_exception FOREIGN KEY (exception_id) REFERENCES post_payment_exceptions(id) ON DELETE CASCADE,
  CONSTRAINT fk_ppee_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);

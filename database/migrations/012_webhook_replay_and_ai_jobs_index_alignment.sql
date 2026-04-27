CREATE TABLE IF NOT EXISTS webhook_replay_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  event_key VARCHAR(190) NOT NULL,
  signature_hash CHAR(64) NOT NULL,
  payload_hash CHAR(64) NOT NULL,
  event_timestamp DATETIME NULL,
  received_at DATETIME NOT NULL,
  first_seen_at DATETIME NOT NULL,
  last_seen_at DATETIME NOT NULL,
  hit_count INT UNSIGNED NOT NULL DEFAULT 1,
  expires_at DATETIME NULL,
  created_at TIMESTAMP NULL,
  UNIQUE KEY uq_webhook_replay_provider_event (provider, event_key),
  INDEX idx_webhook_replay_expires (expires_at),
  INDEX idx_webhook_replay_provider_received (provider, received_at),
  INDEX idx_webhook_replay_provider_last_seen (provider, last_seen_at),
  INDEX idx_webhook_replay_provider_hits (provider, hit_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ai_jobs
    DROP INDEX IF EXISTS idx_ai_jobs_status_created,
    ADD INDEX idx_ai_jobs_status_created (status, next_retry_at, created_at);

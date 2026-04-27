ALTER TABLE webhook_replay_events
    ADD COLUMN IF NOT EXISTS event_timestamp DATETIME NULL AFTER payload_hash,
    ADD COLUMN IF NOT EXISTS first_seen_at DATETIME NULL AFTER received_at,
    ADD COLUMN IF NOT EXISTS last_seen_at DATETIME NULL AFTER first_seen_at,
    ADD COLUMN IF NOT EXISTS hit_count INT UNSIGNED NOT NULL DEFAULT 1 AFTER last_seen_at;

UPDATE webhook_replay_events
SET first_seen_at = COALESCE(first_seen_at, received_at, NOW()),
    last_seen_at = COALESCE(last_seen_at, received_at, NOW()),
    hit_count = CASE WHEN hit_count IS NULL OR hit_count < 1 THEN 1 ELSE hit_count END;

ALTER TABLE webhook_replay_events
    MODIFY COLUMN first_seen_at DATETIME NOT NULL,
    MODIFY COLUMN last_seen_at DATETIME NOT NULL;

ALTER TABLE webhook_replay_events
    ADD INDEX IF NOT EXISTS idx_webhook_replay_provider_last_seen (provider, last_seen_at),
    ADD INDEX IF NOT EXISTS idx_webhook_replay_provider_hits (provider, hit_count);

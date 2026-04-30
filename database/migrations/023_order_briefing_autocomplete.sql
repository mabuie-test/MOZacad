ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS briefing_autocompleted TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS briefing_autocompleted_at DATETIME NULL,
  ADD COLUMN IF NOT EXISTS briefing_autocomplete_provider VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS briefing_autocomplete_confidence VARCHAR(20) NULL,
  ADD COLUMN IF NOT EXISTS briefing_original_json JSON NULL;

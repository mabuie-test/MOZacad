ALTER TABLE debito_transactions
ADD COLUMN IF NOT EXISTS api_version VARCHAR(10) NULL AFTER wallet_id,
ADD COLUMN IF NOT EXISTS wallet_code VARCHAR(20) NULL AFTER api_version,
ADD COLUMN IF NOT EXISTS provider_payment_id VARCHAR(100) NULL AFTER debito_reference,
ADD COLUMN IF NOT EXISTS provider_reference VARCHAR(100) NULL AFTER provider_payment_id;

CREATE INDEX IF NOT EXISTS idx_debito_transactions_provider_payment_id
ON debito_transactions (provider_payment_id);

CREATE INDEX IF NOT EXISTS idx_debito_transactions_wallet_code
ON debito_transactions (wallet_code);

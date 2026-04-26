<?php

return [
    'base_url' => $_ENV['DEBITO_BASE_URL'] ?? 'https://my.debito.co.mz',
    'wallet_id' => $_ENV['DEBITO_WALLET_ID'] ?? '',
    'token' => $_ENV['DEBITO_TOKEN'] ?? '',
    'callback_url' => $_ENV['DEBITO_CALLBACK_URL'] ?? null,
    'webhook_path' => $_ENV['DEBITO_WEBHOOK_PATH'] ?? '/webhooks/debito',
    'timeout' => (int) ($_ENV['DEBITO_TIMEOUT'] ?? 30),
    'http_retries' => (int) ($_ENV['DEBITO_HTTP_RETRIES'] ?? 2),
    'http_backoff_ms' => (int) ($_ENV['DEBITO_HTTP_BACKOFF_MS'] ?? 500),
    'currency' => $_ENV['DEBITO_CURRENCY'] ?? 'MZN',
    'polling_enabled' => filter_var($_ENV['DEBITO_POLLING_ENABLED'] ?? false, FILTER_VALIDATE_BOOL),
    'webhook_secret' => $_ENV['DEBITO_WEBHOOK_SECRET'] ?? '',
    'allow_unsigned_webhook_local' => filter_var($_ENV['DEBITO_ALLOW_UNSIGNED_WEBHOOK_LOCAL'] ?? true, FILTER_VALIDATE_BOOL),
];

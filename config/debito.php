<?php

return [
    'base_url' => $_ENV['DEBITO_BASE_URL'] ?? 'http://localhost:9000',
    'wallet_id' => $_ENV['DEBITO_WALLET_ID'] ?? '',
    'token' => $_ENV['DEBITO_TOKEN'] ?? '',
    'callback_url' => $_ENV['DEBITO_CALLBACK_URL'] ?? null,
    'enable_webhook' => filter_var($_ENV['DEBITO_ENABLE_WEBHOOK'] ?? false, FILTER_VALIDATE_BOOL),
    'timeout' => (int) ($_ENV['DEBITO_TIMEOUT'] ?? 30),
    'currency' => $_ENV['DEBITO_CURRENCY'] ?? 'MZN',
    'mpesa_enabled' => filter_var($_ENV['DEBITO_MPESA_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
];

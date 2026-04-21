<?php

return [
    'base_url' => $_ENV['DEBITO_BASE_URL'] ?? 'http://localhost:9000',
    'wallet_id' => $_ENV['DEBITO_WALLET_ID'] ?? '',
    'use_static_token' => filter_var($_ENV['DEBITO_USE_STATIC_TOKEN'] ?? true, FILTER_VALIDATE_BOOL),
    'callback_url' => $_ENV['DEBITO_CALLBACK_URL'] ?? null,
    'enable_webhook' => filter_var($_ENV['DEBITO_ENABLE_WEBHOOK'] ?? false, FILTER_VALIDATE_BOOL),
];

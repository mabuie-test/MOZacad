<?php

return [
    'base_url' => $_ENV['DEBITO_BASE_URL'] ?? 'http://localhost:9000',
    'wallet_id' => $_ENV['DEBITO_WALLET_ID'] ?? '',
    'token' => $_ENV['DEBITO_TOKEN'] ?? '',
    'use_login_fallback' => filter_var($_ENV['DEBITO_USE_LOGIN_FALLBACK'] ?? false, FILTER_VALIDATE_BOOL),
    'login_endpoint' => $_ENV['DEBITO_LOGIN_ENDPOINT'] ?? '/api/v1/login',
    'login_username' => $_ENV['DEBITO_LOGIN_USERNAME'] ?? '',
    'login_password' => $_ENV['DEBITO_LOGIN_PASSWORD'] ?? '',
    'login_token_path' => $_ENV['DEBITO_LOGIN_TOKEN_PATH'] ?? 'token',
    'callback_url' => $_ENV['DEBITO_CALLBACK_URL'] ?? null,
    'enable_webhook' => filter_var($_ENV['DEBITO_ENABLE_WEBHOOK'] ?? false, FILTER_VALIDATE_BOOL),
    'timeout' => (int) ($_ENV['DEBITO_TIMEOUT'] ?? 30),
    'currency' => $_ENV['DEBITO_CURRENCY'] ?? 'MZN',
    'mpesa_enabled' => filter_var($_ENV['DEBITO_MPESA_ENABLED'] ?? true, FILTER_VALIDATE_BOOL),
];

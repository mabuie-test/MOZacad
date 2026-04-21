<?php

return [
    'name' => $_ENV['APP_NAME'] ?? 'Moz Acad',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Maputo',
    'locale' => $_ENV['APP_LOCALE'] ?? 'pt_MZ',
];

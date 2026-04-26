<?php

$env = strtolower(trim((string) ($_ENV['APP_ENV'] ?? 'production')));
$debugRequested = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL);
$allowDebugOutsideLocal = filter_var($_ENV['APP_ALLOW_DEBUG_OUTSIDE_LOCAL'] ?? false, FILTER_VALIDATE_BOOL);
$debug = ($env === 'local' || $env === 'development' || $allowDebugOutsideLocal) ? $debugRequested : false;

return [
    'name' => $_ENV['APP_NAME'] ?? 'Moz Acad',
    'env' => $env,
    'debug' => $debug,
    'url' => $_ENV['APP_URL'] ?? 'http://localhost',
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Africa/Maputo',
    'locale' => $_ENV['APP_LOCALE'] ?? 'pt_MZ',
];

<?php

declare(strict_types=1);

use App\Helpers\Env;
use App\Helpers\Router;
use App\Services\TraceContextService;
use App\Services\AIConfigBootstrapValidator;

require_once __DIR__ . '/../vendor/autoload.php';

Env::boot(__DIR__ . '/../.env');

try {
    AIConfigBootstrapValidator::validate();
} catch (\Throwable $exception) {
    $message = 'Falha crítica de configuração de IA no bootstrap. Verifique AI_PROVIDER, AI_PROVIDER_MODE e chaves OPENAI_API_KEY/GEMINI_API_KEY no .env.';
    error_log($message . ' Detalhe: ' . $exception->getMessage());

    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message . PHP_EOL . 'Detalhe técnico: ' . $exception->getMessage();
    exit(1);
}
$traceId = (new TraceContextService())->currentTraceId($_SERVER);
header('X-Request-ID: ' . $traceId);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $appEnv = strtolower(trim((string) Env::get('APP_ENV', 'production')));
    $isProduction = $appEnv === 'production';
    $secureCookie = $isProduction || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $configuredSameSite = ucfirst(strtolower(trim((string) Env::get('SESSION_SAMESITE', $isProduction ? 'Strict' : 'Lax'))));
    $sameSite = in_array($configuredSameSite, ['Lax', 'Strict', 'None'], true) ? $configuredSameSite : ($isProduction ? 'Strict' : 'Lax');
    if ($isProduction) {
        $sameSite = 'Strict';
    }
    if ($sameSite === 'None') {
        $secureCookie = true;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secureCookie ? '1' : '0');
    ini_set('session.cookie_samesite', $sameSite);
    ini_set('session.cache_limiter', 'nocache');

    if ($isProduction && $secureCookie) {
        session_name('__Host-MOZSESSID');
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $secureCookie,
        'samesite' => $sameSite,
    ]);
    session_start();
}

$router = new Router();
(require __DIR__ . '/../routes/web.php')($router);
(require __DIR__ . '/../routes/api.php')($router);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');

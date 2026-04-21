<?php

declare(strict_types=1);

namespace App\Middleware;

final class AuthMiddleware
{
    public function handle(callable $next): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['auth_user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Autenticação obrigatória.'], JSON_UNESCAPED_UNICODE);
            return null;
        }

        return $next();
    }
}

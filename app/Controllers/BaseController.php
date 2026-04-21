<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;

abstract class BaseController
{
    protected function view(string $template, array $data = []): void
    {
        $data['csrfToken'] = Csrf::token();
        View::render($template, $data);
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }

    protected function requireCsrfToken(): bool
    {
        $token = (string) ($_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));

        if (!Csrf::verify($token)) {
            $this->json(['message' => 'CSRF token inválido.'], 419);
            return false;
        }

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\View;
use App\Services\AdminAccessService;
use App\Services\AuthContextService;

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

    protected function requireAuthUserId(): int
    {
        $userId = (new AuthContextService())->authenticatedUserId();
        if ($userId <= 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }

    protected function requireAdminAccess(): bool
    {
        $adminId = (new AdminAccessService())->currentAdminId();
        if ($adminId === 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return false;
        }

        if ($adminId < 0) {
            $this->json(['message' => 'Sem permissão para recursos administrativos.'], 403);
            return false;
        }

        return true;
    }
}

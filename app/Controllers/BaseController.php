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
        $data['isAuthenticated'] = $this->authenticatedUserId() > 0;
        $data['isAdmin'] = (new AdminAccessService())->currentAdminId() > 0;
        $data['currentPath'] = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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
        $userId = $this->authenticatedUserId();
        if ($userId <= 0) {
            if ($this->isHtmlRequest()) {
                header('Location: /login');
                return 0;
            }

            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }

    protected function requireAdminAccess(): bool
    {
        $adminId = (new AdminAccessService())->currentAdminId();
        if ($adminId === 0) {
            if ($this->isHtmlRequest()) {
                header('Location: /login');
                return false;
            }
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return false;
        }

        if ($adminId < 0) {
            $this->json(['message' => 'Sem permissão para recursos administrativos.'], 403);
            return false;
        }

        return true;
    }

    protected function isHtmlRequest(): bool
    {
        return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/html');
    }

    private function authenticatedUserId(): int
    {
        return (new AuthContextService())->authenticatedUserId();
    }
}

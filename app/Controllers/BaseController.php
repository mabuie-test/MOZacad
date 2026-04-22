<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Csrf;
use App\Helpers\Database;
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

    protected function requireAuthUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }

    protected function requireAdminAccess(): bool
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return false;
        }

        $stmt = Database::connect()->prepare('SELECT 1
            FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id AND r.name IN (\'admin\',\'superadmin\')
            LIMIT 1');
        $stmt->execute(['user_id' => $userId]);

        if ($stmt->fetch() === false) {
            $this->json(['message' => 'Sem permissão para recursos administrativos.'], 403);
            return false;
        }

        return true;
    }
}

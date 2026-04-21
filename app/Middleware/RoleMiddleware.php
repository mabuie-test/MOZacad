<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Helpers\Database;

final class RoleMiddleware
{
    public function handle(array $roles, callable $next): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Autenticação obrigatória.'], JSON_UNESCAPED_UNICODE);
            return null;
        }

        $stmt = Database::connect()->prepare('SELECT r.name FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id WHERE ur.user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        $userRoles = array_map(static fn (array $row): string => (string) $row['name'], $stmt->fetchAll());

        if (array_intersect($roles, $userRoles) === []) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['message' => 'Sem permissão para este recurso.'], JSON_UNESCAPED_UNICODE);
            return null;
        }

        return $next();
    }
}

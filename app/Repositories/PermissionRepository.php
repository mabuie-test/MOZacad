<?php

declare(strict_types=1);

namespace App\Repositories;

final class PermissionRepository extends BaseRepository
{
    public function hasPermissionForUser(int $userId, string $permissionCode): bool
    {
        if ($userId <= 0 || $permissionCode === '') {
            return false;
        }

        $stmt = $this->db->prepare('SELECT 1
            FROM user_roles ur
            INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            WHERE ur.user_id = :user_id
              AND p.code = :code
              AND p.is_active = 1
            LIMIT 1');
        $stmt->execute([
            'user_id' => $userId,
            'code' => trim($permissionCode),
        ]);

        return $stmt->fetchColumn() !== false;
    }

    public function allPermissions(): array
    {
        $stmt = $this->db->query('SELECT id, code, name, description, category, is_active FROM permissions ORDER BY category ASC, code ASC');
        return $stmt ? $stmt->fetchAll() : [];
    }
}

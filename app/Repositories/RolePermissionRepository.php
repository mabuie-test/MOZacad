<?php

declare(strict_types=1);

namespace App\Repositories;

final class RolePermissionRepository extends BaseRepository
{
    public function rolePermissionMap(): array
    {
        $stmt = $this->db->query('SELECT r.id AS role_id, r.name AS role_name, p.code AS permission_code
            FROM roles r
            LEFT JOIN role_permissions rp ON rp.role_id = r.id
            LEFT JOIN permissions p ON p.id = rp.permission_id
            ORDER BY r.name ASC, p.code ASC');

        $map = [];
        foreach (($stmt ? $stmt->fetchAll() : []) as $row) {
            $roleName = (string) ($row['role_name'] ?? '');
            if ($roleName === '') {
                continue;
            }
            $map[$roleName] ??= [];
            $permissionCode = (string) ($row['permission_code'] ?? '');
            if ($permissionCode !== '') {
                $map[$roleName][] = $permissionCode;
            }
        }

        return $map;
    }

    public function setRolePermissions(int $roleId, array $permissionIds): void
    {
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
            $delete->execute(['role_id' => $roleId]);

            if ($permissionIds !== []) {
                $insert = $this->db->prepare('INSERT INTO role_permissions (role_id, permission_id, created_at, updated_at) VALUES (:role_id, :permission_id, NOW(), NOW())');
                foreach ($permissionIds as $permissionId) {
                    $id = (int) $permissionId;
                    if ($id <= 0) {
                        continue;
                    }
                    $insert->execute(['role_id' => $roleId, 'permission_id' => $id]);
                }
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}

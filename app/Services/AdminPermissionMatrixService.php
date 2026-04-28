<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RolePermissionRepository;
use App\Repositories\RoleRepository;

final class AdminPermissionMatrixService
{
    public function __construct(
        private readonly RoleRepository $roles = new RoleRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
        private readonly RolePermissionRepository $rolePermissions = new RolePermissionRepository(),
    ) {}

    public function matrix(): array
    {
        $roles = $this->roles->all();
        $permissions = $this->permissions->allPermissions();
        $map = $this->rolePermissions->rolePermissionMap();

        return [
            'roles' => $roles,
            'permissions' => $permissions,
            'rolePermissionMap' => $map,
        ];
    }

    public function updateMatrix(array $payload): void
    {
        $roles = $this->roles->all();
        $permissions = $this->permissions->allPermissions();

        $roleByName = [];
        foreach ($roles as $role) {
            $roleByName[(string) $role['name']] = (int) $role['id'];
        }

        $permissionByCode = [];
        foreach ($permissions as $permission) {
            $permissionByCode[(string) $permission['code']] = (int) $permission['id'];
        }

        $matrix = $payload['matrix'] ?? [];
        if (!is_array($matrix)) {
            return;
        }

        foreach ($roleByName as $roleName => $roleId) {
            $codes = $matrix[$roleName] ?? [];
            if (!is_array($codes)) {
                $codes = [];
            }

            $permissionIds = [];
            foreach ($codes as $code) {
                $permissionId = (int) ($permissionByCode[(string) $code] ?? 0);
                if ($permissionId > 0) {
                    $permissionIds[] = $permissionId;
                }
            }

            $this->rolePermissions->setRolePermissions((int) $roleId, array_values(array_unique($permissionIds)));
        }
    }
}

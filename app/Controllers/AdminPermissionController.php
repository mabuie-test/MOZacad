<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminPermissionMatrixService;

final class AdminPermissionController extends BaseController
{
    public function updateMatrix(): void
    {
        $permission = 'permissions.manage';
        if (!$this->guardAdminPermissionPost($permission, '/admin/permissions')) return;

        (new AdminPermissionMatrixService())->updateMatrix($_POST);
        $this->audit('admin.permissions.matrix.updated', 'role_permissions', null, [], $permission);
        $this->adminSuccess('Matriz de permissões actualizada.', '/admin/permissions');
    }
}

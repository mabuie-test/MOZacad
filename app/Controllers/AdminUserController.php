<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;

final class AdminUserController extends BaseController
{
    public function create(): void
    {
        $permission = 'admin.users.manage';
        if (!$this->guardAdminPermissionPost($permission, '/admin/users')) return;

        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            $this->adminError('Nome, email válido e senha mínima de 8 caracteres são obrigatórios.', 422, '/admin/users');
            return;
        }

        $users = new UserRepository();
        if ($users->findByEmail($email) !== null) {
            $this->adminError('Já existe um utilizador com este email.', 409, '/admin/users');
            return;
        }

        $userId = $users->create([
            'name' => $name,
            'email' => $email,
            'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => 1,
        ]);
        $users->syncRoles($userId, (array) ($_POST['roles'] ?? []));

        $this->audit('admin.user.created', 'user', $userId, ['roles' => (array) ($_POST['roles'] ?? [])], $permission);
        $this->adminSuccess('Utilizador criado com sucesso.', '/admin/users');
    }

    public function updateRoles(string $id): void
    {
        $permission = 'admin.users.manage';
        if (!$this->guardAdminPermissionPost($permission, '/admin/users')) return;

        $userId = (int) $id;
        (new UserRepository())->syncRoles($userId, (array) ($_POST['roles'] ?? []));
        $this->audit('admin.user.roles.updated', 'user', $userId, ['roles' => (array) ($_POST['roles'] ?? [])], $permission);
        $this->adminSuccess('Papéis atualizados.', '/admin/users');
    }

    public function setStatus(string $id): void
    {
        $permission = 'admin.users.manage';
        if (!$this->guardAdminPermissionPost($permission, '/admin/users')) return;

        $userId = (int) $id;
        $action = trim((string) ($_POST['action'] ?? ''));
        $isActive = $action === 'activate';
        (new UserRepository())->updateStatus($userId, $isActive);

        $this->audit('admin.user.status.updated', 'user', $userId, ['action' => $action], $permission);
        $this->adminSuccess($isActive ? 'Utilizador ativado.' : 'Utilizador desativado (suspenso/bloqueado).', '/admin/users');
    }

    public function delete(string $id): void
    {
        $permission = 'admin.users.manage';
        if (!$this->guardAdminPermissionPost($permission, '/admin/users')) return;

        $userId = (int) $id;
        try {
            (new UserRepository())->deleteById($userId);
            $this->audit('admin.user.deleted', 'user', $userId, [], $permission);
            $this->adminSuccess('Utilizador eliminado.', '/admin/users');
        } catch (\Throwable) {
            $this->adminError('Não foi possível eliminar: este utilizador possui registos ligados.', 409, '/admin/users');
        }
    }

    public function roleOptions(): array
    {
        return (new RoleRepository())->all();
    }
}

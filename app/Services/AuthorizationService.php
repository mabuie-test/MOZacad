<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;

final class AuthorizationService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly PermissionRepository $permissions = new PermissionRepository(),
    ) {}

    public function isAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->users->hasAnyRole($userId, ['superadmin', 'admin']);
    }

    public function can(int $userId, string $permissionCode): bool
    {
        if ($userId <= 0 || trim($permissionCode) === '') {
            return false;
        }

        if ($this->users->hasAnyRole($userId, ['superadmin'])) {
            return true;
        }

        return $this->permissions->hasPermissionForUser($userId, trim($permissionCode));
    }

    public function canAccessOrder(int $orderId, int $actorUserId): bool
    {
        $order = $this->orders->findById($orderId);
        if ($order === null) {
            return false;
        }

        return (int) $order['user_id'] === $actorUserId || $this->isAdmin($actorUserId);
    }
}

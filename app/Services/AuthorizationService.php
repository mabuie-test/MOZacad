<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\OrderRepository;
use App\Repositories\UserRepository;

final class AuthorizationService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
    ) {}

    public function isAdmin(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        return $this->users->hasAnyRole($userId, ['superadmin', 'admin']);
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

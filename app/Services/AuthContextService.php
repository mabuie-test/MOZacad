<?php

declare(strict_types=1);

namespace App\Services;

final class AuthContextService
{
    public function authenticatedUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        return (int) ($_SESSION['auth_user_id'] ?? 0);
    }
}

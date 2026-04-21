<?php

declare(strict_types=1);

namespace App\Middleware;

final class RoleMiddleware
{
    public function handle(array $roles, callable $next): mixed
    {
        // TODO: aplicar RBAC por roles user/admin/human_reviewer/superadmin.
        return $next();
    }
}

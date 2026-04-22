<?php

declare(strict_types=1);

namespace App\Services;

final class AdminAccessService
{
    public function __construct(
        private readonly AuthContextService $authContext = new AuthContextService(),
        private readonly AuthorizationService $authorization = new AuthorizationService(),
    ) {}

    public function currentAdminId(): int
    {
        $userId = $this->authContext->authenticatedUserId();
        if ($userId <= 0) {
            return 0;
        }

        return $this->authorization->isAdmin($userId) ? $userId : -1;
    }
}

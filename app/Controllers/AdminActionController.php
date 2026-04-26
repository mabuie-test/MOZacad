<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;

abstract class AdminActionController extends BaseController
{
    protected function guardAdminPost(): bool
    {
        return $this->requireAdminAccess() && $this->requireCsrfToken();
    }

    protected function adminSuccess(string $message, string $redirectPath, array $payload = []): void
    {
        $this->successResponse($message, $redirectPath, $payload);
    }

    protected function adminError(string $message, int $status, string $redirectPath, array $payload = []): void
    {
        $this->errorResponse($message, $status, $redirectPath, $payload);
    }

    protected function audit(string $action, string $subjectType, ?int $subjectId = null, array $payload = []): void
    {
        (new AuditLogRepository())->log((int) ($_SESSION['auth_user_id'] ?? 0), $action, $subjectType, $subjectId, $payload);
    }
}

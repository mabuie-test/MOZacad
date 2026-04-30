<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AIProviderPreflightService;

final class AdminAiPreflightController extends BaseController
{
    public function run(): void
    {
        if (!$this->guardAdminPermissionPost('operations.process_ai_queue', '/admin')) return;

        try {
            (new AIProviderPreflightService())->runManualPreflight(true);
            $this->adminSuccess('Teste manual de preflight IA executado.', '/admin');
        } catch (\Throwable $e) {
            $this->adminError($e->getMessage(), 422, '/admin');
        }
    }
}

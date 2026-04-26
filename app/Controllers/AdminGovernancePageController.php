<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminGovernancePageController extends BaseController
{
    public function institutionRules(): void { $this->render('institution-rules'); }
    public function templates(): void { $this->render('templates'); }

    private function render(string $section): void
    {
        if (!$this->requireAdminAccess()) return;
        $payload = (new AdminOverviewService())->payload($section, $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }
        $this->view('admin/index', $payload);
    }
}

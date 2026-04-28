<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminGovernancePageController extends BaseController
{
    public function institutionRules(): void { $this->render('institution-rules', 'governance.rules.view'); }
    public function templates(): void { $this->render('templates', 'governance.templates.view'); }

    private function render(string $section, string $permission): void
    {
        if (!$this->requireAdminPermission($permission, '/admin')) return;
        $payload = (new AdminOverviewService())->payload($section, $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }
        $this->view('admin/index', $payload);
    }
}

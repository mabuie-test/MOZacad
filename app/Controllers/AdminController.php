<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminController extends BaseController
{
    public function index(): void { $this->renderSection('overview'); }
    public function users(): void { $this->renderSection('users'); }
    public function orders(): void { $this->renderSection('orders'); }
    public function payments(): void { $this->renderSection('payments'); }
    public function humanReviewQueue(): void { $this->renderSection('human-review'); }
    public function institutions(): void { $this->renderSection('institutions'); }
    public function courses(): void { $this->renderSection('courses'); }
    public function disciplines(): void { $this->renderSection('disciplines'); }
    public function workTypes(): void { $this->renderSection('work-types'); }
    public function pricing(): void { $this->renderSection('pricing'); }
    public function discounts(): void { $this->renderSection('discounts'); }
    public function institutionRules(): void { $this->renderSection('institution-rules'); }
    public function templates(): void { $this->renderSection('templates'); }
    public function coupons(): void { $this->renderSection('coupons'); }

    private function renderSection(string $section): void
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

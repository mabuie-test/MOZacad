<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminController extends BaseController
{
    private const SECTION_PERMISSIONS = [
        'overview' => 'admin.overview.view',
        'users' => 'admin.users.view',
        'orders' => 'admin.orders.view',
        'payments' => 'admin.payments.view',
        'human-review' => 'human_review.queue.view',
        'institutions' => 'catalog.institutions.view',
        'courses' => 'catalog.courses.view',
        'disciplines' => 'catalog.disciplines.view',
        'work-types' => 'catalog.work_types.view',
        'pricing' => 'pricing.view',
        'discounts' => 'commercial.discounts.view',
        'institution-rules' => 'governance.rules.view',
        'templates' => 'governance.templates.view',
        'coupons' => 'commercial.coupons.view',
        'permissions' => 'permissions.manage',
    ];

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
    public function permissions(): void { $this->renderSection('permissions'); }

    private function renderSection(string $section): void
    {
        $required = self::SECTION_PERMISSIONS[$section] ?? 'admin.overview.view';
        if (!$this->requireAdminPermission($required, '/admin')) return;

        $payload = (new AdminOverviewService())->payload($section, $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }

        $this->view('admin/index', $payload);
    }
}

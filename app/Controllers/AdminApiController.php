<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminApiController extends BaseController
{
    private const SECTION_PERMISSIONS = [
        'overview' => 'admin.overview.view',
        'users' => 'admin.users.view',
        'orders' => 'admin.orders.view',
        'payments' => 'admin.payments.view',
        'institutions' => 'catalog.institutions.view',
        'courses' => 'catalog.courses.view',
        'disciplines' => 'catalog.disciplines.view',
        'work-types' => 'catalog.work_types.view',
        'pricing' => 'pricing.view',
        'discounts' => 'commercial.discounts.view',
        'institution-rules' => 'governance.rules.view',
        'templates' => 'governance.templates.view',
        'coupons' => 'commercial.coupons.view',
        'human-review' => 'human_review.queue.view',
        'permissions' => 'permissions.manage',
    ];

    public function section(string $section): void
    {
        if (!$this->requireAuthUserId()) return;
        if (!$this->requireFirstPartyApiAccess()) return;

        $normalized = strtolower(trim($section));
        $required = self::SECTION_PERMISSIONS[$normalized] ?? '';
        if ($required === '') {
            $this->json(['message' => 'Secção administrativa inválida.'], 404);
            return;
        }

        if (!$this->requireAdminPermission($required, '/admin')) {
            return;
        }

        $payload = (new AdminOverviewService())->payload($normalized, $_GET);
        $this->json($payload);
    }
}

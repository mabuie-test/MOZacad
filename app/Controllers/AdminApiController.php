<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminApiController extends BaseController
{
    public function section(string $section): void
    {
        if (!$this->requireAuthUserId()) return;
        if (!$this->requireFirstPartyApiAccess()) return;
        if (!$this->requireAdminAccess()) return;

        $normalized = strtolower(trim($section));
        $allowed = [
            'overview', 'users', 'orders', 'payments', 'institutions', 'courses', 'disciplines',
            'work-types', 'pricing', 'discounts', 'institution-rules', 'templates', 'coupons', 'human-review',
        ];
        if (!in_array($normalized, $allowed, true)) {
            $this->json(['message' => 'Secção administrativa inválida.'], 404);
            return;
        }

        $payload = (new AdminOverviewService())->payload($normalized, $_GET);
        $this->json($payload);
    }
}

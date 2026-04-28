<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminCommercialPageController extends BaseController
{
    public function pricing(): void { $this->render('pricing', 'pricing.view'); }
    public function discounts(): void { $this->render('discounts', 'commercial.discounts.view'); }
    public function coupons(): void { $this->render('coupons', 'commercial.coupons.view'); }

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

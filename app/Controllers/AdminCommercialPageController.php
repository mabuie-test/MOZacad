<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminCommercialPageController extends BaseController
{
    public function pricing(): void { $this->render('pricing'); }
    public function discounts(): void { $this->render('discounts'); }
    public function coupons(): void { $this->render('coupons'); }

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

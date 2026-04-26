<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminReviewPageController extends BaseController
{
    public function humanReviewQueue(): void
    {
        if (!$this->requireAdminAccess()) return;
        $payload = (new AdminOverviewService())->payload('human-review', $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }
        $this->view('admin/index', $payload);
    }
}

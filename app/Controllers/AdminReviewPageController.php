<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOverviewService;

final class AdminReviewPageController extends BaseController
{
    public function humanReviewQueue(): void
    {
        if (!$this->requireAdminPermission('human_review.queue.view', '/admin')) return;
        $payload = (new AdminOverviewService())->payload('human-review', $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }
        $this->view('admin/index', $payload);
    }
}

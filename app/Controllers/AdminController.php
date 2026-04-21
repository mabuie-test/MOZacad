<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserDiscountRepository;

final class AdminController extends BaseController
{
    public function index(): void
    {
        $this->view('admin/index');
    }

    public function orders(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $orders = $userId > 0 ? (new OrderRepository())->listByUser($userId) : [];
        $this->json(['orders' => $orders]);
    }

    public function payments(): void
    {
        $payments = (new PaymentRepository())->findPendingForPolling(200);
        $this->json(['payments' => $payments]);
    }

    public function humanReviewQueue(): void
    {
        $queue = (new HumanReviewQueueRepository())->listQueue(200);
        $this->json(['human_review_queue' => $queue]);
    }

    public function discounts(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['discounts' => [], 'message' => 'Informe user_id para listar descontos.']);
            return;
        }

        $discounts = (new UserDiscountRepository())->findEligible($userId, null);
        $this->json(['discounts' => $discounts]);
    }
}

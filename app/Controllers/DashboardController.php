<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RevisionRepository;

final class DashboardController extends BaseController
{
    public function index(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $orders = (new OrderRepository())->listByUser($userId);
        $payments = (new PaymentRepository())->listRecentByUser($userId, 10);
        $revisions = (new RevisionRepository())->listByUser($userId, 10);

        $this->json([
            'summary' => [
                'orders_total' => count($orders),
                'orders_paid_or_queued' => count(array_filter($orders, static fn (array $o): bool => in_array($o['status'], ['queued', 'in_progress', 'ready'], true))),
                'pending_payments' => count(array_filter($payments, static fn (array $p): bool => in_array($p['status'], ['pending', 'processing', 'pending_confirmation'], true))),
                'revision_requests' => count($revisions),
            ],
            'orders' => array_slice($orders, 0, 10),
            'payments' => $payments,
            'revisions' => $revisions,
        ]);
    }

    private function requireAuthUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }
}

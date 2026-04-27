<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;

final class AdminOperationsReadService
{
    public function load(string $section, array $filters): array
    {
        $orders = in_array($section, ['overview', 'orders', 'payments'], true) ? (new OrderRepository())->listAll(300) : [];
        $payments = in_array($section, ['overview', 'payments', 'orders'], true) ? (new PaymentRepository())->listAll(300) : [];
        $queueRows = in_array($section, ['overview', 'human-review'], true) ? (new HumanReviewQueueRepository())->listQueue(300) : [];

        $orderStatusFilter = trim((string) ($filters['order_status'] ?? ''));
        $paymentStatusFilter = trim((string) ($filters['payment_status'] ?? ''));
        $reviewStatusFilter = trim((string) ($filters['review_status'] ?? ''));

        if ($orderStatusFilter !== '') {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => (string) ($row['status'] ?? '') === $orderStatusFilter));
        }
        if ($paymentStatusFilter !== '') {
            $payments = array_values(array_filter($payments, static fn (array $row): bool => (string) ($row['status'] ?? '') === $paymentStatusFilter));
        }
        if ($reviewStatusFilter !== '') {
            $queueRows = array_values(array_filter($queueRows, static fn (array $row): bool => (string) ($row['status'] ?? '') === $reviewStatusFilter));
        }

        return [
            'overview' => [
                'orders_pending_payment' => count(array_filter($orders, static fn (array $o): bool => (string) ($o['status'] ?? '') === 'pending_payment')),
                'orders_under_review' => count(array_filter($orders, static fn (array $o): bool => in_array((string) ($o['status'] ?? ''), ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
                'payments_failed' => count(array_filter($payments, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['failed', 'cancelled', 'expired'], true))),
                'queue_unassigned' => count(array_filter($queueRows, static fn (array $q): bool => empty($q['reviewer_id']) && in_array((string) ($q['status'] ?? ''), ['pending', 'assigned'], true))),
            ],
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'reviewStatusFilter' => $reviewStatusFilter,
            'users' => in_array($section, ['overview', 'users', 'discounts'], true) ? (new UserRepository())->all(300) : [],
            'orders' => $orders,
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'reviewers' => in_array($section, ['overview', 'human-review'], true) ? (new UserRepository())->listByRole('human_reviewer', 80) : [],
        ];
    }
}

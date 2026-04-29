<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\DeliveryChecklistRepository;
use App\Repositories\PostPaymentExceptionRepository;

final class AdminOperationsReadService
{
    public function load(string $section, array $filters): array
    {
        $orders = in_array($section, ['overview', 'orders', 'payments'], true) ? (new OrderRepository())->listAll(300) : [];
        $payments = in_array($section, ['overview', 'payments', 'orders'], true) ? (new PaymentRepository())->listAll(300) : [];
        $queueRows = in_array($section, ['overview', 'human-review'], true) ? (new HumanReviewQueueRepository())->listQueue(300) : [];
        $checklistSummaryRows = in_array($section, ['overview', 'human-review'], true) ? (new DeliveryChecklistRepository())->summarizeByQueue(500) : [];
        $checklistSummaryMap = [];
        foreach ($checklistSummaryRows as $row) {
            $checklistSummaryMap[((int) $row['generated_document_id']) . ':' . ((int) $row['generated_document_version'])] = $row;
        }
        foreach ($queueRows as &$queueRow) {
            $key = ((int) ($queueRow['generated_document_id'] ?? 0)) . ':' . ((int) ($queueRow['generated_document_version'] ?? 0));
            $summary = $checklistSummaryMap[$key] ?? null;
            $queueRow['checklist_total_items'] = (int) ($summary['total_items'] ?? 0);
            $queueRow['checklist_checked_items'] = (int) ($summary['checked_items'] ?? 0);
            $queueRow['checklist_approved_items'] = (int) ($summary['approved_items'] ?? 0);
            $queueRow['checklist_blocking_items'] = (int) ($summary['blocking_items'] ?? 0);
        }
        unset($queueRow);

        $orderStatusFilter = trim((string) ($filters['order_status'] ?? ''));
        $paymentStatusFilter = trim((string) ($filters['payment_status'] ?? ''));
        $reviewStatusFilter = trim((string) ($filters['review_status'] ?? ''));
        $riskFilter = trim((string) ($filters['risk'] ?? ''));
        $delayFilter = trim((string) ($filters['delay'] ?? ''));
        $selectedOrderId = (int) ($filters['order_id'] ?? 0);
        $exceptionSummary = (new PostPaymentExceptionRepository())->summarize();

        if ($orderStatusFilter !== '') {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => (string) ($row['status'] ?? '') === $orderStatusFilter));
        }
        if ($paymentStatusFilter !== '') {
            $payments = array_values(array_filter($payments, static fn (array $row): bool => (string) ($row['status'] ?? '') === $paymentStatusFilter));
        }
        if ($reviewStatusFilter !== '') {
            $queueRows = array_values(array_filter($queueRows, static fn (array $row): bool => (string) ($row['status'] ?? '') === $reviewStatusFilter));
        }
        if ($riskFilter !== '') {
            $orders = array_values(array_filter($orders, static function (array $row) use ($riskFilter): bool {
                $priority = (string) ($row['admin_priority'] ?? 'normal');
                return $priority === $riskFilter;
            }));
        }
        if ($delayFilter !== '') {
            $orders = array_values(array_filter($orders, static function (array $row) use ($delayFilter): bool {
                $sla = (string) ($row['sla_state'] ?? 'on_track');
                return $sla === $delayFilter;
            }));
        }

        return [
            'overview' => [
                'orders_pending_payment' => count(array_filter($orders, static fn (array $o): bool => (string) ($o['status'] ?? '') === 'pending_payment')),
                'orders_under_review' => count(array_filter($orders, static fn (array $o): bool => in_array((string) ($o['status'] ?? ''), ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
                'payments_failed' => count(array_filter($payments, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['failed', 'cancelled', 'expired'], true))),
                'queue_unassigned' => count(array_filter($queueRows, static fn (array $q): bool => empty($q['reviewer_id']) && in_array((string) ($q['status'] ?? ''), ['pending', 'assigned'], true))),
                'exceptions_active' => (int) ($exceptionSummary['active_total'] ?? 0),
                'exceptions_blocking_delivery' => (int) ($exceptionSummary['blocked_delivery_total'] ?? 0),
                'exceptions_overdue' => (int) ($exceptionSummary['overdue_total'] ?? 0),
                'exceptions_escalated' => (int) ($exceptionSummary['escalated_total'] ?? 0),
                'exceptions_auto_reconciled' => (int) ($exceptionSummary['auto_reconciled_total'] ?? 0),
            ],
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'reviewStatusFilter' => $reviewStatusFilter,
            'riskFilter' => $riskFilter,
            'delayFilter' => $delayFilter,
            'users' => in_array($section, ['overview', 'users', 'discounts'], true) ? (new UserRepository())->all(300) : [],
            'orders' => $orders,
            'selectedOrderId' => $selectedOrderId,
            'orderAuditTrail' => $selectedOrderId > 0 ? (new AuditLogRepository())->listBySubject('order', $selectedOrderId, 80) : [],
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'reviewers' => in_array($section, ['overview', 'human-review'], true) ? (new UserRepository())->listByRole('human_reviewer', 80) : [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InvoiceRepository;
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
        $documents = (new GeneratedDocumentRepository())->listDeliverableByUser($userId);
        $invoices = (new InvoiceRepository())->listByUser($userId);

        $summary = [
            'orders_total' => count($orders),
            'orders_paid_or_queued' => count(array_filter($orders, static fn (array $o): bool => in_array($o['status'], ['queued', 'in_progress', 'under_human_review', 'ready'], true))),
            'pending_payments' => count(array_filter($payments, static fn (array $p): bool => in_array($p['status'], ['pending', 'processing', 'pending_confirmation'], true))),
            'revision_requests' => count(array_filter($orders, static fn (array $o): bool => in_array($o['status'], ['revision_requested', 'returned_for_revision'], true))),
            'ready_to_download' => count($documents),
            'open_invoices' => count(array_filter($invoices, static fn (array $inv): bool => in_array((string) ($inv['status'] ?? ''), ['pending', 'issued'], true))),
        ];

        $pendingActions = [
            'needs_payment' => array_values(array_filter($orders, static fn (array $o): bool => (string) $o['status'] === 'pending_payment')),
            'ready_for_download' => $documents,
            'under_review' => array_values(array_filter($orders, static fn (array $o): bool => in_array((string) $o['status'], ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
        ];

        if ($this->isHtmlRequest()) {
            $this->view('dashboard/index', [
                'summary' => $summary,
                'orders' => array_slice($orders, 0, 10),
                'payments' => $payments,
                'revisions' => $revisions,
                'documents' => array_slice($documents, 0, 10),
                'invoices' => array_slice($invoices, 0, 10),
                'pendingActions' => $pendingActions,
            ]);
            return;
        }

        $this->json([
            'summary' => $summary,
            'orders' => array_slice($orders, 0, 10),
            'payments' => $payments,
            'revisions' => $revisions,
            'documents' => array_slice($documents, 0, 10),
            'invoices' => array_slice($invoices, 0, 10),
            'pending_actions' => $pendingActions,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Env;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusLogRepository;
use App\Services\DebitoLoggerService;
use App\Services\DebitoStatusMapper;

final class DebitoWebhookController extends BaseController
{
    public function handle(): void
    {
        $enabled = filter_var(Env::get('DEBITO_ENABLE_WEBHOOK', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            $this->json(['received' => false, 'reason' => 'webhook_disabled'], 202);
            return;
        }

        $payload = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
        $reference = (string) ($payload['reference'] ?? $payload['debito_reference'] ?? '');
        $providerStatus = (string) ($payload['status'] ?? $payload['state'] ?? 'PENDING');
        $internalStatus = (new DebitoStatusMapper())->map($providerStatus);

        (new DebitoLoggerService())->info('Webhook Débito recebido', $payload);

        if ($reference === '') {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'missing_reference']);
            return;
        }

        $payments = new PaymentRepository();
        $payment = $payments->findByExternalReference($reference);

        if ($payment === null) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'payment_not_found']);
            return;
        }

        $payments->updateStatus((int) $payment['id'], $internalStatus, $providerStatus, (string) ($payload['message'] ?? null));
        (new DebitoTransactionRepository())->updateStatusByReference($reference, $internalStatus, $payload);
        (new PaymentStatusLogRepository())->create((int) $payment['id'], $internalStatus, $providerStatus, $payload, 'webhook');

        if ($internalStatus === 'paid') {
            $payments->markPaid((int) $payment['id'], $providerStatus);
            (new InvoiceRepository())->markPaidById((int) $payment['invoice_id']);
            (new OrderRepository())->updateStatus((int) $payment['order_id'], 'queued');
        }

        $this->json(['received' => true, 'processed' => true]);
    }
}

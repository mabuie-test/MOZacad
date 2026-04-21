<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Helpers\Env;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentStatusLogRepository;
use App\Services\DebitoLoggerService;
use App\Services\DebitoStatusMapper;
use Throwable;

final class DebitoWebhookController extends BaseController
{
    public function handle(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $payload = json_decode($rawBody, true);

        if (!is_array($payload)) {
            $this->json(['received' => false, 'processed' => false, 'reason' => 'invalid_json'], 400);
            return;
        }

        $logger = new DebitoLoggerService();
        $logger->info('Webhook Débito recebido', $payload);

        $enabled = filter_var((string) Env::get('DEBITO_ENABLE_WEBHOOK', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'webhook_disabled'], 202);
            return;
        }

        $reference = trim((string) ($payload['reference'] ?? $payload['debito_reference'] ?? ''));
        if ($reference === '') {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'missing_reference'], 422);
            return;
        }

        $providerStatus = (string) ($payload['status'] ?? $payload['state'] ?? 'PENDING');
        $internalStatus = (new DebitoStatusMapper())->map($providerStatus);

        $payments = new PaymentRepository();
        $payment = $payments->findByExternalReference($reference);

        if ($payment === null) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'payment_not_found'], 404);
            return;
        }

        $db = Database::connect();

        try {
            $db->beginTransaction();

            $payments->updateStatus(
                (int) $payment['id'],
                $internalStatus,
                $providerStatus,
                (string) ($payload['message'] ?? null)
            );
            (new DebitoTransactionRepository())->updateStatusByReference($reference, $internalStatus, $payload);
            (new PaymentStatusLogRepository())->create((int) $payment['id'], $internalStatus, $providerStatus, $payload, 'webhook');

            if ($internalStatus === 'paid') {
                $payments->markPaid((int) $payment['id'], $providerStatus);
                (new InvoiceRepository())->markPaidById((int) $payment['invoice_id']);
                (new OrderRepository())->updateStatus((int) $payment['order_id'], 'queued');
            }

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            $logger->error('Webhook Débito falhou', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            $this->json(['received' => true, 'processed' => false, 'reason' => 'processing_error'], 500);
            return;
        }

        $this->json([
            'received' => true,
            'processed' => true,
            'payment_id' => (int) $payment['id'],
            'status' => $internalStatus,
        ]);
    }
}

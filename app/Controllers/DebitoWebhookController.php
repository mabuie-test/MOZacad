<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Env;
use App\Repositories\DebitoTransactionRepository;
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

        if ($reference !== '') {
            $payments = new PaymentRepository();
            $payment = null;
            foreach ($payments->findPendingForPolling(200) as $candidate) {
                if ((string) ($candidate['external_reference'] ?? '') === $reference) {
                    $payment = $candidate;
                    break;
                }
            }

            if ($payment !== null) {
                $payments->updateStatus((int) $payment['id'], $internalStatus, $providerStatus, (string) ($payload['message'] ?? null));
                (new DebitoTransactionRepository())->updateStatusByReference($reference, $internalStatus, $payload);
                (new PaymentStatusLogRepository())->create((int) $payment['id'], $internalStatus, $providerStatus, $payload, 'webhook');
            }
        }

        $this->json(['received' => true]);
    }
}

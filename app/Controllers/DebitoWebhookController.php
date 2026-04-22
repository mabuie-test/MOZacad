<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Services\DebitoLoggerService;
use App\Services\DebitoStatusMapper;
use App\Services\PaymentStateTransitionService;
use Throwable;

final class DebitoWebhookController extends BaseController
{
    public function handle(): void
    {
        $rawBody = file_get_contents('php://input') ?: '';
        $payload = json_decode($rawBody, true);
        $logger = new DebitoLoggerService();

        $logger->info('Webhook Débito recebido', [
            'payload' => is_array($payload) ? $payload : ['raw' => $rawBody],
            'headers' => [
                'content_type' => (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ],
        ]);

        if (!is_array($payload)) {
            $this->json(['received' => false, 'processed' => false, 'reason' => 'invalid_json'], 400);
            return;
        }

        $reference = $this->extractReference($payload);
        if ($reference === '') {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'missing_reference'], 422);
            return;
        }

        $providerStatus = $this->extractProviderStatus($payload);
        $internalStatus = (new DebitoStatusMapper())->map($providerStatus);

        $payments = new PaymentRepository();
        $payment = $payments->findByExternalReference($reference);

        if ($payment === null) {
            $this->json(['received' => true, 'processed' => false, 'reason' => 'payment_not_found'], 404);
            return;
        }

        try {
            $updated = (new PaymentStateTransitionService())->apply(
                $payment,
                $reference,
                $internalStatus,
                $providerStatus,
                $payload,
                'webhook'
            );
        } catch (Throwable $e) {
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
            'updated' => $updated,
            'payment_id' => (int) $payment['id'],
            'status' => $internalStatus,
        ]);
    }

    private function extractReference(array $payload): string
    {
        return trim((string) (
            $payload['reference']
            ?? $payload['debito_reference']
            ?? $payload['transaction_reference']
            ?? $payload['data']['reference']
            ?? ''
        ));
    }

    private function extractProviderStatus(array $payload): string
    {
        return (string) (
            $payload['status']
            ?? $payload['state']
            ?? $payload['transaction_status']
            ?? $payload['data']['status']
            ?? 'PENDING'
        );
    }
}

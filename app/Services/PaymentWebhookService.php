<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\PaymentRepository;
use RuntimeException;

final class PaymentWebhookService
{
    public function __construct(
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly DebitoStatusMapper $statusMapper = new DebitoStatusMapper(),
        private readonly PaymentStateTransitionService $transitions = new PaymentStateTransitionService(),
    ) {}

    /**
     * @return array{received:bool,processed:bool,http_status:int,updated?:bool,payment_id?:int,status?:string,reason?:string}
     */
    public function processDebitoWebhook(string $rawBody, array $headers = []): array
    {
        $this->logger->info('Webhook Débito recebido', [
            'payload' => $rawBody,
            'headers' => $headers,
        ]);

        $enabled = filter_var((string) Env::get('DEBITO_ENABLE_WEBHOOK', true), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            return ['received' => true, 'processed' => false, 'http_status' => 202, 'reason' => 'webhook_disabled'];
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            return ['received' => false, 'processed' => false, 'http_status' => 400, 'reason' => 'invalid_json'];
        }

        $reference = $this->extractReference($payload);
        if ($reference === '') {
            return ['received' => true, 'processed' => false, 'http_status' => 422, 'reason' => 'missing_reference'];
        }

        $payment = $this->payments->findByExternalReference($reference);
        if ($payment === null) {
            return ['received' => true, 'processed' => false, 'http_status' => 404, 'reason' => 'payment_not_found'];
        }

        $providerStatus = $this->extractProviderStatus($payload);
        $internalStatus = $this->statusMapper->map($providerStatus);

        try {
            $updated = $this->transitions->apply(
                $payment,
                $reference,
                $internalStatus,
                $providerStatus,
                $payload,
                'webhook'
            );
        } catch (\Throwable $e) {
            $this->logger->error('Webhook Débito falhou', [
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException('processing_error', 0, $e);
        }

        return [
            'received' => true,
            'processed' => true,
            'http_status' => 200,
            'updated' => $updated,
            'payment_id' => (int) $payment['id'],
            'status' => $internalStatus,
        ];
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

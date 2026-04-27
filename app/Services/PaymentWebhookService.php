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
        $normalizedHeaders = $this->normalizeHeaders($headers);
        $this->logger->info('Webhook Débito recebido', ['headers' => $this->sanitizeHeadersForLog($normalizedHeaders)]);

        $enabled = filter_var((string) Env::get('DEBITO_ENABLE_WEBHOOK', false), FILTER_VALIDATE_BOOL);
        if (!$enabled) {
            return ['received' => true, 'processed' => false, 'http_status' => 202, 'reason' => 'webhook_disabled'];
        }

        if (!$this->validateWebhookConfiguration()) {
            return ['received' => true, 'processed' => false, 'http_status' => 503, 'reason' => 'webhook_misconfigured_secret_required'];
        }

        if (!$this->validateSignature($rawBody, $normalizedHeaders)) {
            return ['received' => true, 'processed' => false, 'http_status' => 401, 'reason' => 'invalid_signature'];
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

    private function validateSignature(string $rawBody, array $headers): bool
    {
        $secret = trim((string) Env::get('DEBITO_WEBHOOK_SECRET', ''));
        if ($secret === '') {
            $allowUnsignedLocal = filter_var((string) Env::get('DEBITO_ALLOW_UNSIGNED_WEBHOOK_LOCAL', false), FILTER_VALIDATE_BOOL);
            if ($this->isLocalEnvironment() && $allowUnsignedLocal) {
                $this->logger->info('Webhook sem assinatura permitido apenas em ambiente local controlado');
                return true;
            }

            $this->logger->error('Webhook bloqueado por ausência de segredo de assinatura');
            return false;
        }

        $headerValue = trim((string) ($headers['x_debito_signature'] ?? $headers['x_webhook_signature'] ?? ''));
        if ($headerValue === '') {
            $this->logger->error('Webhook sem header de assinatura com segredo configurado');
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        $incoming = str_starts_with($headerValue, 'sha256=') ? substr($headerValue, 7) : $headerValue;

        return hash_equals($expected, $incoming);
    }


    private function validateWebhookConfiguration(): bool
    {
        $secret = trim((string) Env::get('DEBITO_WEBHOOK_SECRET', ''));
        if ($secret !== '') {
            return true;
        }

        if ($this->isProduction()) {
            $this->logger->error('Webhook desativado por configuração insegura em produção (DEBITO_WEBHOOK_SECRET vazio)');
            return false;
        }

        if (!$this->isLocalEnvironment()) {
            $this->logger->error('Webhook desativado fora de ambiente local por ausência de segredo (DEBITO_WEBHOOK_SECRET vazio)');
            return false;
        }

        return true;
    }

    private function isProduction(): bool
    {
        return strtolower(trim((string) Env::get('APP_ENV', 'production'))) === 'production';
    }

    private function isLocalEnvironment(): bool
    {
        $env = strtolower(trim((string) Env::get('APP_ENV', 'production')));
        return in_array($env, ['local', 'development', 'dev'], true);
    }

    private function extractReference(array $payload): string
    {
        $reference = trim((string) (
            $payload['reference']
            ?? $payload['debito_reference']
            ?? $payload['transaction_reference']
            ?? $payload['data']['reference']
            ?? ''
        ));

        if ($reference === '' || strlen($reference) > 120) {
            return '';
        }

        if (!preg_match('/^[a-zA-Z0-9._\-:\/]+$/', $reference)) {
            return '';
        }

        return $reference;
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

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower(str_replace('-', '_', (string) $key))] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }

    private function sanitizeHeadersForLog(array $headers): array
    {
        foreach (['x_debito_signature', 'x_webhook_signature', 'authorization'] as $sensitiveKey) {
            if (isset($headers[$sensitiveKey]) && $headers[$sensitiveKey] !== '') {
                $headers[$sensitiveKey] = '[redacted]';
            }
        }

        return $headers;
    }
}

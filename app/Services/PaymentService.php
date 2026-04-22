<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Env;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\PaymentRepository;
use RuntimeException;
use Throwable;

final class PaymentService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly DebitoMpesaPayloadBuilder $payloadBuilder = new DebitoMpesaPayloadBuilder(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly DebitoTransactionRepository $debitoTransactions = new DebitoTransactionRepository(),
        private readonly DebitoStatusMapper $statusMapper = new DebitoStatusMapper(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    public function initiateMpesa(array $context): array
    {
        $existingOpen = $this->payments->findOpenByOrderId((int) $context['order_id']);
        if ($existingOpen !== null) {
            return [
                'payment_id' => (int) $existingOpen['id'],
                'internal_reference' => (string) ($existingOpen['internal_reference'] ?? ''),
                'debito_reference' => (string) ($existingOpen['external_reference'] ?? ''),
                'status' => (string) ($existingOpen['status'] ?? 'pending'),
                'provider_status' => (string) ($existingOpen['provider_status'] ?? 'PENDING'),
                'reused_pending_payment' => true,
            ];
        }
        $internalRef = Env::get('PAYMENT_REFERENCE_PREFIX', 'PAY') . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $db = Database::connect();
        $db->beginTransaction();

        $paymentId = $this->payments->create([
            'user_id' => $context['user_id'],
            'order_id' => $context['order_id'],
            'invoice_id' => $context['invoice_id'],
            'provider' => 'debito',
            'method' => 'mpesa_c2b',
            'amount' => $context['amount'],
            'currency' => $context['currency'] ?? 'MZN',
            'msisdn' => $context['msisdn'],
            'status' => 'pending',
            'internal_reference' => $internalRef,
        ]);

        try {
            $payload = $this->payloadBuilder->build(
                (float) $context['amount'],
                (string) $context['msisdn'],
                $internalRef,
                $context['callback_url'] ?? null,
                $context['internal_notes'] ?? null
            );
            $providerResponse = $this->provider->initiate($payload);

            $debitoReference = (string) ($providerResponse['debito_reference'] ?? '');
            if ($debitoReference === '') {
                throw new RuntimeException('Débito não retornou referência da transação.');
            }

            $providerStatus = (string) ($providerResponse['provider_status'] ?? 'PENDING');
            $internalStatus = $this->statusMapper->map($providerStatus);

            $this->payments->setExternalReference($paymentId, $debitoReference, $providerResponse['provider_transaction_id'] ?: null, $providerStatus);
            $this->payments->updateStatus($paymentId, $internalStatus, $providerStatus, $providerResponse['provider_message'] ?: null);

            $this->debitoTransactions->create([
                'payment_id' => $paymentId,
                'wallet_id' => (string) Env::get('DEBITO_WALLET_ID', ''),
                'debito_reference' => $debitoReference,
                'request_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'response_payload_json' => json_encode($providerResponse['raw'] ?? [], JSON_UNESCAPED_UNICODE),
                'provider_response_code' => $providerResponse['provider_code'] ?: null,
                'provider_response_message' => $providerResponse['provider_message'] ?: null,
                'status' => $internalStatus,
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->logger->error('Débito initiation failed', [
                'payment_id' => $paymentId,
                'order_id' => $context['order_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->logger->info('Débito initiation', ['payment_id' => $paymentId, 'request' => $payload, 'response' => $providerResponse]);

        return [
            'payment_id' => $paymentId,
            'internal_reference' => $internalRef,
            'debito_reference' => $debitoReference,
            'status' => $internalStatus,
            'provider_status' => $providerStatus,
        ];
    }
}

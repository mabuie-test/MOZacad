<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Env;
use App\Repositories\DebitoTransactionRepository;
use App\Repositories\OrderRepository;
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
        private readonly PaymentStateTransitionService $transitions = new PaymentStateTransitionService(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
        private readonly OrderRepository $orders = new OrderRepository(),
    ) {}

    public function initiateMpesa(array $context): array
    {
        $db = Database::connect();
        $db->beginTransaction();

        $paymentId = 0;
        $debitoReference = '';
        $providerStatus = 'PENDING';
        $internalStatus = 'pending';
        $providerPayload = [];
        try {
            $order = $this->orders->lockByIdForUpdate((int) $context['order_id']);
            if ($order === null) {
                throw new RuntimeException('Pedido não encontrado para iniciar pagamento.');
            }

            $existingOpen = $this->payments->findOpenByOrderId((int) $context['order_id']);
            if ($existingOpen !== null) {
                $existingOpen = $this->refreshReusedPaymentStatus($existingOpen, 'initiate_reuse_existing');
                $db->commit();
                return [
                    'payment_id' => (int) $existingOpen['id'],
                    'internal_reference' => (string) ($existingOpen['internal_reference'] ?? ''),
                    'debito_reference' => (string) ($existingOpen['external_reference'] ?? ''),
                    'status' => (string) ($existingOpen['status'] ?? 'pending'),
                    'provider_status' => (string) ($existingOpen['provider_status'] ?? 'PENDING'),
                    'reused_pending_payment' => true,
                ];
            }

            $internalRef = $this->buildInternalReference();
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
                throw new RuntimeException('DebitoPay v2 não retornou payment_id da transação.');
            }

            $providerStatus = (string) ($providerResponse['provider_status'] ?? 'PENDING');
            $internalStatus = $this->statusMapper->map($providerStatus);
            $providerPayload = is_array($providerResponse['raw'] ?? null) ? $providerResponse['raw'] : [];

            $this->payments->setExternalReference($paymentId, $debitoReference, $providerResponse['provider_transaction_id'] ?: null, $providerStatus);

            $this->debitoTransactions->create([
                'payment_id' => $paymentId,
                'wallet_id' => (string) Env::get('DEBITO_WALLET_CODE', ''),
                'api_version' => 'v2',
                'wallet_code' => (string) Env::get('DEBITO_WALLET_CODE', ''),
                'provider_payment_id' => $debitoReference,
                'provider_reference' => (string) ($providerResponse['provider_transaction_id'] ?? ''),
                'debito_reference' => $debitoReference,
                'request_payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'response_payload_json' => json_encode($providerResponse['raw'] ?? [], JSON_UNESCAPED_UNICODE),
                'provider_response_code' => $providerResponse['provider_code'] ?: null,
                'provider_response_message' => $providerResponse['provider_message'] ?: null,
                'status' => 'pending',
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            if ($this->isActiveTransactionConflict($e)) {
                $reusedPayment = $this->payments->findOpenByOrderId((int) ($context['order_id'] ?? 0))
                    ?? $this->payments->findLatestByOrderId((int) ($context['order_id'] ?? 0));

                if ($reusedPayment !== null) {
                    $existingReference = trim((string) ($reusedPayment['external_reference'] ?? ''));
                    if ($existingReference === '') {
                        throw new RuntimeException('Já existe uma transação activa sem referência externa associada. Contacte o suporte para reconciliar o pagamento sem duplicações.', 0, $e);
                    }

                    $reusedPayment = $this->refreshReusedPaymentStatus($reusedPayment, 'initiate_reuse_conflict');
                    $this->logger->info('Débito initiation reused after active transaction conflict', [
                        'order_id' => $context['order_id'] ?? null,
                        'payment_id' => (int) ($reusedPayment['id'] ?? 0),
                    ]);

                    return [
                        'payment_id' => (int) ($reusedPayment['id'] ?? 0),
                        'internal_reference' => (string) ($reusedPayment['internal_reference'] ?? ''),
                        'debito_reference' => (string) ($reusedPayment['external_reference'] ?? ''),
                        'status' => (string) ($reusedPayment['status'] ?? 'pending'),
                        'provider_status' => (string) ($reusedPayment['provider_status'] ?? 'PENDING'),
                        'reused_pending_payment' => true,
                        'recovered_from_provider_conflict' => true,
                    ];
                }

                throw new RuntimeException('Já existe uma transação ativa para este número. Aguarde alguns segundos e volte a confirmar o pagamento.', 0, $e);
            }

            $this->logger->error('Débito initiation failed', [
                'payment_id' => $paymentId,
                'order_id' => $context['order_id'] ?? null,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $this->logger->info('Débito initiation', ['payment_id' => $paymentId]);

        $payment = $this->payments->findById($paymentId);
        if (is_array($payment) && $debitoReference !== '') {
            $this->transitions->apply(
                $payment,
                $debitoReference,
                $internalStatus,
                $providerStatus,
                $providerPayload,
                'initiate'
            );
            $payment = $this->payments->findById($paymentId);
        }

        return [
            'payment_id' => $paymentId,
            'internal_reference' => (string) ($payment['internal_reference'] ?? ''),
            'debito_reference' => (string) ($payment['external_reference'] ?? ''),
            'status' => (string) ($payment['status'] ?? 'pending'),
            'provider_status' => (string) ($payment['provider_status'] ?? 'PENDING'),
        ];
    }

    private function buildInternalReference(): string
    {
        return Env::get('PAYMENT_REFERENCE_PREFIX', 'PAY') . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    private function isActiveTransactionConflict(Throwable $e): bool
    {
        $message = mb_strtolower(trim($e->getMessage()));
        if ($message === '') {
            return false;
        }

        return str_contains($message, 'active transaction')
            || str_contains($message, 'already an active transaction')
            || str_contains($message, 'transação ativa');
    }

    private function refreshReusedPaymentStatus(array $payment, string $source): array
    {
        $paymentId = (int) ($payment['id'] ?? 0);
        if ($paymentId <= 0) {
            return $payment;
        }

        $status = (string) ($payment['status'] ?? 'pending');
        if (in_array($status, ['paid', 'failed', 'cancelled', 'expired'], true)) {
            return $payment;
        }

        $reference = trim((string) ($payment['external_reference'] ?? ''));
        if ($reference === '') {
            return $payment;
        }

        try {
            $statusPayload = $this->provider->checkStatus($reference);
            $providerStatus = (string) ($statusPayload['provider_status'] ?? 'PENDING');
            $internalStatus = $this->statusMapper->map($providerStatus);
            $this->transitions->apply(
                $payment,
                $reference,
                $internalStatus,
                $providerStatus,
                is_array($statusPayload['raw'] ?? null) ? $statusPayload['raw'] : [],
                $source
            );
        } catch (Throwable $e) {
            $this->logger->error('Débito reused payment refresh failed', [
                'payment_id' => $paymentId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->payments->findById($paymentId) ?? $payment;
    }
}

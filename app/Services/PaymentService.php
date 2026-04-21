<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use App\Repositories\PaymentRepository;

final class PaymentService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider = new DebitoMpesaProvider(),
        private readonly DebitoMpesaPayloadBuilder $payloadBuilder = new DebitoMpesaPayloadBuilder(),
        private readonly PaymentRepository $payments = new PaymentRepository(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    public function initiateMpesa(array $context): array
    {
        $internalRef = Env::get('PAYMENT_REFERENCE_PREFIX', 'PAY') . '-' . date('YmdHis') . '-' . random_int(100, 999);
        $paymentId = $this->payments->create([
            'user_id' => $context['user_id'],
            'order_id' => $context['order_id'],
            'invoice_id' => $context['invoice_id'],
            'provider' => 'debito',
            'method' => 'mpesa_c2b',
            'amount' => $context['amount'],
            'currency' => $context['currency'] ?? 'MZN',
            'msisdn' => $context['msisdn'],
            'status' => 'pending_confirmation',
            'internal_reference' => $internalRef,
        ]);

        $payload = $this->payloadBuilder->build((float)$context['amount'], (string)$context['msisdn'], $internalRef);
        $response = $this->provider->initiate($payload);

        $this->logger->info('Débito initiation', ['payment_id' => $paymentId, 'request' => $payload, 'response' => $response]);

        return ['payment_id' => $paymentId, 'internal_reference' => $internalRef, 'provider_response' => $response];
    }
}

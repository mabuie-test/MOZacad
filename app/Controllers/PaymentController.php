<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\PaymentRepository;
use App\Services\InvoiceService;
use App\Services\PaymentService;

final class PaymentController extends BaseController
{
    public function initiateMpesa(): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);

        if ($userId <= 0 || $orderId <= 0 || $amount <= 0) {
            $this->json(['message' => 'Parâmetros inválidos para iniciar pagamento.'], 422);
            return;
        }

        $invoiceId = (new InvoiceService())->create($userId, $orderId, $amount);
        $result = (new PaymentService())->initiateMpesa([
            'user_id' => $userId,
            'order_id' => $orderId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'currency' => $_POST['currency'] ?? 'MZN',
            'msisdn' => $_POST['msisdn'] ?? '',
            'internal_notes' => $_POST['internal_notes'] ?? null,
        ]);

        $this->json($result, 201);
    }

    public function status(int $id): void
    {
        $payment = (new PaymentRepository())->findById($id);
        if ($payment === null) {
            $this->json(['message' => 'Pagamento não encontrado.'], 404);
            return;
        }

        $this->json([
            'id' => $payment['id'],
            'status' => $payment['status'],
            'provider_status' => $payment['provider_status'],
            'external_reference' => $payment['external_reference'],
            'paid_at' => $payment['paid_at'],
        ]);
    }
}

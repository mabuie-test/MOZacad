<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Throwable;

final class PaymentController extends BaseController
{
    public function initiateMpesa(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }
        if (!$this->requireCsrfToken()) {
            return;
        }

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $msisdn = trim((string) ($_POST['msisdn'] ?? ''));

        if ($orderId <= 0 || $msisdn === '') {
            $this->json(['message' => 'order_id e msisdn são obrigatórios para iniciar pagamento.'], 422);
            return;
        }

        $order = (new OrderRepository())->findById($orderId);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        $amount = (float) ($order['final_price'] ?? 0);
        if ($amount <= 0) {
            $this->json(['message' => 'Pedido sem valor final para cobrança.'], 422);
            return;
        }

        try {
            $invoiceId = (new InvoiceService())->create((int) $order['user_id'], $orderId, $amount, (string) ($_POST['currency'] ?? 'MZN'));
            $result = (new PaymentService())->initiateMpesa([
                'user_id' => $userId,
                'order_id' => $orderId,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'currency' => $_POST['currency'] ?? 'MZN',
                'msisdn' => $msisdn,
                'callback_url' => $_POST['callback_url'] ?? null,
                'internal_notes' => $_POST['internal_notes'] ?? null,
            ]);
        } catch (Throwable $e) {
            $this->json(['message' => 'Erro ao iniciar pagamento.', 'error' => $e->getMessage()], 502);
            return;
        }

        $this->json(['invoice_id' => $invoiceId, 'payment' => $result], 201);
    }

    public function status(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $payment = (new PaymentRepository())->findById($id);
        if ($payment === null || (int) $payment['user_id'] !== $userId) {
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

    private function requireAuthUserId(): int
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['message' => 'Autenticação obrigatória.'], 401);
            return 0;
        }

        return $userId;
    }
}

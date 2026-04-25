<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Services\PaymentApplicationService;
use RuntimeException;
use Throwable;

final class PaymentController extends BaseController
{
    public function initiateMpesa(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0 || !$this->requireCsrfToken()) return;

        $orderId = (int) ($_POST['order_id'] ?? 0);
        $msisdn = trim((string) ($_POST['msisdn'] ?? ''));
        if ($orderId <= 0 || $msisdn === '') {
            $this->errorResponse('order_id e msisdn são obrigatórios para iniciar pagamento.', 422, $this->refererPath('/orders'));
            return;
        }

        try {
            $flow = (new PaymentApplicationService())->initiateOrderMpesa(
                $orderId,
                $userId,
                $msisdn,
                !empty($_POST['callback_url']) ? (string) $_POST['callback_url'] : null,
                !empty($_POST['internal_notes']) ? (string) $_POST['internal_notes'] : null,
            );
            (new AuditLogRepository())->log($userId, 'payment.initiate_mpesa', 'order', $orderId, ['invoice_id' => (int) $flow['invoice_id']]);
            $this->successResponse('Pagamento iniciado com sucesso.', '/orders/' . $orderId, ['invoice_id' => (int) $flow['invoice_id'], 'payment' => $flow['payment']], 201);
        } catch (RuntimeException $e) {
            $this->errorResponse($e->getMessage(), 422, $this->refererPath('/orders/' . $orderId . '/pay'));
        } catch (Throwable $e) {
            $this->errorResponse('Erro ao iniciar pagamento.', 502, $this->refererPath('/orders/' . $orderId . '/pay'), ['error' => $e->getMessage()]);
        }
    }

    public function status(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $payment = (new PaymentApplicationService())->userPaymentStatus($id, $userId);
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

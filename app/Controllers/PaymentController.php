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
        if ($userId <= 0) return;

        if (!$this->isApiRequest() && !$this->requireCsrfToken()) return;

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

            $payload = [
                'order_id' => $orderId,
                'invoice_id' => (int) $flow['invoice_id'],
                'payment' => $flow['payment'],
            ];
            $this->successResponse('Pagamento iniciado com sucesso.', '/orders/' . $orderId, $payload, 201);
        } catch (RuntimeException $e) {
            $this->errorResponse($e->getMessage(), 422, $this->refererPath('/orders/' . $orderId . '/pay'));
        } catch (Throwable $e) {
            $this->errorResponse('Erro ao iniciar pagamento.', 502, $this->refererPath('/orders/' . $orderId . '/pay'), ['error' => $e->getMessage()]);
        }
    }


    private function isApiRequest(): bool
    {
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return str_starts_with($path, '/api/');
    }

    public function status(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $payment = (new PaymentApplicationService())->userPaymentStatus($id, $userId);
        if ($payment === null) {
            $this->errorResponse('Pagamento não encontrado.', 404, '/orders');
            return;
        }

        if ($this->isHtmlRequest()) {
            $status = (string) ($payment['status'] ?? 'pending');
            $orderId = (int) ($payment['order_id'] ?? 0);
            $redirect = $orderId > 0 ? '/orders/' . $orderId : '/orders';
            $this->successResponse('Estado do pagamento: ' . $status . '.', $redirect);
            return;
        }

        $this->json([
            'id' => $payment['id'],
            'order_id' => $payment['order_id'] ?? null,
            'status' => $payment['status'],
            'provider_status' => $payment['provider_status'],
            'external_reference' => $payment['external_reference'],
            'paid_at' => $payment['paid_at'],
        ]);
    }
}

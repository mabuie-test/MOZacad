<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminPaymentService;
use App\Services\AdminWorkerActionService;
use App\Services\ApplicationLoggerService;
use RuntimeException;

final class AdminPaymentController extends BaseController
{

    public function processAiQueueNow(): void
    {
        if (!$this->guardAdminPost()) return;

        try {
            $summary = (new AdminWorkerActionService())->processAiQueueNow();
            (new ApplicationLoggerService())->info('admin.ai_queue.process_now', $summary + ['admin_user_id' => (int) ($_SESSION['auth_user_id'] ?? 0)]);
            $this->audit('admin.ai_queue.process_now', 'ai_job', null, $summary);

            if ($this->wantsJson()) {
                $this->json(['message' => 'Fila processada.', 'summary' => $summary]);
                return;
            }

            $this->adminSuccess(
                sprintf('Fila processada. checked=%d processed=%d completed=%d failed=%d skipped=%d', (int) $summary['checked'], (int) $summary['processed'], (int) $summary['completed'], (int) $summary['failed'], (int) $summary['skipped']),
                '/admin/orders'
            );
        } catch (\Throwable) {
            $this->adminError('Falha ao processar fila técnica.', 500, '/admin/orders');
        }
    }

    public function confirmManual(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $providerStatus = trim((string) ($_POST['provider_status'] ?? 'SUCCESSFUL'));
        $note = trim((string) ($_POST['note'] ?? ''));

        try {
            (new AdminPaymentService())->confirmPaymentManually($id, $providerStatus, $note !== '' ? $note : null);
            $this->audit('admin.payment.confirm_manual', 'payment', $id, [
                'provider_status' => $providerStatus,
                'note' => $note,
            ]);
            $this->adminSuccess('Pagamento confirmado manualmente com sucesso.', '/admin/payments');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/payments');
        } catch (\Throwable) {
            $this->adminError('Falha ao confirmar pagamento manualmente.', 500, '/admin/payments');
        }
    }
}


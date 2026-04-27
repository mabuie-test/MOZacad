<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminPaymentService;
use RuntimeException;

final class AdminPaymentController extends BaseController
{
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


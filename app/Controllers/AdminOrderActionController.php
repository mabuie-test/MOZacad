<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminOrderStateTransitionService;

final class AdminOrderActionController extends BaseController
{
    public function pause(int $id): void { $this->transition($id, 'pause'); }
    public function resume(int $id): void { $this->transition($id, 'resume'); }
    public function escalate(int $id): void { $this->transition($id, 'escalate'); }
    public function blockDelivery(int $id): void { $this->transition($id, 'block_delivery'); }
    public function reopenReview(int $id): void { $this->transition($id, 'reopen_review'); }

    private function transition(int $id, string $action): void
    {
        if (!$this->guardAdminPermissionPost('admin.orders.manage', '/admin/orders')) {
            return;
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        $confirmed = ((string) ($_POST['confirm'] ?? '0')) === '1';
        $actorId = (int) ($_SESSION['auth_user_id'] ?? 0);

        try {
            $result = (new AdminOrderStateTransitionService())->execute($id, $action, $actorId, $reason, $confirmed);
            $this->adminSuccess('Ação administrativa executada com sucesso.', '/admin/orders', $result);
        } catch (\InvalidArgumentException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/orders');
        } catch (\RuntimeException $e) {
            $this->adminError($e->getMessage(), 404, '/admin/orders');
        } catch (\Throwable $e) {
            $this->internalErrorResponse('Falha ao executar ação administrativa.', '/admin/orders');
        }
    }
}

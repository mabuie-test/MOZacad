<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\OrderRepository;

final class AdminOrderStateTransitionService
{
    private const ACTION_MAP = [
        'pause' => ['status' => 'paused_admin', 'critical' => false],
        'resume' => ['status' => 'queued', 'critical' => false],
        'escalate' => ['status' => 'under_human_review', 'critical' => true],
        'block_delivery' => ['status' => 'delivery_blocked', 'critical' => true],
        'reopen_review' => ['status' => 'under_human_review', 'critical' => true],
        'payment_dispute' => ['status' => 'payment_dispute', 'critical' => true],
        'payment_refund' => ['status' => 'refund_pending', 'critical' => true],
        'payment_cancel' => ['status' => 'cancelled_post_payment', 'critical' => true],
    ];

    public function execute(int $orderId, string $action, int $actorId, string $reason, bool $confirmed): array
    {
        $normalized = strtolower(trim($action));
        if (!isset(self::ACTION_MAP[$normalized])) {
            throw new \InvalidArgumentException('Ação administrativa inválida.');
        }

        $rule = self::ACTION_MAP[$normalized];
        if (($rule['critical'] ?? false) === true) {
            if (trim($reason) === '') {
                throw new \InvalidArgumentException('Motivo obrigatório para ações críticas.');
            }
            if ($confirmed !== true) {
                throw new \InvalidArgumentException('Confirmação obrigatória para ações críticas.');
            }
        }

        $repo = new OrderRepository();
        $order = $repo->findById($orderId);
        if ($order === null) {
            throw new \RuntimeException('Pedido não encontrado.');
        }

        $currentStatus = (string) ($order['status'] ?? '');
        $this->assertAllowedTransition($normalized, $currentStatus);

        $nextStatus = (string) $rule['status'];
        $repo->updateAdminState($orderId, $nextStatus, $this->priorityForAction($normalized));

        (new AuditLogRepository())->log(
            $actorId,
            'admin.order.' . $normalized,
            'order',
            $orderId,
            [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'reason' => trim($reason),
                'confirmed' => $confirmed,
                'critical' => (bool) ($rule['critical'] ?? false),
            ],
            'admin.orders.manage'
        );

        return ['from' => $currentStatus, 'to' => $nextStatus, 'action' => $normalized];
    }

    private function assertAllowedTransition(string $action, string $currentStatus): void
    {
        if ($action === 'resume' && $currentStatus !== 'paused_admin') {
            throw new \InvalidArgumentException('Só é possível retomar pedidos pausados.');
        }

        if (in_array($action, ['payment_dispute', 'payment_refund', 'payment_cancel'], true)
            && !in_array($currentStatus, ['queued', 'in_progress', 'under_human_review', 'ready', 'approved', 'delivery_blocked'], true)) {
            throw new \InvalidArgumentException('Fluxo pós-pagamento só pode ser iniciado após confirmação operacional.');
        }
    }

    private function priorityForAction(string $action): string
    {
        return in_array($action, ['escalate', 'block_delivery', 'reopen_review', 'payment_dispute', 'payment_refund', 'payment_cancel'], true) ? 'high' : 'normal';
    }
}


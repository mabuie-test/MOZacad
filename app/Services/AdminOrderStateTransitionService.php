<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PostPaymentExceptionRepository;

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


        if (in_array($normalized, ['payment_dispute', 'payment_refund', 'payment_cancel'], true)) {
            $this->syncPostPaymentException($orderId, $actorId, $normalized, $currentStatus, $nextStatus, trim($reason));
        }

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


    private function syncPostPaymentException(int $orderId, int $actorId, string $action, string $fromStatus, string $toStatus, string $reason): void
    {
        $category = str_replace('payment_', '', $action);
        $repo = new PostPaymentExceptionRepository();
        $existing = $repo->findOpenByOrderAndCategory($orderId, $category);

        if ($existing === null) {
            $exceptionId = $repo->create([
                'order_id' => $orderId,
                'payment_id' => null,
                'review_queue_id' => null,
                'category' => $category,
                'state' => 'open',
                'owner_user_id' => $actorId > 0 ? $actorId : null,
                'sla_due_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'escalation_level' => 1,
                'blocked_delivery' => in_array($action, ['payment_dispute', 'payment_cancel'], true) ? 1 : 0,
                'resolution_code' => null,
                'resolution_notes' => null,
                'auto_reconciled' => 0,
                'resolved_at' => null,
            ]);
            $repo->logEvent($exceptionId, $actorId > 0 ? $actorId : null, 'exception.created', [
                'order_id' => $orderId,
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'sla_due_at' => date('c', strtotime('+24 hours')),
            ]);
            return;
        }

        $exceptionId = (int) $existing['id'];
        $repo->updateState($exceptionId, 'in_review');
        $repo->assignOwner($exceptionId, $actorId > 0 ? $actorId : null);
        $repo->escalate(
            $exceptionId,
            max(1, (int) ($existing['escalation_level'] ?? 0) + 1),
            in_array($action, ['payment_dispute', 'payment_cancel'], true)
        );
        $repo->logEvent($exceptionId, $actorId > 0 ? $actorId : null, 'exception.updated', [
            'order_id' => $orderId,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'reason' => $reason,
            'owner_user_id' => $actorId,
            'escalation_level' => max(1, (int) ($existing['escalation_level'] ?? 0) + 1),
        ]);
    }
}



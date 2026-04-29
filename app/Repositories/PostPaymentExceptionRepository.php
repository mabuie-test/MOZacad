<?php

declare(strict_types=1);

namespace App\Repositories;

final class PostPaymentExceptionRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO post_payment_exceptions (
            order_id, payment_id, review_queue_id, category, state, owner_user_id, sla_due_at,
            escalation_level, blocked_delivery, resolution_code, resolution_notes, auto_reconciled, resolved_at,
            created_at, updated_at
        ) VALUES (
            :order_id, :payment_id, :review_queue_id, :category, :state, :owner_user_id, :sla_due_at,
            :escalation_level, :blocked_delivery, :resolution_code, :resolution_notes, :auto_reconciled, :resolved_at,
            NOW(), NOW()
        )');
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function findOpenByOrderAndCategory(int $orderId, string $category): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM post_payment_exceptions
            WHERE order_id = :order_id
              AND category = :category
              AND state IN ('open','in_review','awaiting_finance','awaiting_compliance')
            ORDER BY id DESC
            LIMIT 1");
        $stmt->execute(['order_id' => $orderId, 'category' => $category]);

        return $stmt->fetch() ?: null;
    }

    public function updateState(int $exceptionId, string $state, ?string $resolutionCode = null, ?string $resolutionNotes = null): void
    {
        $resolvedAt = in_array($state, ['resolved', 'cancelled'], true) ? 'NOW()' : 'NULL';
        $stmt = $this->db->prepare("UPDATE post_payment_exceptions
            SET state = :state,
                resolution_code = :resolution_code,
                resolution_notes = :resolution_notes,
                resolved_at = {$resolvedAt},
                updated_at = NOW()
            WHERE id = :id");
        $stmt->execute([
            'id' => $exceptionId,
            'state' => $state,
            'resolution_code' => $resolutionCode,
            'resolution_notes' => $resolutionNotes,
        ]);
    }

    public function assignOwner(int $exceptionId, ?int $ownerUserId): void
    {
        $stmt = $this->db->prepare('UPDATE post_payment_exceptions
            SET owner_user_id = :owner_user_id,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute(['id' => $exceptionId, 'owner_user_id' => $ownerUserId]);
    }

    public function escalate(int $exceptionId, int $nextLevel, bool $blockedDelivery): void
    {
        $stmt = $this->db->prepare('UPDATE post_payment_exceptions
            SET escalation_level = :escalation_level,
                blocked_delivery = :blocked_delivery,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute([
            'id' => $exceptionId,
            'escalation_level' => $nextLevel,
            'blocked_delivery' => $blockedDelivery ? 1 : 0,
        ]);
    }

    public function logEvent(int $exceptionId, ?int $actorId, string $eventType, array $payload): void
    {
        $stmt = $this->db->prepare('INSERT INTO post_payment_exception_events (exception_id, actor_id, event_type, payload_json, created_at)
            VALUES (:exception_id, :actor_id, :event_type, :payload_json, NOW())');
        $stmt->execute([
            'exception_id' => $exceptionId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function listWithFilters(array $filters, int $limit = 200): array
    {
        $sql = "SELECT e.*, o.title_or_theme, u.email AS owner_email
            FROM post_payment_exceptions e
            INNER JOIN orders o ON o.id = e.order_id
            LEFT JOIN users u ON u.id = e.owner_user_id
            WHERE 1=1";
        $params = [];
        $state = trim((string) ($filters['exception_state'] ?? ''));
        $owner = (int) ($filters['exception_owner'] ?? 0);
        $escalated = trim((string) ($filters['exception_escalated'] ?? ''));
        $sla = trim((string) ($filters['exception_sla'] ?? ''));
        if ($state !== '') { $sql .= ' AND e.state = :state'; $params['state'] = $state; }
        if ($owner > 0) { $sql .= ' AND e.owner_user_id = :owner'; $params['owner'] = $owner; }
        if ($escalated === 'yes') { $sql .= ' AND e.escalation_level > 0'; }
        if ($escalated === 'no') { $sql .= ' AND e.escalation_level = 0'; }
        if ($sla === 'overdue') { $sql .= ' AND e.sla_due_at IS NOT NULL AND e.sla_due_at < NOW()'; }
        if ($sla === 'due_24h') { $sql .= ' AND e.sla_due_at IS NOT NULL AND e.sla_due_at >= NOW() AND e.sla_due_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)'; }
        if ($sla === 'on_track') { $sql .= ' AND (e.sla_due_at IS NULL OR e.sla_due_at > DATE_ADD(NOW(), INTERVAL 24 HOUR))'; }
        $sql .= ' ORDER BY e.updated_at DESC LIMIT :limit';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function summarize(): array
    {
        $sql = "SELECT
            SUM(CASE WHEN state IN ('open','in_review','awaiting_finance','awaiting_compliance') THEN 1 ELSE 0 END) AS active_total,
            SUM(CASE WHEN blocked_delivery = 1 AND state IN ('open','in_review','awaiting_finance','awaiting_compliance') THEN 1 ELSE 0 END) AS blocked_delivery_total,
            SUM(CASE WHEN sla_due_at IS NOT NULL AND sla_due_at < NOW() AND state IN ('open','in_review','awaiting_finance','awaiting_compliance') THEN 1 ELSE 0 END) AS overdue_total,
            SUM(CASE WHEN escalation_level > 0 AND state IN ('open','in_review','awaiting_finance','awaiting_compliance') THEN 1 ELSE 0 END) AS escalated_total,
            SUM(CASE WHEN auto_reconciled = 1 THEN 1 ELSE 0 END) AS auto_reconciled_total
        FROM post_payment_exceptions";
        $row = $this->db->query($sql)->fetch() ?: [];
        return [
            'active_total' => (int) ($row['active_total'] ?? 0),
            'blocked_delivery_total' => (int) ($row['blocked_delivery_total'] ?? 0),
            'overdue_total' => (int) ($row['overdue_total'] ?? 0),
            'escalated_total' => (int) ($row['escalated_total'] ?? 0),
            'auto_reconciled_total' => (int) ($row['auto_reconciled_total'] ?? 0),
        ];
    }
}

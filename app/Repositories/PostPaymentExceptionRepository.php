<?php

declare(strict_types=1);

namespace App\Repositories;

final class PostPaymentExceptionRepository extends BaseRepository
{
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

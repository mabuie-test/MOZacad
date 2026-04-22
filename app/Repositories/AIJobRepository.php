<?php

declare(strict_types=1);

namespace App\Repositories;

final class AIJobRepository extends BaseRepository
{
    public function findOpenByOrderAndStage(int $orderId, string $stage): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ai_jobs WHERE order_id = :order_id AND stage = :stage AND status IN ('queued','pending','processing') ORDER BY id DESC LIMIT 1");
        $stmt->execute([
            'order_id' => $orderId,
            'stage' => $stage,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $orderId, string $stage, string $status, array $payload): int
    {
        $stmt = $this->db->prepare('INSERT INTO ai_jobs (order_id, stage, status, payload_json, created_at, updated_at) VALUES (:order_id, :stage, :status, :payload_json, NOW(), NOW())');
        $stmt->execute([
            'order_id' => $orderId,
            'stage' => $stage,
            'status' => $status,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->lastInsertId();
    }
}

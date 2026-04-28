<?php

declare(strict_types=1);

namespace App\Repositories;

final class AIJobRepository extends BaseRepository
{

    public function findLatestByOrderAndStage(int $orderId, string $stage): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM ai_jobs WHERE order_id = :order_id AND stage = :stage ORDER BY id DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId, 'stage' => $stage]);

        return $stmt->fetch() ?: null;
    }

    public function findOpenByOrderAndStage(int $orderId, string $stage): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM ai_jobs WHERE order_id = :order_id AND stage = :stage AND status IN ('queued','pending','reserved','processing','retry_wait') ORDER BY id DESC LIMIT 1");
        $stmt->execute(['order_id' => $orderId, 'stage' => $stage]);
        return $stmt->fetch() ?: null;
    }

    public function create(int $orderId, string $stage, string $status, array $payload): int
    {
        $stmt = $this->db->prepare('INSERT INTO ai_jobs (order_id, stage, status, payload_json, created_at, updated_at) VALUES (:order_id, :stage, :status, :payload_json, NOW(), NOW())');
        $stmt->execute(['order_id' => $orderId, 'stage' => $stage, 'status' => $status, 'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE)]);
        return (int) $this->db->lastInsertId();
    }

    public function reserveQueued(int $limit = 5, int $staleProcessingSeconds = 1800): array
    {
        $limit = max(1, min(100, $limit));
        $staleProcessingSeconds = max(60, $staleProcessingSeconds);
        $token = bin2hex(random_bytes(16));

        $this->db->beginTransaction();
        try {
            $sql = "UPDATE ai_jobs
                SET status = 'reserved',
                    reservation_token = :token,
                    reserved_at = NOW(),
                    updated_at = NOW()
                WHERE (
                    status IN ('queued','pending')
                    OR (status = 'retry_wait' AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
                    OR (status = 'processing' AND processing_started_at IS NOT NULL AND processing_started_at < DATE_SUB(NOW(), INTERVAL :stale_seconds SECOND))
                    OR (status = 'reserved' AND reserved_at IS NOT NULL AND reserved_at < DATE_SUB(NOW(), INTERVAL 600 SECOND))
                )
                ORDER BY created_at ASC
                LIMIT :limit";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue('token', $token);
            $stmt->bindValue('stale_seconds', $staleProcessingSeconds, \PDO::PARAM_INT);
            $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            $selected = $this->db->prepare('SELECT * FROM ai_jobs WHERE reservation_token = :token ORDER BY created_at ASC');
            $selected->execute(['token' => $token]);
            $rows = $selected->fetchAll();

            $this->db->commit();
            return $rows;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function markProcessing(int $id, string $reservationToken): bool
    {
        $stmt = $this->db->prepare("UPDATE ai_jobs
            SET status = 'processing',
                processing_started_at = NOW(),
                reservation_token = NULL,
                reserved_at = NULL,
                attempts = attempts + 1,
                updated_at = NOW()
            WHERE id = :id
              AND status = 'reserved'
              AND reservation_token = :reservation_token");
        $stmt->execute([
            'id' => $id,
            'reservation_token' => $reservationToken,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function markCompleted(int $id, array $result): void
    {
        $this->db->prepare("UPDATE ai_jobs
            SET status='completed',
                result_json=:result_json,
                error_text = NULL,
                reservation_token = NULL,
                reserved_at = NULL,
                processing_started_at = NULL,
                next_retry_at = NULL,
                updated_at=NOW()
            WHERE id=:id")
            ->execute(['id' => $id, 'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE)]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->prepare("UPDATE ai_jobs
            SET status='failed',
                error_text=:error_text,
                reservation_token = NULL,
                reserved_at = NULL,
                processing_started_at = NULL,
                next_retry_at = NULL,
                updated_at=NOW()
            WHERE id=:id")
            ->execute(['id' => $id, 'error_text' => $error]);
    }

    public function markRetryWait(int $id, string $error, int $delaySeconds): void
    {
        $delaySeconds = max(30, min(7200, $delaySeconds));
        $stmt = $this->db->prepare("UPDATE ai_jobs
            SET status = 'retry_wait',
                error_text = :error_text,
                reservation_token = NULL,
                reserved_at = NULL,
                processing_started_at = NULL,
                next_retry_at = DATE_ADD(NOW(), INTERVAL :delay_seconds SECOND),
                updated_at = NOW()
            WHERE id = :id");
        $stmt->bindValue('id', $id, \PDO::PARAM_INT);
        $stmt->bindValue('error_text', $error);
        $stmt->bindValue('delay_seconds', $delaySeconds, \PDO::PARAM_INT);
        $stmt->execute();
    }
}

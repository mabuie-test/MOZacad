<?php

declare(strict_types=1);

namespace App\Repositories;

final class HumanReviewQueueRepository extends BaseRepository
{
    public function enqueue(int $orderId, ?int $reviewerId = null): int
    {
        $stmt = $this->db->prepare("INSERT INTO human_review_queue (order_id, reviewer_id, status, created_at, updated_at) VALUES (:order_id, :reviewer_id, 'pending', NOW(), NOW())");
        $stmt->execute(['order_id' => $orderId, 'reviewer_id' => $reviewerId]);

        return (int) $this->db->lastInsertId();
    }

    public function assignReviewer(int $queueId, int $reviewerId): void
    {
        $stmt = $this->db->prepare("UPDATE human_review_queue SET reviewer_id = :reviewer_id, status = 'assigned', updated_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $queueId, 'reviewer_id' => $reviewerId]);
    }

    public function findById(int $queueId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM human_review_queue WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $queueId]);

        return $stmt->fetch() ?: null;
    }

    public function updateDecision(int $queueId, string $status, ?string $notes = null): void
    {
        $stmt = $this->db->prepare('UPDATE human_review_queue SET status = :status, decision = :decision, comments = :review_notes, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $queueId,
            'status' => $status,
            'decision' => $status,
            'review_notes' => $notes,
        ]);
    }

    public function listQueue(int $limit = 100): array
    {
        $stmt = $this->db->prepare('SELECT * FROM human_review_queue ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

final class RevisionRepository extends BaseRepository
{
    public function create(int $orderId, int $userId, string $reason, string $status = 'requested'): int
    {
        $stmt = $this->db->prepare('INSERT INTO revisions (order_id, user_id, reason, status, created_at, updated_at)
            VALUES (:order_id, :user_id, :reason, :status, NOW(), NOW())');
        $stmt->execute([
            'order_id' => $orderId,
            'user_id' => $userId,
            'reason' => $reason,
            'status' => $status,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function listByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM revisions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}

<?php

declare(strict_types=1);

namespace App\Repositories;

final class GeneratedDocumentRepository extends BaseRepository
{
    public function create(int $orderId, string $path, string $status = 'generated', int $version = 1): int
    {
        $stmt = $this->db->prepare('INSERT INTO generated_documents (order_id,file_path,status,version,created_at) VALUES (:order_id,:file_path,:status,:version,NOW())');
        $stmt->execute(['order_id' => $orderId, 'file_path' => $path, 'status' => $status, 'version' => $version]);

        return (int) $this->db->lastInsertId();
    }

    public function findLatestByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM generated_documents WHERE order_id = :order_id ORDER BY version DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function listByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT gd.*, o.user_id, o.title_or_theme
            FROM generated_documents gd
            INNER JOIN orders o ON o.id = gd.order_id
            WHERE o.user_id = :user_id
            ORDER BY gd.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listDeliverableByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT gd.*, o.user_id, o.title_or_theme
            FROM generated_documents gd
            INNER JOIN orders o ON o.id = gd.order_id
            WHERE o.user_id = :user_id
              AND o.status = 'ready'
              AND gd.status IN ('generated', 'approved')
            ORDER BY gd.created_at DESC
            LIMIT :limit");
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function updateLatestStatusByOrderId(int $orderId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE generated_documents
            SET status = :status
            WHERE id = (
                SELECT id FROM (
                    SELECT id FROM generated_documents WHERE order_id = :order_id ORDER BY version DESC LIMIT 1
                ) latest
            )');
        $stmt->execute([
            'status' => $status,
            'order_id' => $orderId,
        ]);
    }
}

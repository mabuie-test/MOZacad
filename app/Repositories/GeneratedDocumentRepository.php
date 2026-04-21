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
}

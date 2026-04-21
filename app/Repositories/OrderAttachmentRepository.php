<?php

declare(strict_types=1);

namespace App\Repositories;

final class OrderAttachmentRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO order_attachments (order_id, attachment_type, file_name, file_path, mime_type, created_at)
            VALUES (:order_id, :attachment_type, :file_name, :file_path, :mime_type, NOW())');
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function listByOrderId(int $orderId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM order_attachments WHERE order_id = :order_id ORDER BY created_at DESC');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }
}

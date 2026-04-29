<?php

declare(strict_types=1);

namespace App\Repositories;

final class GeneratedDocumentRepository extends BaseRepository
{
    public function create(int $orderId, string $path, string $status = 'generated', int $version = 1, array $templateApplication = []): int
    {
        $stmt = $this->db->prepare('INSERT INTO generated_documents (order_id,file_path,status,version,template_application_json,created_at) VALUES (:order_id,:file_path,:status,:version,:template_application_json,NOW())');
        $stmt->execute([
            'order_id' => $orderId,
            'file_path' => $path,
            'status' => $status,
            'version' => $version,
            'template_application_json' => $templateApplication === [] ? null : json_encode($templateApplication, JSON_UNESCAPED_UNICODE),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findLatestByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM generated_documents WHERE order_id = :order_id ORDER BY version DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function findLatestByOrderIdForUpdate(int $orderId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM generated_documents WHERE order_id = :order_id ORDER BY version DESC LIMIT 1 FOR UPDATE');
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }


    public function findDetailedById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT gd.*, o.user_id, o.status AS order_status, o.title_or_theme
            FROM generated_documents gd
            INNER JOIN orders o ON o.id = gd.order_id
            WHERE gd.id = :id
            LIMIT 1');
        $stmt->execute(['id' => $id]);

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
        // Mesma política do download final: `approved` permanece temporariamente
        // para retrocompatibilidade até concluir a migração para `final_approved`.
        $stmt = $this->db->prepare("SELECT gd.*, o.user_id, o.title_or_theme
            FROM generated_documents gd
            INNER JOIN orders o ON o.id = gd.order_id
            WHERE o.user_id = :user_id
              AND o.status = 'ready'
              AND gd.status IN ('final_approved', 'approved')
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

    public function isLatestVersion(int $documentId, int $orderId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM generated_documents WHERE order_id = :order_id ORDER BY version DESC LIMIT 1');
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        return is_array($row) && (int) ($row['id'] ?? 0) === $documentId;
    }
}

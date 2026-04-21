<?php
declare(strict_types=1);
namespace App\Repositories;
final class NotificationRepository extends BaseRepository
{
    public function create(int $userId, string $title, string $body, string $type = 'info'): int
    {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, title, message, created_at)
            VALUES (:user_id, :title, :message, NOW())');
        $stmt->execute(['user_id' => $userId, 'title' => $title, 'message' => '[' . $type . '] ' . $body]);
        return (int) $this->db->lastInsertId();
    }

    public function listByUser(int $userId, int $limit = 30): array
    {
        $stmt = $this->db->prepare('SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

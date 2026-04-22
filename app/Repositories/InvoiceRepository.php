<?php

declare(strict_types=1);

namespace App\Repositories;

final class InvoiceRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO invoices (user_id, order_id, invoice_number, amount, currency, status, issued_at, created_at, updated_at)
            VALUES (:user_id,:order_id,:invoice_number,:amount,:currency,:status,NOW(),NOW(),NOW())');
        $stmt->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function markPaidById(int $id): void
    {
        $this->markStatusById($id, 'paid', true);
    }

    public function markStatusById(int $id, string $status, bool $markPaidAt = false): void
    {
        $sql = 'UPDATE invoices SET status = :status, updated_at = NOW()';
        if ($markPaidAt || $status === 'paid') {
            $sql .= ', paid_at = NOW()';
        }
        $sql .= ' WHERE id = :id';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function findOpenByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE order_id = :order_id AND status IN ('issued','pending') ORDER BY id DESC LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function listByUser(int $userId, int $limit = 50): array
    {
        $stmt = $this->db->prepare('SELECT * FROM invoices WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

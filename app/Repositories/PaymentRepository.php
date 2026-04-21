<?php

declare(strict_types=1);

namespace App\Repositories;

final class PaymentRepository extends BaseRepository
{
    public function create(array $data): int
    {
        $sql = 'INSERT INTO payments (user_id, order_id, invoice_id, provider, method, amount, currency, msisdn, status, internal_reference, created_at, updated_at)
                VALUES (:user_id,:order_id,:invoice_id,:provider,:method,:amount,:currency,:msisdn,:status,:internal_reference,NOW(),NOW())';
        $this->db->prepare($sql)->execute($data);
        return (int) $this->db->lastInsertId();
    }

    public function findPendingForPolling(int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE status IN ('pending','processing','pending_confirmation') LIMIT :limit");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function markPaid(int $id, string $providerStatus): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, provider_status = :provider_status, paid_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'paid', 'provider_status' => $providerStatus, 'id' => $id]);
    }

    public function updateStatus(int $id, string $status, string $providerStatus, ?string $message = null): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, provider_status = :provider_status, status_message = :status_message, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'provider_status' => $providerStatus, 'status_message' => $message, 'id' => $id]);
    }
}

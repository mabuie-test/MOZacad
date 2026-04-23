<?php

declare(strict_types=1);

namespace App\Repositories;

final class PaymentRepository extends BaseRepository
{
    public function listAll(int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT p.*, u.email AS user_email
            FROM payments p
            INNER JOIN users u ON u.id = p.user_id
            ORDER BY p.created_at DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $sql = 'INSERT INTO payments (user_id, order_id, invoice_id, provider, method, amount, currency, msisdn, status, internal_reference, created_at, updated_at)
                VALUES (:user_id,:order_id,:invoice_id,:provider,:method,:amount,:currency,:msisdn,:status,:internal_reference,NOW(),NOW())';
        $this->db->prepare($sql)->execute($data);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function lockByIdForUpdate(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByExternalReference(string $reference): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE external_reference = :reference LIMIT 1');
        $stmt->execute(['reference' => $reference]);

        return $stmt->fetch() ?: null;
    }



    public function findByInternalReference(string $reference): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE internal_reference = :reference LIMIT 1');
        $stmt->execute(['reference' => $reference]);

        return $stmt->fetch() ?: null;
    }

    public function findOpenByOrderId(int $orderId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = :order_id AND status IN ('pending','processing','pending_confirmation') ORDER BY id DESC LIMIT 1");
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }

    public function findOpenByOrderIdForUpdate(int $orderId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments WHERE order_id = :order_id AND status IN ('pending','processing','pending_confirmation') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetch() ?: null;
    }
    public function listRecentByUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare('SELECT * FROM payments WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findPendingForPolling(int $limit = 50): array
    {
        $stmt = $this->db->prepare("SELECT * FROM payments
            WHERE status IN ('pending','processing','pending_confirmation')
              AND external_reference IS NOT NULL
              AND external_reference <> ''
            ORDER BY updated_at ASC, id ASC
            LIMIT :limit");
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function markPaid(int $id, string $providerStatus): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, provider_status = :provider_status, paid_at = NOW(), updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => 'paid', 'provider_status' => $providerStatus, 'id' => $id]);
    }

    public function setExternalReference(int $id, string $externalReference, ?string $providerTransactionId = null, ?string $providerStatus = null): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET external_reference = :external_reference, provider_transaction_id = :provider_transaction_id,
                provider_status = :provider_status, updated_at = NOW() WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'external_reference' => $externalReference,
            'provider_transaction_id' => $providerTransactionId,
            'provider_status' => $providerStatus,
        ]);
    }

    public function updateStatus(int $id, string $status, string $providerStatus, ?string $message = null): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET status = :status, provider_status = :provider_status, status_message = :status_message, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['status' => $status, 'provider_status' => $providerStatus, 'status_message' => $message, 'id' => $id]);
    }
}

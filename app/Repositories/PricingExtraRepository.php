<?php

declare(strict_types=1);

namespace App\Repositories;

final class PricingExtraRepository extends BaseRepository
{
    public function all(int $limit = 300): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pricing_extras ORDER BY extra_code ASC LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function upsert(string $extraCode, string $name, float $amount, bool $isActive = true): void
    {
        $stmt = $this->db->prepare('INSERT INTO pricing_extras (extra_code, name, amount, is_active, created_at, updated_at)
            VALUES (:extra_code, :name, :amount, :is_active, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                amount = VALUES(amount),
                is_active = VALUES(is_active),
                updated_at = NOW()');

        $stmt->execute([
            'extra_code' => trim($extraCode),
            'name' => trim($name),
            'amount' => $amount,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }
}

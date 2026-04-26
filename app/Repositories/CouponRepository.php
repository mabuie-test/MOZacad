<?php

declare(strict_types=1);

namespace App\Repositories;

final class CouponRepository extends BaseRepository
{
    public function findActiveByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM coupons WHERE code = :code AND is_active = 1
            AND (starts_at IS NULL OR starts_at <= NOW())
            AND (ends_at IS NULL OR ends_at >= NOW())
            AND (usage_limit IS NULL OR used_count < usage_limit)
            LIMIT 1");
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function allWithUsage(int $limit = 200): array
    {
        $stmt = $this->db->prepare('SELECT c.*, COUNT(cul.id) AS usage_logs_count
            FROM coupons c
            LEFT JOIN coupon_usage_logs cul ON cul.coupon_id = c.id
            GROUP BY c.id
            ORDER BY c.id DESC
            LIMIT :limit');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO coupons (code, discount_type, discount_value, starts_at, ends_at, usage_limit, used_count, is_active)
            VALUES (:code, :discount_type, :discount_value, :starts_at, :ends_at, :usage_limit, 0, :is_active)');
        $stmt->execute([
            'code' => $data['code'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons
            SET code = :code,
                discount_type = :discount_type,
                discount_value = :discount_value,
                starts_at = :starts_at,
                ends_at = :ends_at,
                usage_limit = :usage_limit,
                is_active = :is_active
            WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'code' => $data['code'],
            'discount_type' => $data['discount_type'],
            'discount_value' => $data['discount_value'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'is_active' => $data['is_active'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function setActive(int $id, bool $active): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons SET is_active = :is_active WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'is_active' => $active ? 1 : 0,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function findActiveByCodeForUpdate(string $code): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM coupons WHERE code = :code AND is_active = 1
            AND (starts_at IS NULL OR starts_at <= NOW())
            AND (ends_at IS NULL OR ends_at >= NOW())
            AND (usage_limit IS NULL OR used_count < usage_limit)
            LIMIT 1 FOR UPDATE");
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    public function reserveUsageById(int $couponId): bool
    {
        $stmt = $this->db->prepare('UPDATE coupons
            SET used_count = used_count + 1
            WHERE id = :id
              AND is_active = 1
              AND (usage_limit IS NULL OR used_count < usage_limit)');
        $stmt->execute(['id' => $couponId]);

        return $stmt->rowCount() > 0;
    }

    public function reserveUsageByCode(string $code): ?array
    {
        $this->db->beginTransaction();
        try {
            $coupon = $this->findActiveByCode($code);
            if ($coupon === null) {
                $this->db->commit();
                return null;
            }

            if (!$this->reserveUsageById((int) $coupon['id'])) {
                $this->db->rollBack();
                return null;
            }

            $this->db->commit();
            $coupon['used_count'] = ((int) ($coupon['used_count'] ?? 0)) + 1;
            return $coupon;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}

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

    public function reserveUsageByCode(string $code): ?array
    {
        $this->db->beginTransaction();
        try {
            $coupon = $this->findActiveByCode($code);
            if ($coupon === null) {
                $this->db->commit();
                return null;
            }

            $stmt = $this->db->prepare('UPDATE coupons
                SET used_count = used_count + 1
                WHERE id = :id
                  AND is_active = 1
                  AND (usage_limit IS NULL OR used_count < usage_limit)');
            $stmt->execute(['id' => (int) $coupon['id']]);

            if ($stmt->rowCount() < 1) {
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

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
}

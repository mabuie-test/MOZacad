<?php

declare(strict_types=1);

namespace App\Repositories;

final class CouponUsageRepository extends BaseRepository
{
    public function findByOrderAndCouponForUpdate(int $orderId, int $couponId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupon_usage_logs WHERE order_id = :order_id AND coupon_id = :coupon_id LIMIT 1 FOR UPDATE');
        $stmt->execute([
            'order_id' => $orderId,
            'coupon_id' => $couponId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function create(int $orderId, int $couponId, ?int $userId, string $couponCode): void
    {
        $stmt = $this->db->prepare('INSERT INTO coupon_usage_logs (order_id, coupon_id, user_id, coupon_code, created_at)
            VALUES (:order_id, :coupon_id, :user_id, :coupon_code, NOW())');
        $stmt->execute([
            'order_id' => $orderId,
            'coupon_id' => $couponId,
            'user_id' => $userId,
            'coupon_code' => $couponCode,
        ]);
    }
}

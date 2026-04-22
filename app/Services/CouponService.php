<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\CouponRepository;
use App\Repositories\CouponUsageRepository;

final class CouponService
{
    public function __construct(
        private readonly CouponRepository $coupons = new CouponRepository(),
        private readonly CouponUsageRepository $usage = new CouponUsageRepository(),
    ) {}

    public function apply(?string $couponCode, float $subtotal, bool $consume = false, ?int $orderId = null, ?int $userId = null): array
    {
        $code = strtoupper(trim((string) $couponCode));
        if ($code === '' || $subtotal <= 0) {
            return ['amount' => 0.0, 'coupon' => null];
        }

        $coupon = $consume
            ? $this->consumeForOrder($code, $orderId, $userId)
            : $this->coupons->findActiveByCode($code);
        if ($coupon === null) {
            return ['amount' => 0.0, 'coupon' => null];
        }

        $amount = 0.0;
        if ($coupon['discount_type'] === 'percent') {
            $amount = $subtotal * ((float) $coupon['discount_value'] / 100);
        } elseif ($coupon['discount_type'] === 'fixed') {
            $amount = min((float) $coupon['discount_value'], $subtotal);
        }

        return [
            'amount' => round(max(0.0, $amount), 2),
            'coupon' => $coupon,
        ];
    }

    private function consumeForOrder(string $code, ?int $orderId, ?int $userId): ?array
    {
        if (($orderId ?? 0) <= 0) {
            return $this->coupons->reserveUsageByCode($code);
        }

        $db = Database::connect();
        $db->beginTransaction();
        try {
            $coupon = $this->coupons->findActiveByCodeForUpdate($code);
            if ($coupon === null) {
                $db->commit();
                return null;
            }

            $existing = $this->usage->findByOrderAndCouponForUpdate($orderId, (int) $coupon['id']);
            if ($existing !== null) {
                $db->commit();
                return $coupon;
            }

            $reserved = $this->coupons->reserveUsageById((int) $coupon['id']);
            if (!$reserved) {
                $db->rollBack();
                return null;
            }

            $this->usage->create(
                $orderId,
                (int) $coupon['id'],
                $userId,
                $code
            );

            $db->commit();
            $coupon['used_count'] = ((int) ($coupon['used_count'] ?? 0)) + 1;
            return $coupon;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

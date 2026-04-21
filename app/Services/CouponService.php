<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CouponRepository;

final class CouponService
{
    public function __construct(private readonly CouponRepository $coupons = new CouponRepository()) {}

    public function apply(?string $couponCode, float $subtotal): array
    {
        $code = strtoupper(trim((string) $couponCode));
        if ($code === '' || $subtotal <= 0) {
            return ['amount' => 0.0, 'coupon' => null];
        }

        $coupon = $this->coupons->findActiveByCode($code);
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
}

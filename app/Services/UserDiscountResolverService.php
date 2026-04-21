<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserDiscountRepository;

final class UserDiscountResolverService
{
    public function __construct(private readonly UserDiscountRepository $repo = new UserDiscountRepository()) {}

    public function resolve(int $userId, ?int $workTypeId, float $subtotal, array $extras): ?array
    {
        $discounts = $this->repo->findEligible($userId, $workTypeId);
        if ($discounts === []) {
            return null;
        }

        $best = null;
        $bestAmount = 0.0;

        foreach ($discounts as $discount) {
            $amount = $this->previewAmount($discount, $subtotal, $extras);
            if ($amount > $bestAmount) {
                $bestAmount = $amount;
                $best = $discount;
            }
        }

        return $best;
    }

    private function previewAmount(array $discount, float $subtotal, array $extras): float
    {
        return match ($discount['discount_type']) {
            'percent' => $subtotal * ((float) $discount['discount_value'] / 100),
            'fixed' => min((float) $discount['discount_value'], $subtotal),
            'extra_waiver' => (float) ($extras[(string) ($discount['extra_code'] ?? '')] ?? 0),
            default => 0.0,
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserDiscountRepository;

final class DiscountService
{
    public function __construct(
        private readonly UserDiscountResolverService $resolver = new UserDiscountResolverService(),
        private readonly DiscountUsageLoggerService $logger = new DiscountUsageLoggerService(),
        private readonly UserDiscountRepository $repo = new UserDiscountRepository(),
    ) {}

    public function apply(int $userId, int $orderId, int $workTypeId, float $subtotal, array $extras): array
    {
        $discount = $this->resolver->resolve($userId, $workTypeId, $subtotal, $extras);
        if (!$discount) {
            return ['amount' => 0.0, 'discount' => null, 'extras' => $extras];
        }

        $amount = 0.0;
        if ($discount['discount_type'] === 'percent') {
            $amount = $subtotal * ((float) $discount['discount_value'] / 100);
        } elseif ($discount['discount_type'] === 'fixed') {
            $amount = min((float) $discount['discount_value'], $subtotal);
        } elseif ($discount['discount_type'] === 'extra_waiver' && !empty($discount['extra_code'])) {
            $code = (string) $discount['extra_code'];
            if (isset($extras[$code])) {
                $amount = (float) $extras[$code];
                $extras[$code] = 0.0;
            }
        }

        $amount = round(max(0.0, min($amount, $subtotal)), 2);

        if ($amount > 0) {
            $reserved = $this->repo->incrementUsage((int) $discount['id']);
            if (!$reserved) {
                return ['amount' => 0.0, 'discount' => null, 'extras' => $extras];
            }
            $this->logger->log((int) $discount['id'], $userId, $orderId, $amount, [
                'type' => $discount['discount_type'],
                'name' => $discount['name'] ?? null,
            ]);
        }

        return ['amount' => $amount, 'discount' => $discount, 'extras' => $extras];
    }
}

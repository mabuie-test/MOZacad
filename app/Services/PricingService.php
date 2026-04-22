<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\PricingBreakdownDTO;

final class PricingService
{
    public function __construct(
        private readonly PricingConfig $config = new PricingConfig(),
        private readonly ExtraPricingService $extraPricing = new ExtraPricingService(),
        private readonly DiscountService $discountService = new DiscountService(),
        private readonly CouponService $couponService = new CouponService(),
    ) {}

    public function calculate(array $context): PricingBreakdownDTO
    {
        $base = $this->config->basePriceBySlug((string) $context['work_type_slug']);
        $included = (int) $this->config->get('PRICING_INCLUDED_PAGES_DEFAULT', 10);
        $perPage = (float) $this->config->get('PRICING_PER_PAGE_DEFAULT', 40);
        $extraPages = max(0, (int) $context['target_pages'] - $included);
        $extraPagesAmount = $extraPages * $perPage;

        $levelMultiplier = (float) ($context['academic_level_multiplier'] ?? 1);
        $complexityMultiplier = (float) ($context['complexity_multiplier'] ?? 1);
        $urgencyMultiplier = (float) ($context['urgency_multiplier'] ?? 1);

        $extraLines = $this->extraPricing->calculate($context['extras'] ?? []);
        $extrasAmount = (float) array_sum($extraLines);

        $humanReviewFee = !empty($context['requires_human_review'])
            ? (float) $this->config->get('PRICING_HUMAN_REVIEW_MONOGRAFIA', 1500)
            : 0.0;

        $subtotal = (($base + $extraPagesAmount) * $levelMultiplier * $complexityMultiplier * $urgencyMultiplier) + $extrasAmount + $humanReviewFee;

        $couponResult = $this->couponService->apply(
            (string) ($context['coupon_code'] ?? ''),
            $subtotal,
            (bool) ($context['consume_coupon'] ?? false),
            isset($context['order_id']) ? (int) $context['order_id'] : null,
            isset($context['user_id']) ? (int) $context['user_id'] : null
        );
        $couponDiscount = (float) $couponResult['amount'];

        $discountResult = $this->discountService->apply(
            (int) $context['user_id'],
            (int) $context['order_id'],
            (int) $context['work_type_id'],
            max(0.0, $subtotal - $couponDiscount),
            $extraLines
        );

        $adjustedExtrasAmount = (float) array_sum($discountResult['extras']);
        $userDiscount = (float) $discountResult['amount'];

        $minimum = (float) $this->config->get('PRICING_MIN_ORDER_AMOUNT', 500);
        $final = max($minimum, $subtotal - $couponDiscount - $userDiscount);

        return new PricingBreakdownDTO(
            baseAmount: round($base, 2),
            includedPages: $included,
            extraPagesCount: $extraPages,
            extraPagesAmount: round($extraPagesAmount, 2),
            academicLevelMultiplier: $levelMultiplier,
            complexityMultiplier: $complexityMultiplier,
            urgencyMultiplier: $urgencyMultiplier,
            extrasAmount: round($adjustedExtrasAmount, 2),
            humanReviewFee: round($humanReviewFee, 2),
            couponDiscountAmount: round($couponDiscount, 2),
            userDiscountAmount: round($userDiscount, 2),
            finalTotal: round($final, 2)
        );
    }
}

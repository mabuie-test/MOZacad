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
    ) {}

    public function calculate(array $context): PricingBreakdownDTO
    {
        $base = $this->config->basePriceBySlug($context['work_type_slug']);
        $included = (int) $this->config->get('PRICING_INCLUDED_PAGES_DEFAULT', 10);
        $perPage = (float) $this->config->get('PRICING_PER_PAGE_DEFAULT', 40);
        $extraPages = max(0, (int)$context['target_pages'] - $included);
        $extraPagesAmount = $extraPages * $perPage;

        $levelMultiplier = (float)($context['academic_level_multiplier'] ?? 1);
        $complexityMultiplier = (float)($context['complexity_multiplier'] ?? 1);
        $urgencyMultiplier = (float)($context['urgency_multiplier'] ?? 1);

        $extras = $this->extraPricing->calculate($context['extras'] ?? []);
        $extrasAmount = array_sum($extras);

        $humanReviewFee = !empty($context['requires_human_review'])
            ? (float)$this->config->get('PRICING_HUMAN_REVIEW_MONOGRAFIA', 1500)
            : 0.0;

        $subtotal = (($base + $extraPagesAmount) * $levelMultiplier * $complexityMultiplier * $urgencyMultiplier) + $extrasAmount + $humanReviewFee;

        $couponDiscount = (float)($context['coupon_discount_amount'] ?? 0);
        $discountResult = $this->discountService->apply((int)$context['user_id'], (int)$context['order_id'], (int)$context['work_type_id'], $subtotal, $extras);
        $userDiscount = $discountResult['amount'];

        $minimum = (float)$this->config->get('PRICING_MIN_ORDER_AMOUNT', 500);
        $final = max($minimum, $subtotal - $couponDiscount - $userDiscount);

        return new PricingBreakdownDTO(
            baseAmount: round($base, 2),
            includedPages: $included,
            extraPagesCount: $extraPages,
            extraPagesAmount: round($extraPagesAmount, 2),
            academicLevelMultiplier: $levelMultiplier,
            complexityMultiplier: $complexityMultiplier,
            urgencyMultiplier: $urgencyMultiplier,
            extrasAmount: round($extrasAmount, 2),
            humanReviewFee: round($humanReviewFee, 2),
            couponDiscountAmount: round($couponDiscount, 2),
            userDiscountAmount: round($userDiscount, 2),
            finalTotal: round($final, 2)
        );
    }
}

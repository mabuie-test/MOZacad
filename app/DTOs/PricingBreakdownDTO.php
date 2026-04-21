<?php

declare(strict_types=1);

namespace App\DTOs;

final class PricingBreakdownDTO
{
    public function __construct(
        public readonly float $baseAmount,
        public readonly int $includedPages,
        public readonly int $extraPagesCount,
        public readonly float $extraPagesAmount,
        public readonly float $academicLevelMultiplier,
        public readonly float $complexityMultiplier,
        public readonly float $urgencyMultiplier,
        public readonly float $extrasAmount,
        public readonly float $humanReviewFee,
        public readonly float $couponDiscountAmount,
        public readonly float $userDiscountAmount,
        public readonly float $finalTotal,
    ) {}

    public function toArray(): array
    {
        return [
            'base_amount' => $this->baseAmount,
            'included_pages' => $this->includedPages,
            'extra_pages_count' => $this->extraPagesCount,
            'extra_pages_amount' => $this->extraPagesAmount,
            'academic_level_multiplier' => $this->academicLevelMultiplier,
            'complexity_multiplier' => $this->complexityMultiplier,
            'urgency_multiplier' => $this->urgencyMultiplier,
            'extras_amount' => $this->extrasAmount,
            'human_review_fee' => $this->humanReviewFee,
            'coupon_discount_amount' => $this->couponDiscountAmount,
            'user_discount_amount' => $this->userDiscountAmount,
            'final_total' => $this->finalTotal,
        ];
    }
}

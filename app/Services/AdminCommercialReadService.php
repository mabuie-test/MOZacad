<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CouponRepository;
use App\Repositories\PricingExtraRepository;
use App\Repositories\PricingRuleRepository;
use App\Repositories\UserDiscountRepository;

final class AdminCommercialReadService
{
    public function load(string $section): array
    {
        return [
            'discounts' => in_array($section, ['overview', 'discounts'], true) ? (new UserDiscountRepository())->listAll(300) : [],
            'pricingRules' => in_array($section, ['overview', 'pricing'], true) ? (new PricingRuleRepository())->all(300) : [],
            'pricingExtras' => in_array($section, ['overview', 'pricing'], true) ? (new PricingExtraRepository())->all(300) : [],
            'coupons' => in_array($section, ['overview', 'coupons'], true) ? (new CouponRepository())->allWithUsage(200) : [],
            'pricingConfig' => [
                'currency' => (new PricingConfig())->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => (new PricingConfig())->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => (new PricingConfig())->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => (new PricingConfig())->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
        ];
    }
}

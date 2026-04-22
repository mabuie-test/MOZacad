<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\PricingExtraRepository;
use App\Repositories\PricingRuleRepository;

final class AdminPricingService
{
    public function __construct(
        private readonly PricingRuleRepository $rules = new PricingRuleRepository(),
        private readonly PricingExtraRepository $extras = new PricingExtraRepository(),
        private readonly AuditLogRepository $audit = new AuditLogRepository(),
    ) {}

    public function upsertRule(int $actorId, string $ruleCode, string $ruleValue, ?string $description, bool $isActive): void
    {
        $this->rules->upsert($ruleCode, $ruleValue, $description, $isActive);
        $this->audit->log($actorId, 'admin.pricing_rule.upsert', 'pricing_rule', null, ['rule_code' => $ruleCode]);
    }

    public function upsertExtra(int $actorId, string $extraCode, string $name, float $amount, bool $isActive): void
    {
        $this->extras->upsert($extraCode, $name, $amount, $isActive);
        $this->audit->log($actorId, 'admin.pricing_extra.upsert', 'pricing_extra', null, ['extra_code' => $extraCode]);
    }
}

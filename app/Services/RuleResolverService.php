<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ResolvedRuleSetDTO;

final class RuleResolverService
{
    public function resolve(array $institutionRules, array $workTypeRules, array $academicLevelRules): ResolvedRuleSetDTO
    {
        return new ResolvedRuleSetDTO(
            visualRules: array_replace($institutionRules['visual'] ?? [], $workTypeRules['visual'] ?? [], $academicLevelRules['visual'] ?? []),
            referenceRules: array_replace($institutionRules['references'] ?? [], $workTypeRules['references'] ?? [], $academicLevelRules['references'] ?? []),
            structureRules: array_replace($institutionRules['structure'] ?? [], $workTypeRules['structure'] ?? [], $academicLevelRules['structure'] ?? []),
            meta: ['resolved_at' => date('c')]
        );
    }
}

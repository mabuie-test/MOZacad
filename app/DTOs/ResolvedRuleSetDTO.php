<?php

declare(strict_types=1);

namespace App\DTOs;

final class ResolvedRuleSetDTO
{
    public function __construct(
        public readonly array $visualRules,
        public readonly array $referenceRules,
        public readonly array $structureRules,
        public readonly array $meta = [],
    ) {}
}

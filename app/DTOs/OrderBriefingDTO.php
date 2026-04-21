<?php

declare(strict_types=1);

namespace App\DTOs;

final class OrderBriefingDTO
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $title,
        public readonly ?string $problem,
        public readonly ?string $generalObjective,
        public readonly array $specificObjectives,
        public readonly array $keywords,
        public readonly array $extras,
        public readonly array $raw = []
    ) {}
}

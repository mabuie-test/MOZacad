<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class ReferenceEntryDTO
{
    public function __construct(
        public string $signalType,
        public string $signalValue,
        public string $formatted,
        public bool $requiresManualReview,
        public string $completeness,
        public array $source,
    ) {}

    public function toArray(): array
    {
        return [
            'signal_type' => $this->signalType,
            'signal_value' => $this->signalValue,
            'formatted' => $this->formatted,
            'requires_manual_completion' => $this->requiresManualReview,
            'completeness' => $this->completeness,
            'source' => $this->source,
        ];
    }
}

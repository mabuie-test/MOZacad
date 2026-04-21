<?php

declare(strict_types=1);

namespace App\Services;

interface AIProviderInterface
{
    public function generate(string $prompt, array $context = []): string;

    public function refine(string $text, array $rules = []): string;

    public function humanize(string $text, string $profile = 'academic_humanized_pt_mz'): string;

    /**
     * @param array<string,mixed> $schema JSON Schema (draft-07 compatible subset)
     * @return array<string,mixed>
     */
    public function generateStructured(string $prompt, array $schema): array;
}

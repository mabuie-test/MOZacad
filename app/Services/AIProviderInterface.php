<?php

declare(strict_types=1);

namespace App\Services;

interface AIProviderInterface
{
    public function generate(string $prompt): string;
    public function refine(string $text, array $rules = []): string;
}

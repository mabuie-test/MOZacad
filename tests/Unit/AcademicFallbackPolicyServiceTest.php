<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/AcademicFallbackPolicyService.php';

use App\Services\AcademicFallbackPolicyService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new AcademicFallbackPolicyService();

$sections = [
    ['code' => 'desenvolvimento', 'title' => 'Desenvolvimento', 'content' => 'Texto curto sem densidade e sem citações.'],
];
$briefing = [
    'title' => 'Impacto da educação digital no ensino superior',
    'problem' => 'baixa integração pedagógica de plataformas digitais',
];

try {
    $service->ensureSubstantiveDevelopment(
        $sections,
        $briefing,
        'APA',
        static fn (array $currentSections): bool => false,
        static fn (array $currentBriefing, string $referenceStyle): array => ['Newitt (1995)'],
        static fn (string $line): string => '(Newitt, 1995)',
        static fn (array $section): string => mb_strtolower((string) ($section['code'] ?? ''))
    );
    throw new RuntimeException('Esperava falha honesta para tema não relacionado.');
} catch (RuntimeException $e) {
    assertTrue(
        str_contains($e->getMessage(), 'desenvolvimento insuficiente'),
        'Tema não relacionado deve falhar honestamente sem fallback colonial.'
    );
}

echo "AcademicFallbackPolicyService theme confidence tests passed.\n";

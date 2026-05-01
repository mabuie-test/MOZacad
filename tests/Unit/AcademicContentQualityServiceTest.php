<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/AcademicContentQualityService.php';

use App\Services\AcademicContentQualityService;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new AcademicContentQualityService();

$blueprint = [
    'sections' => [
        [
            'code' => 'desenvolvimento',
            'title' => 'Desenvolvimento',
            'min_words' => 12,
            'max_words' => 12,
        ],
    ],
];

$sectionWithinRange = [
    'code' => 'desenvolvimento',
    'title' => 'Desenvolvimento',
    'content' => 'A análise da educação técnico-científica integra método teórico-prático e ação.',
];

$withinRangeResult = $service->validateSection($sectionWithinRange, [], $blueprint);
assertTrue(
    !array_filter($withinRangeResult['issues'], static fn (string $issue): bool => str_starts_with($issue, 'length.')),
    'Texto com acentos e termos hifenizados deve respeitar o intervalo de palavras.'
);

$sectionBelowMin = [
    'code' => 'desenvolvimento',
    'title' => 'Desenvolvimento',
    'content' => 'Educação técnico-científica exige ação teórico-prática contínua.',
];

$belowMinResult = $service->validateSection($sectionBelowMin, [], $blueprint);
assertTrue(
    in_array('length.below_min:8/12', $belowMinResult['issues'], true),
    'Contagem Unicode deve identificar abaixo do mínimo com termos compostos e acentuação.'
);

$sectionAboveMax = [
    'code' => 'desenvolvimento',
    'title' => 'Desenvolvimento',
    'content' => 'A revisão técnico-científica em educação pública inclui análise qualitativa, validação empírica e síntese comparativa robusta.',
];

$aboveMaxResult = $service->validateSection($sectionAboveMax, [], $blueprint);
assertTrue(
    in_array('length.above_max:16/12', $aboveMaxResult['issues'], true),
    'Contagem Unicode deve identificar acima do máximo em texto com termos técnicos compostos.'
);

echo "AcademicContentQualityService tests passed.\n";

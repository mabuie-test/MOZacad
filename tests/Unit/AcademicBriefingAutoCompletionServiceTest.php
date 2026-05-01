<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/AcademicBriefingAutoCompletionService.php';

use App\Services\AcademicBriefingAutoCompletionService;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

$service = new AcademicBriefingAutoCompletionService();

$baseOrder = ['topic' => 'educação inclusiva em Moçambique'];

$shortGeneral = [
    'general_objective' => 'Analisar educação em ação já',
];

$shortResult = $service->complete($baseOrder, $shortGeneral, []);
assertSameValue(
    'Analisar educação inclusiva em Moçambique, considerando políticas, actores institucionais, desigualdades de acesso e legados sociais.',
    $shortResult['general_objective'],
    'Objetivo geral com menos de 6 palavras unicode deve ser substituído.'
);

$minThresholdGeneral = [
    'general_objective' => 'Analisar a educação pública em ação comunitária',
];

$thresholdResult = $service->complete($baseOrder, $minThresholdGeneral, []);
assertSameValue(
    'Analisar a educação pública em ação comunitária',
    $thresholdResult['general_objective'],
    'Objetivo geral com 6 palavras unicode (acentos incluídos) deve ser mantido.'
);

echo "AcademicBriefingAutoCompletionService tests passed.\n";

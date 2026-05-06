<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Jobs/GenerateOrderDocumentJob.php';

use App\Jobs\GenerateOrderDocumentJob;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$job = new GenerateOrderDocumentJob();
$briefing = [
    'title' => 'Impacto da educação digital no ensino superior',
    'problem' => 'baixa integração pedagógica de plataformas digitais',
    'generalObjective' => 'analisar estratégias para integração pedagógica digital',
    'keywords' => ['educação digital', 'ensino superior', 'inovação pedagógica'],
];

$method = new ReflectionMethod(GenerateOrderDocumentJob::class, 'ensureAcademicAbstract');
$method->setAccessible(true);

$sectionsWithWeakAbstract = [
    ['code' => 'resumo', 'title' => 'Resumo', 'content' => 'Resumo genérico e superficial.'],
];

/** @var array<int, array<string, string>> $updated */
$updated = $method->invoke($job, $sectionsWithWeakAbstract, $briefing);
$summary = (string) ($updated[0]['content'] ?? '');
$normalized = mb_strtolower($summary);

assertTrue(str_contains($normalized, 'tema:'), 'Resumo final deve explicitar o tema.');
assertTrue(str_contains($normalized, 'problema:'), 'Resumo final deve explicitar o problema.');
assertTrue(str_contains($normalized, 'objectivo geral:'), 'Resumo final deve explicitar o objectivo geral.');
assertTrue(str_contains($normalized, 'metodologia:'), 'Resumo final deve explicitar a metodologia.');
assertTrue(str_contains($normalized, 'síntese dos principais achados:'), 'Resumo final deve explicitar a síntese dos principais achados.');
assertTrue(str_contains($normalized, 'palavras-chave:'), 'Resumo final deve explicitar as palavras-chave.');
assertTrue(str_contains($normalized, 'abordagem qualitativa documental e analítico-interpretativa'), 'Resumo final deve usar fallback metodológico seguro.');

echo "GenerateOrderDocumentJob academic abstract fallback tests passed.\n";

<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Domain/Academic/QualityThresholds.php';
require __DIR__ . '/../../app/Jobs/GenerateOrderDocumentJob.php';

use App\Domain\Academic\QualityThresholds;
use App\Jobs\GenerateOrderDocumentJob;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$job = new GenerateOrderDocumentJob();
$briefing = [
    'problem' => 'integração de tecnologias digitais no ensino superior',
    'generalObjective' => 'avaliar estratégias pedagógicas para integração tecnológica',
];

$method = new ReflectionMethod(GenerateOrderDocumentJob::class, 'ensureSubstantiveMethodology');
$method->setAccessible(true);

$threshold = QualityThresholds::MIN_METHODOLOGY_WORDS;
$baseMethodology = 'A abordagem metodológica adota método qualitativo com procedimentos organizados em etapas. Este desenho responde ao problema de integração de tecnologias digitais no ensino superior e ao objetivo de avaliar estratégias pedagógicas para integração tecnológica.';
$baseWords = array_values(array_filter(preg_split('/\s+/u', trim($baseMethodology)) ?: [], static fn (string $word): bool => $word !== ''));
$fillerWord = 'complementar';
$missingWords = $threshold - count($baseWords);
$methodologyAtThreshold = $baseMethodology;
if ($missingWords > 0) {
    $methodologyAtThreshold .= ' ' . implode(' ', array_fill(0, $missingWords, $fillerWord));
}
$sections = [
    ['code' => 'metodologia', 'title' => 'Metodologia', 'content' => $methodologyAtThreshold],
];

/** @var array<int, array<string, string>> $updated */
$updated = $method->invoke($job, $sections, $briefing);
$updatedContent = (string) ($updated[0]['content'] ?? '');

assertTrue($updatedContent === trim($methodologyAtThreshold), 'Metodologia no limiar mínimo deve passar sem reforço adicional.');

$reinforcementMethod = new ReflectionMethod(GenerateOrderDocumentJob::class, 'buildGenericMethodologyReinforcement');
$reinforcementMethod->setAccessible(true);
$reinforcement = trim((string) $reinforcementMethod->invoke($job, $briefing));

assertTrue($updatedContent !== $reinforcement, 'Conteúdo no limiar não deve ser substituído por reforço genérico.');

echo "GenerateOrderDocumentJob methodology threshold tests passed.\n";

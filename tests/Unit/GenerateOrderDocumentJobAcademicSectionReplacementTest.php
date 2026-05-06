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

$method = new ReflectionMethod(GenerateOrderDocumentJob::class, 'ensureAcademicIntroduction');
$method->setAccessible(true);

$weakIntro = 'Texto curto e genérico sem alinhamento académico.';
$sectionsWithWeakIntro = [
    ['code' => 'introducao', 'title' => 'Introdução', 'content' => $weakIntro],
];

/** @var array<int, array<string, string>> $updated */
$updated = $method->invoke($job, $sectionsWithWeakIntro, $briefing);
$introContent = (string) ($updated[0]['content'] ?? '');

assertTrue($introContent !== $weakIntro, 'Introdução fraca deve ser substituída por conteúdo reconstruído.');
assertTrue(!str_contains($introContent, $weakIntro), 'Introdução reconstruída não deve concatenar texto fraco original.');

$sectionsWithoutIntro = [
    ['code' => 'desenvolvimento', 'title' => 'Desenvolvimento', 'content' => 'Seção intermediária.'],
];

/** @var array<int, array<string, string>> $created */
$created = $method->invoke($job, $sectionsWithoutIntro, $briefing);

$introMatches = array_values(array_filter(
    $created,
    static fn (array $section): bool => mb_strtolower((string) ($section['title'] ?? '')) === 'introdução'
));

assertTrue(count($introMatches) === 1, 'Introdução ausente deve ser inserida apenas uma vez.');
assertTrue(!str_contains((string) $introMatches[0]['content'], $weakIntro), 'Introdução inserida não deve conter duplicação semântica do texto fraco.');

echo "GenerateOrderDocumentJob academic section replacement tests passed.\n";

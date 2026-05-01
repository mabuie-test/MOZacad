<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Helpers/Config.php';
require __DIR__ . '/../../app/Helpers/Env.php';
require __DIR__ . '/../../app/Services/StoragePathService.php';
require __DIR__ . '/../../app/Services/LogSanitizerService.php';
require __DIR__ . '/../../app/Services/TraceContextService.php';
require __DIR__ . '/../../app/Services/ApplicationLoggerService.php';
require __DIR__ . '/../../app/Services/DynamicAcademicStructureService.php';

use App\Services\DynamicAcademicStructureService;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

function assertBlueprintTriggered(DynamicAcademicStructureService $service, string $topic): void
{
    $blueprint = $service->buildDynamicBlueprint([
        'topic' => $topic,
        'target_pages' => 6,
    ], [], [], [['code' => 'fallback']], []);

    assertSame('resumo', findByTitle($blueprint, 'Resumo')['code'], sprintf('Blueprint should trigger for topic: %s', $topic));
}

function findByTitle(array $sections, string $title): array
{
    foreach ($sections as $section) {
        if (($section['title'] ?? '') === $title) {
            return $section;
        }
    }

    throw new RuntimeException(sprintf('Section with title "%s" not found.', $title));
}

$service = new DynamicAcademicStructureService();

$blueprint = $service->buildDynamicBlueprint([
    'topic' => 'História da educação colonial em Moçambique',
    'target_pages' => 6,
], [], [], [], []);

assertSame('resumo', findByTitle($blueprint, 'Resumo')['code'], 'Resumo should map to resumo code.');
assertSame('introducao', findByTitle($blueprint, 'Introdução')['code'], 'Introdução should map to introducao code.');
assertSame('conclusao', findByTitle($blueprint, 'Conclusão')['code'], 'Conclusão should map to conclusao code.');
assertSame('references', findByTitle($blueprint, 'Referências')['code'], 'Referências should map to references code.');
assertSame('colonial_education_history_mozambique', $blueprint[0]['dynamic_profile_id'], 'Dynamic profile id should be included in output metadata.');

$summary = findByTitle($blueprint, 'Resumo');
assertSame(120, $summary['min_words'], 'Resumo min words should be specific.');
assertSame(220, $summary['max_words'], 'Resumo max words should be specific.');

$reflection = new ReflectionClass(DynamicAcademicStructureService::class);
$mapMethod = $reflection->getMethod('mapSections');
$mapMethod->setAccessible(true);
/** @var array<int,array<string,mixed>> $mapped */
$mapped = $mapMethod->invoke($service, ['Resumo', 'Introdução', 'Metodologia', 'Conclusão', 'Referencias'], 'test_profile', []);

assertSame('metodologia', findByTitle($mapped, 'Metodologia')['code'], 'Metodologia should map semantically.');
assertSame('references', findByTitle($mapped, 'Referencias')['code'], 'Referencias without accent should map to references.');

// Positivo: variantes sem acento.
assertBlueprintTriggered($service, 'Historia da educacao colonial em Mocambique');

// Negativo: ausência de grupo obrigatório (sem país Moçambique).
$fallbackMissingGroup = $service->buildDynamicBlueprint([
    'topic' => 'História da educação colonial na África',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('fallback', $fallbackMissingGroup[0]['code'], 'Missing required criterion group should keep fallback blueprint.');

// Prioridade: primeiro perfil válido no catálogo vence quando múltiplos casam.
$priority = $service->buildDynamicBlueprint([
    'topic' => 'Pedagogia e educação: fundamentos e teorias gerais',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('general_pedagogy', $priority[0]['dynamic_profile_id'], 'First matching profile should win by catalog order.');


$matched = $service->buildDynamicBlueprint([
    'topic' => 'História da educação colonial em Moçambique',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);

assertSame(true, isset($matched[0]['code'], $matched[0]['title'], $matched[0]['min_words'], $matched[0]['max_words']), 'Contrato do blueprint deve conter code/title/min_words/max_words.');

$notMatched = $service->buildDynamicBlueprint([
    'topic' => 'Administração pública local e transparência',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('fallback', $notMatched[0]['code'], 'Non-match deve retornar blueprint base (fallback).');

echo "DynamicAcademicStructureService tests passed.\n";

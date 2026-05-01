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


$catalogProfiles = require __DIR__ . '/../../config/academic_thematic_profiles.php';
foreach ($catalogProfiles as $catalogProfile) {
    assertSame(true, isset($catalogProfile['priority']) && is_int($catalogProfile['priority']), 'Every thematic profile must define integer priority.');
}

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


$specificityMethod = $reflection->getMethod('specificityScoreFromMatchedCriteria');
$specificityMethod->setAccessible(true);

$scoreWithMalformedVariant = $specificityMethod->invoke($service, [
    ['criterion' => 'c1', 'variant' => ''],
    ['criterion' => 'c2'],
]);
assertSame(2002, $scoreWithMalformedVariant, 'Each matched criterion variant must contribute at least one point even when malformed.');

$scoreWithEmptyCriteria = $specificityMethod->invoke($service, []);
assertSame(0, $scoreWithEmptyCriteria, 'Empty matched criteria must keep specificity score at zero.');

// Positivo: variantes sem acento.
assertBlueprintTriggered($service, 'Historia da educacao colonial em Mocambique');

// Negativo: ausência de grupo obrigatório (sem país Moçambique).
$fallbackMissingGroup = $service->buildDynamicBlueprint([
    'topic' => 'História da educação colonial na África',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('fallback', $fallbackMissingGroup[0]['code'], 'Missing required criterion group should keep fallback blueprint.');

// Prioridade explícita no catálogo: perfil com maior prioridade vence.
$priority = $service->buildDynamicBlueprint([
    'topic' => 'Pedagogia e educação: fundamentos e teorias gerais',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('general_pedagogy', $priority[0]['dynamic_profile_id'], 'Priority should deterministically resolve tie.');



// Falso positivo evitado: radical curto não deve casar por substring arbitrária.
$falsePositive = $service->buildDynamicBlueprint([
    'topic' => 'Historicidade da administração pública em Moçambique',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('fallback', $falsePositive[0]['code'], 'Short radical should not trigger match by substring.');

// Desempate por prioridade explícita.
$priorityService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'low_priority_profile',
    'priority' => 1,
    'criteria' => [['edu', ['educacao']], ['gen', ['fundamentos']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'high_priority_profile',
    'priority' => 10,
    'criteria' => [['edu', ['educacao']], ['gen', ['fundamentos']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
]]);
$priorityResolved = $priorityService->buildDynamicBlueprint([
    'topic' => 'Educação e fundamentos',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('high_priority_profile', $priorityResolved[0]['dynamic_profile_id'], 'Higher priority profile must win tie.');

// Desempate determinístico para prioridade e especificidade iguais: id ascendente.
$deterministicTieService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'zz_profile',
    'priority' => 7,
    'criteria' => [['edu', ['educacao']], ['base', ['fundamentos']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'aa_profile',
    'priority' => 7,
    'criteria' => [['edu', ['educacao']], ['base', ['fundamentos']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
]]);
$expectedDeterministicProfile = null;
for ($i = 0; $i < 20; $i++) {
    $tieBlueprint = $deterministicTieService->buildDynamicBlueprint([
        'topic' => 'Educação e fundamentos',
        'target_pages' => 6,
    ], [], [], [['code' => 'fallback']], []);

    $currentProfile = $tieBlueprint[0]['dynamic_profile_id'] ?? null;

    if ($expectedDeterministicProfile === null) {
        $expectedDeterministicProfile = $currentProfile;
    }

    assertSame($expectedDeterministicProfile, $currentProfile, 'Tie resolution must be stable across repeated executions.');
}
assertSame('aa_profile', $expectedDeterministicProfile, 'Tie must resolve by ascending profile id.');


// Empate com mesma prioridade e match simultâneo: seleção determinística por id ascendente.
$simultaneousTieService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'profile_b',
    'priority' => 9,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'profile_a',
    'priority' => 9,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
]]);
$simultaneousTie = $simultaneousTieService->buildDynamicBlueprint([
    'topic' => 'História da educação',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('profile_a', $simultaneousTie[0]['dynamic_profile_id'], 'Same-priority simultaneous match must be deterministic by ascending id.');

// Regressão: mesma prioridade com múltiplos matches deve estabilizar por especificidade e id em execuções repetidas.
$regressionTieService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'profile_z',
    'priority' => 5,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'profile_a',
    'priority' => 5,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'profile_specific',
    'priority' => 5,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia da educacao']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
]]);

$expectedRegressionProfile = null;
for ($i = 0; $i < 15; $i++) {
    $regressionBlueprint = $regressionTieService->buildDynamicBlueprint([
        'topic' => 'História da educação',
        'target_pages' => 6,
    ], [], [], [['code' => 'fallback']], []);

    $currentProfile = $regressionBlueprint[0]['dynamic_profile_id'] ?? null;

    if ($expectedRegressionProfile === null) {
        $expectedRegressionProfile = $currentProfile;
    }

    assertSame($expectedRegressionProfile, $currentProfile, 'Same-priority matches must produce stable profile id across repeated executions.');
}
assertSame('profile_specific', $expectedRegressionProfile, 'Specificity must break same-priority ties before id.');

// Regressão: prioridade continua prevalecendo sobre especificidade.
$priorityOverSpecificityService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'high_priority_less_specific',
    'priority' => 20,
    'criteria' => [['edu', ['educacao']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
], [
    'id' => 'low_priority_more_specific',
    'priority' => 10,
    'criteria' => [['edu', ['educacao']], ['hist', ['historia']], ['pais', ['mocambique']]],
    'sections' => ['Resumo', 'Introdução', 'Conclusão', 'Referências'],
]]);
$priorityOverSpecificity = $priorityOverSpecificityService->buildDynamicBlueprint([
    'topic' => 'História da educação em Moçambique',
    'target_pages' => 6,
], [], [], [['code' => 'fallback']], []);
assertSame('high_priority_less_specific', $priorityOverSpecificity[0]['dynamic_profile_id'], 'Priority must remain the primary tie-break rule.');

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

$baseBlueprint = [['code' => 'fallback']];

set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$nullCatalogService = new DynamicAcademicStructureService(null, static fn (): mixed => null);
$nullCatalogBlueprint = $nullCatalogService->buildDynamicBlueprint([
    'topic' => 'História da educação colonial em Moçambique',
    'target_pages' => 6,
], [], [], $baseBlueprint, []);
assertSame($baseBlueprint, $nullCatalogBlueprint, 'Null catalog must fail-safe to base blueprint.');

$stringCatalogService = new DynamicAcademicStructureService(null, static fn (): mixed => '{"invalid":true}');
$stringCatalogBlueprint = $stringCatalogService->buildDynamicBlueprint([
    'topic' => 'História da educação colonial em Moçambique',
    'target_pages' => 6,
], [], [], $baseBlueprint, []);
assertSame($baseBlueprint, $stringCatalogBlueprint, 'String catalog must fail-safe to base blueprint.');

$validCatalogService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'custom_valid_profile',
    'priority' => 3,
    'criteria' => [['tema', ['historia']]],
    'sections' => ['Resumo', 'Introdução', 'Metodologia', 'Conclusão', 'Referências'],
]]);
$validCatalogBlueprint = $validCatalogService->buildDynamicBlueprint([
    'topic' => 'História contemporânea da educação',
    'target_pages' => 6,
], [], [], $baseBlueprint, []);
assertSame('custom_valid_profile', $validCatalogBlueprint[0]['dynamic_profile_id'], 'Valid catalog must be consumed normally.');

$invalidShortVariantService = new DynamicAcademicStructureService(null, static fn (): mixed => [[
    'id' => 'invalid_short_variant',
    'priority' => 3,
    'criteria' => [['group', ['abc']]],
    'sections' => ['Resumo'],
]]);
$invalidShortVariantBlueprint = $invalidShortVariantService->buildDynamicBlueprint([
    'topic' => 'abc',
    'target_pages' => 6,
], [], [], $baseBlueprint, []);
assertSame($baseBlueprint, $invalidShortVariantBlueprint, 'Single-term variants shorter than threshold must be rejected by schema validator.');

restore_error_handler();

echo "DynamicAcademicStructureService tests passed.\n";

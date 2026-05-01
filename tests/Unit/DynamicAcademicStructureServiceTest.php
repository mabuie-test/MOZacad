<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/DynamicAcademicStructureService.php';

use App\Services\DynamicAcademicStructureService;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
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

$order = [
    'topic' => 'História da educação colonial em Moçambique',
    'target_pages' => 6,
];

$blueprint = $service->buildDynamicBlueprint($order, [], [], [], []);

assertSame('resumo', findByTitle($blueprint, 'Resumo')['code'], 'Resumo should map to resumo code.');
assertSame('introducao', findByTitle($blueprint, 'Introdução')['code'], 'Introdução should map to introducao code.');
assertSame('conclusao', findByTitle($blueprint, 'Conclusão')['code'], 'Conclusão should map to conclusao code.');
assertSame('references', findByTitle($blueprint, 'Referências')['code'], 'Referências should map to references code.');

$summary = findByTitle($blueprint, 'Resumo');
assertSame(120, $summary['min_words'], 'Resumo min words should be specific.');
assertSame(220, $summary['max_words'], 'Resumo max words should be specific.');

$methodSection = [
    'Resumo',
    'Introdução',
    'Metodologia',
    'Conclusão',
    'Referencias',
];

$reflection = new ReflectionClass(DynamicAcademicStructureService::class);
$mapMethod = $reflection->getMethod('mapSections');
$mapMethod->setAccessible(true);
/** @var array<int,array<string,mixed>> $mapped */
$mapped = $mapMethod->invoke($service, $methodSection);

assertSame('metodologia', findByTitle($mapped, 'Metodologia')['code'], 'Metodologia should map semantically.');
assertSame('references', findByTitle($mapped, 'Referencias')['code'], 'Referencias without accent should map to references.');

echo "DynamicAcademicStructureService tests passed.\n";

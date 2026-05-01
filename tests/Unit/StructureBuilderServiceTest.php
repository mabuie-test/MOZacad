<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/StructureBuilderService.php';

use App\Services\StructureBuilderService;

function assertTrue(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function assertContainsCode(array $structure, string $code): void
{
    foreach ($structure as $section) {
        if (($section['code'] ?? null) === $code) {
            return;
        }
    }

    throw new RuntimeException(sprintf('Expected section code "%s" not found.', $code));
}

$service = new StructureBuilderService();

$emptyRulesStructure = $service->build([], []);
assertContainsCode($emptyRulesStructure, 'analise');

$theoreticalStructure = $service->build([], ['is_theoretical' => true]);
assertContainsCode($theoreticalStructure, 'analise_teorica');

$empiricalStructure = $service->build([], ['is_empirical' => true]);
assertContainsCode($empiricalStructure, 'resultados');

$conflictThrown = false;

try {
    $service->build([], ['is_theoretical' => true, 'is_empirical' => true]);
} catch (\InvalidArgumentException) {
    $conflictThrown = true;
}

assertTrue($conflictThrown, 'Expected InvalidArgumentException for theoretical+empirical conflict.');

echo "StructureBuilderService tests passed.\n";

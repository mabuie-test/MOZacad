<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/UnicodeWordCounter.php';
require __DIR__ . '/../../app/Services/InstitutionFormattingService.php';

use App\Services\InstitutionFormattingService;
use App\Support\UnicodeWordCounter;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

$service = new InstitutionFormattingService();

$sections = [
    [
        'code' => 'intro',
        'title' => 'Introdução',
        'content' => 'A investigação pós-independência fortalece a educação pública.',
    ],
];

$formatted = $service->apply($sections, []);
$content = $sections[0]['content'];
$expectedWordCount = UnicodeWordCounter::count($content);

assertSameValue($expectedWordCount, $formatted['sections'][0]['word_count'], 'word_count deve usar a mesma base UnicodeWordCounter.');

echo "InstitutionFormattingService tests passed.\n";

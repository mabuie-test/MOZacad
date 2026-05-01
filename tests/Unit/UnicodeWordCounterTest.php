<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/UnicodeWordCounter.php';

use App\Support\UnicodeWordCounter;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

$cases = [
    [
        'text' => 'A investigação em educação colonial avançou.',
        'expectedTokens' => ['A', 'investigação', 'em', 'educação', 'colonial', 'avançou'],
    ],
    [
        'text' => 'No período pós-independência, há reconfigurações político-sociais.',
        'expectedTokens' => ['No', 'período', 'pós', 'independência', 'há', 'reconfigurações', 'político', 'sociais'],
    ],
    [
        'text' => 'Ensino, escola; missão: assimilação?',
        'expectedTokens' => ['Ensino', 'escola', 'missão', 'assimilação'],
    ],
];

foreach ($cases as $index => $case) {
    $actual = UnicodeWordCounter::count($case['text']);
    $expected = count($case['expectedTokens']);

    assertSameValue(
        $expected,
        $actual,
        sprintf('Case %d falhou na contagem Unicode token a token.', $index + 1)
    );
}

echo "UnicodeWordCounter tests passed.\n";

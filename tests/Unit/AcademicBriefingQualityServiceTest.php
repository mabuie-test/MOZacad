<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/AcademicBriefingQualityService.php';

use App\Services\AcademicBriefingQualityService;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new AcademicBriefingQualityService();

$validBriefing = [
    'problem_statement' => 'Como a evasão escolar em regiões rurais d\'África impacta o desempenho acadêmico dos estudantes?',
    'general_objective' => 'Analisar os fatores sociais que influenciam a evasão escolar em comunidades rurais.',
    'specific_objectives' => [
        'Identificar variáveis socioeconômicas associadas à evasão em escolas públicas.',
        'Comparar dados históricos de frequência escolar entre distritos com perfis distintos.',
        'Avaliar políticas educacionais locais com base em indicadores de permanência estudantil.',
    ],
];

$result = $service->evaluate($validBriefing);
assertSame(true, $result['ok'], 'Briefing com acentos e apóstrofos deve ser válido.');
assertSame([], $result['issues'], 'Não deve haver issues no briefing válido.');

$shortSpecificBriefing = [
    'problem_statement' => 'Como a evasão escolar em regiões rurais d\'África impacta o desempenho acadêmico dos estudantes?',
    'general_objective' => 'Analisar os fatores sociais que influenciam a evasão escolar em comunidades rurais.',
    'specific_objectives' => [
        'Analisar d\'África',
        'Comparar dados históricos de frequência escolar entre distritos com perfis distintos.',
        'Avaliar políticas educacionais locais com base em indicadores de permanência estudantil.',
    ],
];

$shortSpecificResult = $service->evaluate($shortSpecificBriefing);
assertTrue(in_array('specific_objective_too_short', $shortSpecificResult['issues'], true), 'Objetivo específico curto com apóstrofo deve ser detetado.');

echo "AcademicBriefingQualityService tests passed.\n";

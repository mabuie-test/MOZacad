<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Support/UnicodeWordCounter.php';
require __DIR__ . '/../../app/Services/AcademicBriefingAutoCompletionService.php';

use App\Services\AcademicBriefingAutoCompletionService;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(' Expected: %s; Actual: %s', var_export($expected, true), var_export($actual, true)));
    }
}

function assertTrueValue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new AcademicBriefingAutoCompletionService();

$profiles = [
    ['slug' => 'revisao_literatura', 'expected' => 'revisao'],
    ['slug' => 'ensaio_critico', 'expected' => 'ensaio'],
    ['slug' => 'estudo_empirico', 'expected' => 'empirico'],
    ['slug' => 'dissertacao_teorica', 'expected' => 'teorico'],
];

foreach ($profiles as $case) {
    $result = $service->complete([
        'topic' => 'Educação inclusiva em Moçambique',
        'work_type_slug' => $case['slug'],
    ], [], []);

    assertSameValue(
        $case['expected'],
        $result['applied_profile']['work_type_template'],
        sprintf('Fallback de work type deveria selecionar perfil %s.', $case['expected'])
    );
}

$withoutKeywords = $service->complete([
    'topic' => 'Educação pública e inclusão social em Moçambique',
], [
    'keywords_json' => [],
], []);

assertTrueValue(count($withoutKeywords['keywords']) > 0, 'Sem keywords, o serviço deve inferir fallback pelo título.');
assertSameValue('educacao', $withoutKeywords['keywords'][0], 'Primeira keyword fallback esperada do título normalizado.');

$colonialResult = $service->complete([
    'topic' => 'História da educação colonial em Moçambique',
], [
    'briefing' => 'Analisar o ensino durante o período colonial e missões religiosas.',
], []);

assertSameValue(true, $colonialResult['applied_profile']['education_colonial_package'], 'Tema colonial deve ativar pacote colonial.');
assertTrueValue(in_array('educação colonial', $colonialResult['keywords'], true), 'Keyword colonial padrão deve ser adicionada quando perfil colonial casar.');

$nonColonialResult = $service->complete([
    'topic' => 'Gestão de bibliotecas universitárias',
], [
    'briefing' => 'Melhoria de processos administrativos e digitais.',
], []);

assertSameValue(false, $nonColonialResult['applied_profile']['education_colonial_package'], 'Tema não colonial não deve ativar pacote colonial.');

echo "AcademicBriefingAutoCompletionService tests passed.\n";

<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Domain/Academic/OperationalMetaPatterns.php';
require __DIR__ . '/../../app/Domain/Academic/QualityThresholds.php';
require __DIR__ . '/../../app/Services/DocumentEditorialQualityGateService.php';
require __DIR__ . '/../../app/Jobs/GenerateOrderDocumentJob.php';

use App\Domain\Academic\QualityThresholds;
use App\Jobs\GenerateOrderDocumentJob;
use App\Services\DocumentEditorialQualityGateService;

function assertTrue(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function wordPad(string $base, int $target): string {
    $words = array_values(array_filter(preg_split('/\s+/u', trim($base)) ?: [], static fn (string $word): bool => $word !== ''));
    $missing = $target - count($words);
    return trim($missing > 0 ? $base . ' ' . implode(' ', array_fill(0, $missing, 'complementar')) : $base);
}

$gate = new DocumentEditorialQualityGateService();
$job = new GenerateOrderDocumentJob();
$briefing = ['problem' => 'integração de tecnologias digitais no ensino superior', 'generalObjective' => 'avaliar estratégias pedagógicas para integração tecnológica'];
$baseMethodology = 'A abordagem metodológica adota método qualitativo com procedimentos organizados em etapas. Este desenho responde ao problema de integração de tecnologias digitais no ensino superior e ao objetivo de avaliar estratégias pedagógicas para integração tecnológica.';
$devContent = "1. Eixo analítico\n" . str_repeat('A análise documental evidencia padrões empíricos relevantes (Silva, 2021). ', 20)
    . "\n\n2. Delimitação conceitual\n" . str_repeat('A literatura sustenta categorias de análise e critérios de comparação (Pereira, 2020). ', 20)
    . "\n\n3. Estratégia de recolha\n" . str_repeat('A recolha documental seguiu protocolo de inclusão e exclusão de fontes (Costa, 2019). ', 20)
    . "\n\n4. Técnica de tratamento\n" . str_repeat('A codificação temática permitiu rastrear recorrências e divergências analíticas (Silva, 2021). ', 20)
    . "\n\n5. Critérios de validade\n" . str_repeat('A triangulação fortaleceu validade interna e consistência interpretativa (Pereira, 2020). ', 20)
    . "\n\n6. Síntese analítica\n" . str_repeat('A síntese integrou evidências em quadro explicativo coerente com o objetivo geral (Costa, 2019). ', 20);

$baseSections = [
    ['code' => 'introducao', 'title' => 'Introdução', 'content' => str_repeat('Contexto do tema e delimitação do problema investigado. ', 25)],
    ['code' => 'objectivos', 'title' => 'Objectivos', 'content' => str_repeat('Objetivo geral e objetivos específicos articulados ao problema. ', 20)],
    ['code' => 'metodologia', 'title' => 'Metodologia', 'content' => ''],
    ['code' => 'desenvolvimento', 'title' => 'Desenvolvimento', 'content' => $devContent],
    ['code' => 'conclusao', 'title' => 'Conclusão', 'content' => str_repeat('Síntese dos resultados e retorno ao objetivo geral do estudo. ', 25)],
    ['code' => 'referencias', 'title' => 'Referências', 'content' => "SILVA, Ana. Metodologias de pesquisa. Maputo: Atlas, 2021.\nPEREIRA, João. Didática universitária. Lisboa: Horizonte, 2020.\nCOSTA, Maria. Educação digital. Porto: Saber, 2019."],
];

$scenarios = [[120,true,null],[100,true,'major'],[80,false,'critical']];
foreach ($scenarios as [$words,$expectedOk,$expectedSeverity]) {
    $sections = $baseSections;
    $sections[2]['content'] = wordPad($baseMethodology, $words);
    $result = $gate->validate($sections);
    assertTrue($result['ok'] === $expectedOk, "Cenário {$words} palavras retornou status inesperado.");
    $issues = array_values(array_filter($result['issues'], static fn(array $i): bool => ($i['rule'] ?? '') === 'methodology_too_short'));
    if ($expectedSeverity === null) { assertTrue($issues === [], '120+ não deve gerar issue de metodologia.'); continue; }
    assertTrue($issues !== [], "{$words} palavras deve gerar issue methodology_too_short.");
    assertTrue(($issues[0]['severity'] ?? '') === $expectedSeverity, "{$words} palavras com severidade inesperada.");
}


$ensure = new ReflectionMethod(GenerateOrderDocumentJob::class, 'ensureSubstantiveMethodology');
$ensure->setAccessible(true);
$sectionsStable = $baseSections;
$sectionsStable[2]['content'] = wordPad($baseMethodology, QualityThresholds::MIN_METHODOLOGY_WORDS);
$stable = $ensure->invoke($job, $sectionsStable, $briefing);
assertTrue(trim((string) $stable[2]['content']) === trim((string) $sectionsStable[2]['content']), 'Conteúdo de metodologia com 120+ palavras deve ser preservado sem reforço.');

echo "GenerateOrderDocumentJob methodology threshold tests passed.\n";

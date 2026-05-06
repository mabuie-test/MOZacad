<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Domain/Academic/OperationalMetaPatterns.php';
require __DIR__ . '/../../app/Domain/Academic/QualityThresholds.php';
require __DIR__ . '/../../app/Services/AcademicSectionClassifierService.php';
require __DIR__ . '/../../app/Services/DocumentEditorialQualityGateService.php';

use App\Services\DocumentEditorialQualityGateService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new DocumentEditorialQualityGateService();

$sections = [
    ['code' => 'introducao', 'title' => 'Introdução', 'content' => str_repeat('Contexto académico sólido. ', 25)],
    ['code' => 'objectivos', 'title' => 'Objectivos', 'content' => str_repeat('Definir objectivos específicos e gerais. ', 20)],
    ['code' => 'metodologia', 'title' => 'Metodologia', 'content' => str_repeat('Pesquisa documental, análise crítica e triangulação de fontes primárias e secundárias. ', 16)],
    ['code' => 'desenvolvimento', 'title' => 'Desenvolvimento', 'content' => "1. Contextualização histórica\n" . str_repeat('O período colonial moldou estruturas de poder e educação em Moçambique (Newitt, 1995). ', 20)
        . "\n\n2. Estrutura do sistema educativo colonial\n" . str_repeat('A dualidade entre ensino oficial e rudimentar consolidou desigualdades educativas (Ngoenha, 2000). ', 20)
        . "\n\n3. Papel das missões religiosas\n" . str_repeat('As missões ampliaram alfabetização, mas reforçaram normatização cultural e moral (Althusser, 1980). ', 20)
        . "\n\n4. Ensino rudimentar, assimilação e língua portuguesa\n" . str_repeat('A assimilação vinculou língua portuguesa a mobilidade social limitada e seletiva (Mondlane, 1995). ', 20)
        . "\n\n5. Desigualdade de acesso e estratificação social\n" . str_repeat('As regiões rurais enfrentaram escassez estrutural e baixa progressão escolar (Newitt, 1995). ', 20)
        . "\n\n6. Legados no pós-independência\n" . str_repeat('Persistem desafios de equidade, currículo e justiça linguística no sistema educativo (Ngoenha, 2000). ', 20)],
    ['code' => 'conclusao', 'title' => 'Conclusão', 'content' => str_repeat('A análise confirma legado estrutural e desafios de democratização educativa. ', 20)],
    ['code' => 'referencias', 'title' => 'Referências', 'content' => "NEWITT, Malyn. A History of Mozambique. Bloomington: Indiana University Press, 1995.\nNGOENHA, Severino Elias. Estatuto e axiologia da educação em Moçambique. Maputo: Livraria Universitária UEM, 2000.\nMONDLANE, Eduardo. Lutar por Moçambique. Maputo: Centro de Estudos Africanos, 1995.\nALTHUSSER, Louis. Ideologia e aparelhos ideológicos do Estado. Lisboa: Presença, 1980."],
];

$result = $service->validate($sections);
assertTrue($result['ok'] === true, 'Documento com desenvolvimento temático denso e citações distribuídas deve passar no quality gate.');

$withInstructionOnly = $sections;
$withInstructionOnly[0]['content'] .= ' Esta instrução metodológica clarifica o recorte teórico.';
$instructionOnlyResult = $service->validate($withInstructionOnly);
assertTrue($instructionOnlyResult['ok'] === true, 'Uso isolado de "instrução" não deve ser bloqueado.');

$withPipelineInstruction = $sections;
$withPipelineInstruction[0]['content'] .= ' Estas são instruções do pipeline para ajuste interno.';
$pipelineInstructionResult = $service->validate($withPipelineInstruction);
assertTrue($pipelineInstructionResult['ok'] === false, 'Expressão "instruções do pipeline" deve reprovar no quality gate.');


$missingMethodology = array_values(array_filter($sections, static fn(array $section): bool => ($section['code'] ?? '') !== 'metodologia'));
$missingMethodologyResult = $service->validate($missingMethodology);
$missingIssues = array_values(array_filter($missingMethodologyResult['issues'], static fn(array $issue): bool => ($issue['rule'] ?? '') === 'required_section_missing_or_empty' && str_contains((string) ($issue['message'] ?? ''), 'metodologia')));
assertTrue($missingIssues !== [], 'Ausência de metodologia deve gerar rule required_section_missing_or_empty.');
assertTrue(($missingIssues[0]['severity'] ?? '') === 'critical', 'Ausência de metodologia deve ser critical.');


$variantSections = $sections;
$variantSections[2]['code'] = 'secao_metodo';
$variantSections[2]['title'] = '3.1 Nota metodológica — desenho e técnicas';
$variantSections[2]['content'] = str_repeat('Procedimentos metodológicos com triangulação de fontes e validação teórica. ', 16);
$variantResult = $service->validate($variantSections);
assertTrue($variantResult['ok'] === true, 'Variantes metodológicas com ruído textual devem classificar como metodologia estável.');

$withPayload = $sections;
$withPayload[1]['content'] .= '
payload: {"step":"draft"}';
$payloadResult = $service->validate($withPayload);
assertTrue($payloadResult['ok'] === false, 'Marcador "payload:" deve reprovar no quality gate.');

$withDebug = $sections;
$withDebug[2]['content'] .= '
debug: tracing';
$debugResult = $service->validate($withDebug);
assertTrue($debugResult['ok'] === false, 'Marcador "debug:" deve reprovar no quality gate.');

echo "DocumentEditorialQualityGateService tests passed.\n";

<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Domain/Academic/QualityThresholds.php';
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

echo "DocumentEditorialQualityGateService tests passed.\n";

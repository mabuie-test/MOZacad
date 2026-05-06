<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/AcademicSectionClassifierService.php';
require __DIR__ . '/../../app/Services/DocumentComplianceValidationService.php';

use App\Services\DocumentComplianceValidationService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new DocumentComplianceValidationService();

$sections = [
    ['title' => 'Introdução'],
    ['title' => 'Conclusão'],
    ['title' => 'Referências'],
];
$rules = [
    'structureRules' => ['requires_methodology' => true],
    'referenceRules' => ['style' => 'ABNT'],
];

$result = $service->validate($sections, [], $rules);
$issues = array_values(array_filter($result['non_conformities'], static fn(array $i): bool => ($i['rule'] ?? '') === 'required_section_missing' && ($i['target'] ?? '') === 'metodologia'));
assertTrue($issues !== [], 'Metodologia em falta deve gerar required_section_missing.');
assertTrue(($issues[0]['severity'] ?? '') === 'critical', 'Metodologia em falta deve ser critical na matriz única.');
assertTrue($result['is_compliant'] === false, 'Issue critical deve marcar documento como não conforme.');


$variantSections = [
    ['title' => '1) Apresentação'],
    ['title' => '2) Considerações finais'],
    ['title' => '3) Nota metodológica: percurso e técnicas'],
    ['title' => '4) Bibliografia'],
];
$variantResult = $service->validate($variantSections, [], $rules);
assertTrue($variantResult['is_compliant'] === true, 'Equivalências com variantes metodológicas e ruído devem manter conformidade.');


echo "DocumentComplianceValidationService tests passed.\n";

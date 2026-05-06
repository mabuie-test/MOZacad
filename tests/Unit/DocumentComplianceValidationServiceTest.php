<?php

declare(strict_types=1);

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

echo "DocumentComplianceValidationService tests passed.\n";

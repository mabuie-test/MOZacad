<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/DocxAssemblyService.php';

use App\Services\DocxAssemblyService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new DocxAssemblyService();
$cleanText = new ReflectionMethod(DocxAssemblyService::class, 'cleanText');
$cleanText->setAccessible(true);
$modeProperty = new ReflectionProperty(DocxAssemblyService::class, 'editorialCleanupMode');
$modeProperty->setAccessible(true);

$methodologicalLimitations = 'Como limitações metodológicas, os dados não foram disponibilizados integralmente pela instituição e não foram especificados todos os indicadores secundários.';

$modeProperty->setValue($service, 'default');
$defaultOutput = $cleanText->invoke($service, $methodologicalLimitations);
assertTrue(
    str_contains($defaultOutput, 'não foram disponibilizados integralmente')
    && str_contains($defaultOutput, 'não foram especificados todos os indicadores secundários'),
    'Limitações metodológicas devem permanecer no modo default.'
);

$strictInput = '[[REVISAR]] O capítulo requer revisão manual antes da publicação. Resultado analítico mantido.';
$modeProperty->setValue($service, 'strict_editorial_cleanup');
$strictOutput = $cleanText->invoke($service, $strictInput);
assertTrue(
    !str_contains($strictOutput, '[[REVISAR]]') && !str_contains($strictOutput, 'requer revisão manual'),
    'Marcadores editoriais devem ser removidos no modo estrito.'
);
assertTrue(
    str_contains($strictOutput, 'Resultado analítico mantido.'),
    'Conteúdo analítico deve permanecer após limpeza editorial estrita.'
);

echo "DocxAssemblyService tests passed.\n";

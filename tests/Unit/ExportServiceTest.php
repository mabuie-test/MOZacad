<?php

declare(strict_types=1);

require __DIR__ . '/../../app/Services/StoragePathService.php';
require __DIR__ . '/../../app/Services/ExportService.php';

use App\Services\ExportService;

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$service = new ExportService();
$method = new ReflectionMethod(ExportService::class, 'assertValidXml');
$method->setAccessible(true);

$method->invoke($service, 'word/document.xml', '<root><w:t>Marconi &amp; Lakatos</w:t></root>');
assertTrue(true, 'XML válido deve ser aceite.');

$failed = false;
try {
    $method->invoke($service, 'word/document.xml', '<root><w:t>Marconi & Lakatos</w:t></root>');
} catch (RuntimeException $e) {
    $failed = str_contains($e->getMessage(), 'XML mal formado');
}

assertTrue($failed, 'XML inválido com ampersand não escapado deve falhar.');

echo "ExportService tests passed.\n";

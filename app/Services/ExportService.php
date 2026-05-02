<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use RuntimeException;
use ZipArchive;

final class ExportService
{
    public function __construct(private readonly StoragePathService $paths = new StoragePathService()) {}

    public function saveDocx(PhpWord $doc, string $filename): string
    {
        $baseDir = $this->paths->generatedBase();
        $this->paths->ensureDirectory($baseDir);

        $path = $baseDir . '/' . basename($filename);
        IOFactory::createWriter($doc, 'Word2007')->save($path);
        $this->assertDocxStructureIsValid($path);

        return $path;
    }

    private function assertDocxStructureIsValid(string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('DOCX não foi gerado no caminho esperado.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Falha ao abrir DOCX para validação estrutural.');
        }

        try {
            $xmlEntries = ['[Content_Types].xml', 'word/document.xml', 'word/_rels/document.xml.rels'];
            foreach ($xmlEntries as $entry) {
                $content = $zip->getFromName($entry);
                if (!is_string($content) || $content === '') {
                    throw new RuntimeException(sprintf('DOCX inválido: entrada XML obrigatória ausente (%s).', $entry));
                }
                $this->assertValidXml($entry, $content);
            }
        } finally {
            $zip->close();
        }
    }

    private function assertValidXml(string $entryName, string $xml): void
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument();
            $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_PARSEHUGE);
            if ($ok !== true) {
                $errors = libxml_get_errors();
                $first = $errors[0] ?? null;
                $detail = $first !== null
                    ? sprintf('%s (linha %d, coluna %d)', trim($first->message), $first->line, $first->column)
                    : 'erro XML desconhecido';
                throw new RuntimeException(sprintf('DOCX inválido: XML mal formado em %s: %s.', $entryName, $detail));
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}

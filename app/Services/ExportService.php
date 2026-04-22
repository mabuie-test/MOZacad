<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

final class ExportService
{
    public function __construct(private readonly StoragePathService $paths = new StoragePathService()) {}

    public function saveDocx(PhpWord $doc, string $filename): string
    {
        $baseDir = $this->paths->generatedBase();
        $this->paths->ensureDirectory($baseDir);

        $path = $baseDir . '/' . basename($filename);
        IOFactory::createWriter($doc, 'Word2007')->save($path);
        return $path;
    }
}

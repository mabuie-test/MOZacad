<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

final class ExportService
{
    public function saveDocx(PhpWord $doc, string $filename): string
    {
        $baseDir = dirname(__DIR__, 2) . '/' . trim((string) Env::get('STORAGE_GENERATED_PATH', 'storage/generated'), '/');
        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
        }

        $path = $baseDir . '/' . $filename;
        $writer = IOFactory::createWriter($doc, 'Word2007');
        $writer->save($path);

        return $path;
    }
}

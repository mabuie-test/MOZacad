<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

final class ExportService
{
    public function saveDocx(PhpWord $doc, string $filename): string
    {
        $path = __DIR__ . '/../../storage/generated/' . $filename;
        $writer = IOFactory::createWriter($doc, 'Word2007');
        $writer->save($path);
        return $path;
    }
}

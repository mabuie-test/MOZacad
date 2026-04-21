<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;

final class DocxAssemblyService
{
    public function assemble(array $formatted, string $title): PhpWord
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addTitle($title, 1);

        foreach ($formatted['sections'] as $line) {
            $section->addText((string)$line);
        }

        return $phpWord;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

final class DocxAssemblyService
{
    public function assemble(array $formatted, string $title): PhpWord
    {
        $phpWord = new PhpWord();

        $rules = $formatted['rules'] ?? [];
        $font = (string) ($rules['font_family'] ?? 'Times New Roman');
        $fontSize = (int) ($rules['font_size'] ?? 12);

        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize($fontSize);

        $section = $phpWord->addSection([
            'marginTop' => 1440,
            'marginBottom' => 1440,
            'marginLeft' => 1700,
            'marginRight' => 1700,
        ]);

        $section->addText((string) (($rules['front_page']['institution_name'] ?? 'Instituição Académica')), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(3);
        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(8);
        $section->addText('Maputo, ' . date('Y'), [], ['alignment' => Jc::CENTER]);

        $section->addPageBreak();
        $section->addTitle('Folha de rosto', 1);
        $section->addText($title, ['bold' => true], ['alignment' => Jc::CENTER]);

        foreach (($formatted['sections'] ?? []) as $sectionData) {
            $section->addPageBreak();
            $section->addTitle((string) ($sectionData['title'] ?? 'Secção'), 1);
            foreach (preg_split('/\n+/', (string) ($sectionData['content'] ?? '')) as $paragraph) {
                $paragraph = trim($paragraph);
                if ($paragraph === '') {
                    continue;
                }
                $section->addText($paragraph, ['size' => $fontSize], ['alignment' => Jc::BOTH, 'spaceAfter' => 180]);
            }
        }

        return $phpWord;
    }
}

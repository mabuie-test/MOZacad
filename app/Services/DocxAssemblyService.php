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
            'marginTop' => (int) round(((float) ($rules['margin_top'] ?? 2.5)) * 567),
            'marginBottom' => (int) round(((float) ($rules['margin_bottom'] ?? 2.5)) * 567),
            'marginLeft' => (int) round(((float) ($rules['margin_left'] ?? 3.0)) * 567),
            'marginRight' => (int) round(((float) ($rules['margin_right'] ?? 3.0)) * 567),
        ]);

        $frontPage = $rules['front_page'] ?? [];
        $section->addText((string) ($frontPage['institution_name'] ?? 'Instituição Académica'), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(3);
        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['course_name'])) {
            $section->addText((string) $frontPage['course_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(8);
        $section->addText('Maputo, ' . date('Y'), [], ['alignment' => Jc::CENTER]);

        $section->addPageBreak();
        $section->addTitle('Folha de rosto', 1);
        $section->addText($title, ['bold' => true], ['alignment' => Jc::CENTER]);

        $allSections = $formatted['sections'] ?? [];
        foreach ($allSections as $sectionData) {
            $sectionTitle = (string) ($sectionData['title'] ?? 'Secção');
            if (in_array(mb_strtolower($sectionTitle), ['resumo', 'abstract'], true)) {
                $section->addPageBreak();
                $section->addTitle($sectionTitle, 1);
                $this->appendParagraphs($section, (string) ($sectionData['content'] ?? ''), $fontSize);
                continue;
            }

            $section->addPageBreak();
            $section->addTitle($sectionTitle, 1);
            $this->appendParagraphs($section, (string) ($sectionData['content'] ?? ''), $fontSize);
        }

        return $phpWord;
    }

    private function appendParagraphs(\PhpOffice\PhpWord\Element\Section $section, string $content, int $fontSize): void
    {
        foreach (preg_split('/\n+/', $content) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            $section->addText($paragraph, ['size' => $fontSize], ['alignment' => Jc::BOTH, 'spaceAfter' => 180]);
        }
    }
}

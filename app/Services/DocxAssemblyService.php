<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;
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
        $headingFontSize = (int) ($rules['heading_font_size'] ?? 14);

        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize($fontSize);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => $headingFontSize], ['alignment' => Jc::CENTER, 'spaceAfter' => 240]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => max(12, $headingFontSize - 1)], ['alignment' => Jc::LEFT, 'spaceAfter' => 180]);

        $section = $phpWord->addSection([
            'marginTop' => (int) round(((float) ($rules['margins']['top'] ?? 2.5)) * 567),
            'marginBottom' => (int) round(((float) ($rules['margins']['bottom'] ?? 2.5)) * 567),
            'marginLeft' => (int) round(((float) ($rules['margins']['left'] ?? 3.0)) * 567),
            'marginRight' => (int) round(((float) ($rules['margins']['right'] ?? 3.0)) * 567),
        ]);

        $frontPage = is_array($rules['front_page'] ?? null) ? $rules['front_page'] : [];
        $sections = is_array($formatted['sections'] ?? null) ? $formatted['sections'] : [];

        $this->addCoverPage($section, $title, $frontPage, $headingFontSize);
        $this->addTitlePage($section, $title, $frontPage);
        $this->addPreTextSections($section, $sections, ['resumo', 'abstract']);
        $this->addTableOfContents($section);
        $this->addMainChapters($section, $sections);
        $this->addReferences($section, $sections);
        $this->addAnnexesAndAppendices($section, $sections);

        return $phpWord;
    }

    private function addCoverPage(Section $section, string $title, array $frontPage, int $headingFontSize): void
    {
        $section->addText((string) ($frontPage['institution_name'] ?? 'Instituição Académica'), ['bold' => true, 'size' => $headingFontSize], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['faculty_name'])) {
            $section->addText((string) $frontPage['faculty_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        if (!empty($frontPage['course_name'])) {
            $section->addText((string) $frontPage['course_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }

        $section->addTextBreak(4);
        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);

        if (!empty($frontPage['author_name'])) {
            $section->addTextBreak(2);
            $section->addText('Autor: ' . (string) $frontPage['author_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }

        $section->addTextBreak(8);
        $year = (string) ($frontPage['year'] ?? date('Y'));
        $city = (string) ($frontPage['city'] ?? 'Maputo');
        $section->addText($city . ', ' . $year, ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    private function addTitlePage(Section $section, string $title, array $frontPage): void
    {
        $section->addTitle('Folha de rosto', 1);
        $section->addText($title, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);

        if (!empty($frontPage['submission_note'])) {
            $section->addTextBreak(2);
            $section->addText((string) $frontPage['submission_note'], ['size' => 12], ['alignment' => Jc::BOTH]);
        }

        $section->addPageBreak();
    }

    private function addPreTextSections(Section $section, array $sections, array $codes): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, $codes, true)) {
                continue;
            }

            $section->addTitle((string) ($item['title'] ?? ucfirst($code)), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
            $section->addPageBreak();
        }
    }

    private function addTableOfContents(Section $section): void
    {
        $section->addTitle('Índice', 1);
        $section->addTOC(['size' => 11], ['tabLeader' => '.']);
        $section->addPageBreak();
    }

    private function addMainChapters(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (in_array($code, ['resumo', 'abstract', 'references', 'referencias', 'anexo', 'apendice'], true)) {
                continue;
            }

            $title = (string) ($item['title'] ?? 'Capítulo');
            $section->addTitle($title, 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
            $section->addPageBreak();
        }
    }

    private function addReferences(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, ['references', 'referencias'], true)) {
                continue;
            }

            $section->addTitle((string) ($item['title'] ?? 'Referências'), 1);
            $references = preg_split('/\n+/', (string) ($item['content'] ?? '')) ?: [];
            foreach ($references as $reference) {
                $reference = trim($reference);
                if ($reference === '') {
                    continue;
                }

                $section->addListItem($reference, 0, ['size' => 11]);
            }

            $section->addPageBreak();
            break;
        }
    }

    private function addAnnexesAndAppendices(Section $section, array $sections): void
    {
        $annexes = array_filter($sections, static function (array $item): bool {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            return str_starts_with($code, 'anexo') || str_starts_with($code, 'apendice');
        });

        foreach ($annexes as $item) {
            $section->addTitle((string) ($item['title'] ?? 'Anexo/Apêndice'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
            $section->addPageBreak();
        }
    }

    private function appendParagraphs(Section $section, string $content): void
    {
        foreach (preg_split('/\n+/', $content) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $section->addText($paragraph, [], ['alignment' => Jc::BOTH, 'spaceAfter' => 180]);
        }
    }
}

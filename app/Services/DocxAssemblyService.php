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
        $lineSpacing = (float) ($rules['line_spacing'] ?? 1.5);

        $phpWord->setDefaultFontName($font);
        $phpWord->setDefaultFontSize($fontSize);

        $phpWord->addParagraphStyle('body_text', [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 160,
            'lineHeight' => $lineSpacing,
            'indentation' => ['firstLine' => 600],
        ]);
        $phpWord->addParagraphStyle('quote_long', [
            'alignment' => Jc::BOTH,
            'spaceAfter' => 120,
            'lineHeight' => 1.0,
            'indentation' => ['left' => 720, 'right' => 720],
        ]);
        $phpWord->addParagraphStyle('references_item', [
            'alignment' => Jc::LEFT,
            'spaceAfter' => 100,
            'lineHeight' => 1.0,
            'indentation' => ['hanging' => 360],
        ]);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => $headingFontSize], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => max(12, $headingFontSize - 1)], ['alignment' => Jc::LEFT, 'spaceAfter' => 180]);

        $section = $phpWord->addSection([
            'marginTop' => (int) round(((float) ($rules['margins']['top'] ?? 2.5)) * 567),
            'marginBottom' => (int) round(((float) ($rules['margins']['bottom'] ?? 2.5)) * 567),
            'marginLeft' => (int) round(((float) ($rules['margins']['left'] ?? 3.0)) * 567),
            'marginRight' => (int) round(((float) ($rules['margins']['right'] ?? 3.0)) * 567),
        ]);

        $this->addHeaderFooter($section, $rules);

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

    private function addHeaderFooter(Section $section, array $rules): void
    {
        $header = $section->addHeader();
        $header->addText((string) ($rules['front_page']['institution_name'] ?? 'MOZacad'), ['size' => 10], ['alignment' => Jc::CENTER]);

        $footer = $section->addFooter();
        $footer->addPreserveText('Página {PAGE} de {NUMPAGES}', ['size' => 10], ['alignment' => Jc::CENTER]);
    }

    private function addCoverPage(Section $section, string $title, array $frontPage, int $headingFontSize): void
    {
        $section->addText((string) ($frontPage['institution_name'] ?? 'Instituição Académica'), ['bold' => true, 'size' => $headingFontSize], ['alignment' => Jc::CENTER]);
        $section->addText((string) ($frontPage['course_name'] ?? ''), ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(4);
        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(8);
        $section->addText(((string) ($frontPage['city'] ?? 'Maputo')) . ', ' . ((string) ($frontPage['year'] ?? date('Y'))), ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    private function addTitlePage(Section $section, string $title, array $frontPage): void
    {
        $section->addTitle('Folha de rosto', 1);
        $section->addText($title, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['submission_note'])) {
            $section->addTextBreak(2);
            $section->addText((string) $frontPage['submission_note'], [], 'body_text');
        }
        $section->addPageBreak();
    }

    private function addPreTextSections(Section $section, array $sections, array $codes): void
    {
        $added = 0;
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, $codes, true)) {
                continue;
            }

            $section->addTitle((string) ($item['title'] ?? ucfirst($code)), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
            $section->addPageBreak();
            $added++;
        }

        if ($added === 0) {
            $section->addTitle('Resumo', 1);
            $section->addText('Resumo indisponível - requer revisão manual.', [], 'body_text');
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

            $section->addTitle((string) ($item['title'] ?? 'Capítulo'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
            if (mb_strlen((string) ($item['content'] ?? '')) > 900) {
                $section->addPageBreak();
            }
        }
    }

    private function addReferences(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, ['references', 'referencias'], true)) {
                continue;
            }

            $section->addPageBreak();
            $section->addTitle((string) ($item['title'] ?? 'Referências'), 1);
            foreach (preg_split('/\n+/', (string) ($item['content'] ?? '')) ?: [] as $reference) {
                $reference = trim($reference);
                if ($reference !== '') {
                    $section->addText($reference, [], 'references_item');
                }
            }
            break;
        }
    }

    private function addAnnexesAndAppendices(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!str_starts_with($code, 'anexo') && !str_starts_with($code, 'apendice')) {
                continue;
            }
            $section->addPageBreak();
            $section->addTitle((string) ($item['title'] ?? 'Anexo/Apêndice'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }
    }

    private function appendParagraphs(Section $section, string $content): void
    {
        foreach (preg_split('/\n+/', $content) as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $style = mb_strlen($paragraph) > 450 ? 'quote_long' : 'body_text';
            $section->addText($paragraph, [], $style);
        }
    }
}

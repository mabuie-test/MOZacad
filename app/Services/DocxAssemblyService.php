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
        $rules = is_array($formatted['rules'] ?? null) ? $formatted['rules'] : [];

        $phpWord->setDefaultFontName((string) ($rules['font_family'] ?? 'Times New Roman'));
        $phpWord->setDefaultFontSize($this->safeInt($rules['font_size'] ?? 12, 12));

        $phpWord->addParagraphStyle('body_text', ['alignment' => Jc::BOTH, 'spaceAfter' => 160, 'lineHeight' => $this->safeFloat($rules['line_spacing'] ?? 1.5, 1.5), 'indentation' => ['firstLine' => 600]]);
        $phpWord->addParagraphStyle('plain_text', ['alignment' => Jc::LEFT, 'spaceAfter' => 140, 'lineHeight' => $this->safeFloat($rules['line_spacing'] ?? 1.5, 1.5)]);
        $phpWord->addParagraphStyle('quote_long', ['alignment' => Jc::BOTH, 'spaceAfter' => 120, 'lineHeight' => 1.0, 'indentation' => ['left' => 720, 'right' => 720]]);
        $phpWord->addParagraphStyle('references_item', ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'lineHeight' => 1.0, 'indentation' => ['hanging' => 360]]);

        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => $this->safeInt($rules['heading_font_size'] ?? 14, 14)], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => max(12, $this->safeInt($rules['heading_font_size'] ?? 14, 14) - 1)], ['alignment' => Jc::LEFT, 'spaceAfter' => 180]);

        $section = $phpWord->addSection([
            'marginTop' => $this->cmToTwip($rules['margins']['top'] ?? 2.5),
            'marginBottom' => $this->cmToTwip($rules['margins']['bottom'] ?? 2.5),
            'marginLeft' => $this->cmToTwip($rules['margins']['left'] ?? 3.0),
            'marginRight' => $this->cmToTwip($rules['margins']['right'] ?? 3.0),
        ]);

        $frontPage = is_array($rules['front_page'] ?? null) ? $rules['front_page'] : [];
        $sections = is_array($formatted['sections'] ?? null) ? $formatted['sections'] : [];

        $this->addHeaderFooter($section, $frontPage);
        $this->addCoverPage($section, $title, $frontPage);
        $this->addTitlePage($section, $title, $frontPage);
        $this->addPreTextSections($section, $sections, ['resumo', 'abstract']);
        $this->addTableOfContentsPlaceholder($section, $sections);
        $this->addMainChapters($section, $sections);
        $this->addReferences($section, $sections);
        $this->addAnnexesAndAppendices($section, $sections);

        return $phpWord;
    }

    private function addHeaderFooter(Section $section, array $frontPage): void
    {
        $section->addHeader()->addText($this->cleanText((string) ($frontPage['institution_name'] ?? 'MOZacad')), ['size' => 10], ['alignment' => Jc::CENTER]);
        $section->addFooter()->addPreserveText('Página {PAGE}', ['size' => 10], ['alignment' => Jc::RIGHT]);
    }

    private function addCoverPage(Section $section, string $title, array $frontPage): void
    {
        $section->addText($this->cleanText((string) ($frontPage['institution_name'] ?? 'Instituição Académica')), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['faculty'])) {
            $section->addText($this->cleanText((string) $frontPage['faculty']), ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        if (!empty($frontPage['department'])) {
            $section->addText($this->cleanText((string) $frontPage['department']), ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(4);
        $section->addText($this->cleanText($title), ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['student_name'])) {
            $section->addText('Discente: ' . $this->cleanText((string) $frontPage['student_name']), ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        if (!empty($frontPage['supervisor_name'])) {
            $section->addText('Orientador(a): ' . $this->cleanText((string) $frontPage['supervisor_name']), ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(6);
        $section->addText($this->cleanText(((string) ($frontPage['city'] ?? 'Maputo')) . ', ' . ((string) ($frontPage['year'] ?? date('Y')))), ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    private function addTitlePage(Section $section, string $title, array $frontPage): void
    {
        $section->addTitle('Folha de rosto', 1);
        $section->addText($this->cleanText($title), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
        $note = (string) ($frontPage['submission_note'] ?? 'Documento académico elaborado segundo normas institucionais.');
        $section->addText($this->cleanText($note), [], 'body_text');
        $section->addPageBreak();
    }

    private function addPreTextSections(Section $section, array $sections, array $codes): void
    {
        $found = false;
        foreach ($sections as $item) {
            if (!in_array(mb_strtolower((string) ($item['code'] ?? '')), $codes, true)) {
                continue;
            }

            $found = true;
            $section->addTitle($this->cleanText((string) ($item['title'] ?? 'Resumo')), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }

        if (!$found) {
            $section->addTitle('Resumo', 1);
            $section->addText('Este trabalho apresenta uma síntese académica introdutória do tema, com enquadramento conceptual e objectivos coerentes com o contexto de estudo.', [], 'body_text');
        }

        $section->addPageBreak();
    }

    private function addTableOfContentsPlaceholder(Section $section, array $sections): void
    {
        $section->addTitle('Índice', 1);

        $indexable = [];
        foreach ($sections as $item) {
            $title = $this->cleanText((string) ($item['title'] ?? 'Capítulo'));
            if ($title !== '') {
                $indexable[] = $title;
            }
        }

        if ($indexable === []) {
            $section->addText('Índice a ser actualizado conforme a estrutura final do documento.', [], 'plain_text');
        } else {
            foreach ($indexable as $position => $heading) {
                $section->addText(($position + 1) . '. ' . $heading . ' ........................', [], 'plain_text');
            }
        }

        $section->addPageBreak();
    }

    private function addMainChapters(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (in_array($code, ['resumo', 'abstract', 'references', 'referencias'], true) || str_starts_with($code, 'anexo') || str_starts_with($code, 'apendice')) {
                continue;
            }

            $section->addTitle($this->cleanText((string) ($item['title'] ?? 'Capítulo')), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
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
            $section->addTitle($this->cleanText((string) ($item['title'] ?? 'Referências')), 1);
            foreach (preg_split('/\n+/', (string) ($item['content'] ?? '')) ?: [] as $reference) {
                $clean = $this->cleanText($reference);
                if ($clean !== '') {
                    $section->addText($clean, [], 'references_item');
                }
            }
            return;
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
            $section->addTitle($this->cleanText((string) ($item['title'] ?? 'Anexo/Apêndice')), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }
    }

    private function appendParagraphs(Section $section, string $content): void
    {
        foreach (preg_split('/\n+/', $content) ?: [] as $paragraph) {
            $clean = $this->cleanText($paragraph);
            if ($clean === '') {
                continue;
            }

            $isLongCitation = str_starts_with($clean, '"') && mb_strlen($clean) > 280;
            $section->addText($clean, [], $isLongCitation ? 'quote_long' : 'body_text');
        }
    }

    private function cleanText(string $text): string
    {
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', $text) ?? '';
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);
        $clean = preg_replace('/^\s*#{1,6}\s*/m', '', $clean) ?? $clean;
        $clean = str_replace(['**', '__', '`'], '', $clean);
        $clean = preg_replace('/^\s*[-*•]+\s+/m', '', $clean) ?? $clean;

        $blockedPhrases = [
            'revisão humana necessária',
            'resumo indisponível',
            'não foram fornecidos',
            'não foram indicadas fontes',
            'sujeito a revisão humana',
            'não foram disponibilizados',
        ];

        foreach ($blockedPhrases as $phrase) {
            $clean = preg_replace('/' . preg_quote($phrase, '/') . '/iu', '', $clean) ?? $clean;
        }

        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        $clean = preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean;

        return trim($clean);
    }

    private function safeInt(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    private function safeFloat(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    private function cmToTwip(mixed $cm): int
    {
        $val = $this->safeFloat($cm, 2.5);
        return (int) round($val * 567.0);
    }
}

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

        $phpWord->setDefaultFontName((string) ($rules['font_family'] ?? 'Times New Roman'));

        $docInfo = $phpWord->getDocInfo();
        $institutionNorm = is_array($rules['institution_norm'] ?? null) ? $rules['institution_norm'] : [];
        $docInfo->setTitle($title);
        $docInfo->setDescription('Documento académico gerado com perfil institucional MOZacad.');
        $docInfo->setCategory((string) ($rules['references_style'] ?? 'APA'));
        $docInfo->setCompany((string) (($rules['front_page']['institution_name'] ?? 'Instituição Académica')));
        $docInfo->setCustomProperty('institution_norm_source', (string) ($institutionNorm['source'] ?? 'none'));
        $phpWord->setDefaultFontSize((int) ($rules['font_size'] ?? 12));
        $phpWord->addParagraphStyle('body_text', ['alignment' => Jc::BOTH, 'spaceAfter' => 160, 'lineHeight' => (float) ($rules['line_spacing'] ?? 1.5), 'indentation' => ['firstLine' => 600]]);
        $phpWord->addParagraphStyle('quote_long', ['alignment' => Jc::BOTH, 'spaceAfter' => 120, 'lineHeight' => 1.0, 'indentation' => ['left' => 720, 'right' => 720]]);
        $phpWord->addParagraphStyle('references_item', ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'lineHeight' => 1.0, 'indentation' => ['hanging' => 360]]);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => (int) ($rules['heading_font_size'] ?? 14)], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => max(12, (int) (($rules['heading_font_size'] ?? 14) - 1))], ['alignment' => Jc::LEFT, 'spaceAfter' => 180]);

        $section = $phpWord->addSection([
            'marginTop' => (int) round(((float) ($rules['margins']['top'] ?? 2.5)) * 567),
            'marginBottom' => (int) round(((float) ($rules['margins']['bottom'] ?? 2.5)) * 567),
            'marginLeft' => (int) round(((float) ($rules['margins']['left'] ?? 3.0)) * 567),
            'marginRight' => (int) round(((float) ($rules['margins']['right'] ?? 3.0)) * 567),
        ]);

        $frontPage = is_array($rules['front_page'] ?? null) ? $rules['front_page'] : [];
        $sections = is_array($formatted['sections'] ?? null) ? $formatted['sections'] : [];

        $this->addHeaderFooter($section, $frontPage);
        $this->addCoverPage($section, $title, $frontPage);
        $this->addTitlePage($section, $title, $frontPage, $rules['norm_notes'] ?? [], $rules['template_resolution'] ?? []);
        $this->addPreTextSections($section, $sections, ['resumo', 'abstract']);
        $this->addTableOfContents($section);
        $this->addMainChapters($section, $sections);
        $this->addReferences($section, $sections);
        $this->addAnnexesAndAppendices($section, $sections);

        return $phpWord;
    }

    private function addHeaderFooter(Section $section, array $frontPage): void
    {
        $section->addHeader()->addText((string) ($frontPage['institution_name'] ?? 'MOZacad'), ['size' => 10], ['alignment' => Jc::CENTER]);
        $section->addFooter()->addPreserveText('Página {PAGE}', ['size' => 10], ['alignment' => Jc::RIGHT]);
    }

    private function addCoverPage(Section $section, string $title, array $frontPage): void
    {
        $section->addText((string) ($frontPage['institution_name'] ?? 'Instituição Académica'), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['faculty'])) {
            $section->addText((string) $frontPage['faculty'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        if (!empty($frontPage['department'])) {
            $section->addText((string) $frontPage['department'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(4);
        $section->addText($title, ['bold' => true, 'size' => 16], ['alignment' => Jc::CENTER]);
        if (!empty($frontPage['student_name'])) {
            $section->addText('Discente: ' . (string) $frontPage['student_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        if (!empty($frontPage['supervisor_name'])) {
            $section->addText('Orientador(a): ' . (string) $frontPage['supervisor_name'], ['size' => 12], ['alignment' => Jc::CENTER]);
        }
        $section->addTextBreak(6);
        $section->addText(((string) ($frontPage['city'] ?? 'Maputo')) . ', ' . ((string) ($frontPage['year'] ?? date('Y'))), ['size' => 12], ['alignment' => Jc::CENTER]);
        $section->addPageBreak();
    }

    private function addTitlePage(Section $section, string $title, array $frontPage, array $notes, array $templateResolution): void
    {
        $section->addTitle('Folha de rosto', 1);
        $section->addText($title, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);
        $section->addText((string) ($frontPage['submission_note'] ?? 'Documento produzido com apoio assistido por IA e sujeito a revisão humana.'), [], 'body_text');
        if ($notes !== []) {
            $section->addText('Notas institucionais aplicadas: ' . implode(' | ', array_map('strval', $notes)), ['italic' => true], 'body_text');
        }
        $section->addText('Montagem documental: ' . (string) ($templateResolution['mode'] ?? 'programmatic_assembly'), ['italic' => true], 'body_text');
        $section->addPageBreak();
    }

    private function addPreTextSections(Section $section, array $sections, array $codes): void
    {
        $found = false;
        foreach ($sections as $item) {
            if (!in_array(mb_strtolower((string) ($item['code'] ?? '')), $codes, true)) continue;
            $found = true;
            $section->addTitle((string) ($item['title'] ?? 'Resumo'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }
        if (!$found) {
            $section->addTitle('Resumo', 1);
            $section->addText('Resumo indisponível - requer revisão manual.', [], 'body_text');
        }
        $section->addPageBreak();
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
            if (in_array($code, ['resumo', 'abstract', 'references', 'referencias'], true) || str_starts_with($code, 'anexo') || str_starts_with($code, 'apendice')) continue;
            $section->addTitle((string) ($item['title'] ?? 'Capítulo'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }
    }

    private function addReferences(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, ['references', 'referencias'], true)) continue;
            $section->addPageBreak();
            $section->addTitle((string) ($item['title'] ?? 'Referências'), 1);
            foreach (preg_split('/\n+/', (string) ($item['content'] ?? '')) ?: [] as $reference) {
                if (trim($reference) !== '') $section->addText(trim($reference), [], 'references_item');
            }
            break;
        }
    }

    private function addAnnexesAndAppendices(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!str_starts_with($code, 'anexo') && !str_starts_with($code, 'apendice')) continue;
            $section->addPageBreak();
            $section->addTitle((string) ($item['title'] ?? 'Anexo/Apêndice'), 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''));
        }
    }

    private function appendParagraphs(Section $section, string $content): void
    {
        foreach (preg_split('/\n+/', $content) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') continue;
            $isLongCitation = str_starts_with($paragraph, '"') && mb_strlen($paragraph) > 280;
            $section->addText($paragraph, [], $isLongCitation ? 'quote_long' : 'body_text');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

final class DocxAssemblyService
{
    private string $editorialCleanupMode = 'default';

    public function assemble(array $formatted, string $title, array $templateResolution = []): PhpWord
    {
        $phpWord = new PhpWord();
        $rules = is_array($formatted['rules'] ?? null) ? $formatted['rules'] : [];
        $templateMeta = $this->normalizeTemplateMeta($templateResolution);

        $phpWord->setDefaultFontName((string) ($rules['font_family'] ?? 'Times New Roman'));
        $phpWord->setDefaultFontSize($this->safeInt($rules['font_size'] ?? 12, 12));

        $lineSpacing = $this->safeFloat($rules['line_spacing'] ?? 1.5, 1.5);
        $phpWord->addParagraphStyle('body_text', ['alignment' => Jc::BOTH, 'spaceAfter' => 160, 'lineHeight' => $lineSpacing, 'indentation' => ['firstLine' => 600]]);
        $phpWord->addParagraphStyle('plain_text', ['alignment' => Jc::LEFT, 'spaceAfter' => 140, 'lineHeight' => $lineSpacing]);
        $phpWord->addParagraphStyle('quote_long', ['alignment' => Jc::BOTH, 'spaceAfter' => 120, 'lineHeight' => 1.0, 'indentation' => ['left' => 720, 'right' => 720]]);
        $phpWord->addParagraphStyle('references_item', ['alignment' => Jc::LEFT, 'spaceAfter' => 100, 'lineHeight' => 1.0, 'indentation' => ['hanging' => 360]]);

        $headingSize = $this->safeInt($rules['heading_font_size'] ?? 14, 14);
        $phpWord->addTitleStyle(1, ['bold' => true, 'size' => $headingSize], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
        $phpWord->addTitleStyle(2, ['bold' => true, 'size' => max(12, $headingSize - 1)], ['alignment' => Jc::LEFT, 'spaceAfter' => 180]);

        $section = $phpWord->addSection([
            'marginTop' => $this->cmToTwip($rules['margins']['top'] ?? 2.5),
            'marginBottom' => $this->cmToTwip($rules['margins']['bottom'] ?? 2.5),
            'marginLeft' => $this->cmToTwip($rules['margins']['left'] ?? 3.0),
            'marginRight' => $this->cmToTwip($rules['margins']['right'] ?? 3.0),
        ]);

        $frontPage = is_array($rules['front_page'] ?? null) ? $rules['front_page'] : [];
        $sections = is_array($formatted['sections'] ?? null) ? $formatted['sections'] : [];
        $profile = $this->resolveAssemblyProfile((string) ($rules['assembly_profile'] ?? 'strict_academic'));
        $this->editorialCleanupMode = $this->resolveEditorialCleanupMode((string) ($rules['editorial_cleanup_mode'] ?? 'default'));

        $this->addHeaderFooter($section, $frontPage);

        if ($this->isFrontBlockEnabled($frontPage, 'technical_cover_enabled', true)) {
            $this->addCoverPage($section, $title, $frontPage, $templateMeta);
        }

        if ($this->isFrontBlockEnabled($frontPage, 'title_page_enabled', true)) {
            $this->addTitlePage($section, $title, $frontPage, $profile);
        }

        $this->addPreTextSections($section, $sections, ['resumo', 'abstract'], $frontPage);

        if ($this->isFrontBlockEnabled($frontPage, 'table_of_contents_enabled', true)) {
            $this->addTableOfContentsPlaceholder($section, $profile);
        }
        $this->addMainChapters($section, $sections);
        $this->addReferences($section, $sections);
        $this->addAnnexesAndAppendices($section, $sections);

        return $phpWord;
    }

    public function buildTemplateApplicationRecord(array $templateResolution): array
    {
        return $this->normalizeTemplateMeta($templateResolution);
    }

    private function addHeaderFooter(Section $section, array $frontPage): void
    {
        $headerText = $this->cleanText((string) ($frontPage['institution_name'] ?? 'MOZacad'));
        $section->addHeader()->addText($headerText !== '' ? $headerText : 'MOZacad', ['size' => 10], ['alignment' => Jc::CENTER]);
        $section->addFooter()->addPreserveText('Página {PAGE}', ['size' => 10], ['alignment' => Jc::RIGHT]);
    }

    private function addCoverPage(Section $section, string $title, array $frontPage, array $templateMeta): void
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
        $city = $this->cleanText((string) ($frontPage['city'] ?? 'Maputo'));
        $year = $this->cleanText((string) ($frontPage['year'] ?? date('Y')));
        $section->addText($city . ', ' . $year, ['size' => 12], ['alignment' => Jc::CENTER]);
        if ($this->shouldRenderTemplateNote($frontPage, $templateMeta)) {
            $section->addText(
                'Template aplicado: ' . $this->cleanText((string) ($templateMeta['template_file'] ?? 'programmatic_fallback'))
                . ' | ID: ' . $this->cleanText((string) ($templateMeta['template_artifact_id'] ?? 'n/a'))
                . ' | HASH: ' . $this->cleanText((string) ($templateMeta['template_sha256'] ?? 'n/a')),
                ['size' => 8],
                ['alignment' => Jc::CENTER]
            );
        }
        $section->addPageBreak();
    }

    private function normalizeTemplateMeta(array $templateResolution): array
    {
        $traceability = is_array($templateResolution['traceability'] ?? null) ? $templateResolution['traceability'] : [];
        $file = (string) ($templateResolution['selected_template'] ?? basename((string) ($templateResolution['candidate_path'] ?? '')));
        if ($file === '') {
            $file = 'programmatic_fallback';
        }

        return [
            'mode' => (string) ($templateResolution['mode'] ?? 'programmatic_fallback'),
            'template_file' => $file,
            'template_artifact_id' => $traceability['artifact_id'] ?? null,
            'template_sha256' => $traceability['tracked_checksum'] ?? null,
            'reason' => (string) ($templateResolution['reason'] ?? ''),
        ];
    }

    private function addTitlePage(Section $section, string $title, array $frontPage, string $profile): void
    {
        $section->addTitle('Folha de rosto', 1);
        $section->addText($this->cleanText($title), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
        $section->addTextBreak(1);

        $note = $this->cleanText((string) ($frontPage['submission_note'] ?? ''));
        if ($note !== '') {
            $section->addText($note, [], 'body_text');
        } elseif ($profile === 'strict_academic') {
            $section->addText('Documento académico.', [], 'body_text');
        }
        $section->addPageBreak();
    }

    private function addPreTextSections(Section $section, array $sections, array $codes, array $frontPage): void
    {
        $found = false;
        foreach ($sections as $item) {
            if (!in_array(mb_strtolower((string) ($item['code'] ?? '')), $codes, true)) {
                continue;
            }

            $found = true;
            $title = $this->cleanText((string) ($item['title'] ?? 'Resumo'));
            $section->addTitle($title !== '' ? $title : 'Resumo', 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $title);
        }

        if (!$found && $this->isFrontBlockEnabled($frontPage, 'pretext_missing_summary_warning_enabled', false)) {
            @trigger_error('Resumo/Abstract ausente nas secções pré-textuais do documento.', E_USER_WARNING);
        }

        if ($found) {
            $section->addPageBreak();
        }
    }

    private function addTableOfContentsPlaceholder(Section $section, string $profile): void
    {
        $section->addTitle('Índice', 1);
        if ($profile !== 'institutional') {
            $section->addText('Índice automático (actualizável no editor de texto).', [], 'plain_text');
        }
        $section->addTOC(['size' => 11]);
        $section->addPageBreak();
    }

    private function addMainChapters(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (in_array($code, ['resumo', 'abstract', 'references', 'referencias'], true) || str_starts_with($code, 'anexo') || str_starts_with($code, 'apendice')) {
                continue;
            }

            $title = $this->cleanText((string) ($item['title'] ?? 'Capítulo'));
            $safeTitle = $title !== '' ? $title : 'Capítulo';
            $section->addTitle($safeTitle, 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $safeTitle);
        }
    }

    private function addReferences(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, ['references', 'referencias'], true)) {
                continue;
            }

            $title = $this->cleanText((string) ($item['title'] ?? 'Referências'));
            $section->addPageBreak();
            $section->addTitle($title !== '' ? $title : 'Referências', 1);

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

            $title = $this->cleanText((string) ($item['title'] ?? 'Anexo/Apêndice'));
            $safeTitle = $title !== '' ? $title : 'Anexo/Apêndice';
            $section->addPageBreak();
            $section->addTitle($safeTitle, 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $safeTitle);
        }
    }


    private function isFrontBlockEnabled(array $frontPage, string $flag, bool $default): bool
    {
        if (!array_key_exists($flag, $frontPage)) {
            return $default;
        }

        return filter_var($frontPage[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function shouldRenderTemplateNote(array $frontPage, array $templateMeta): bool
    {
        if (($templateMeta['mode'] ?? '') === 'template_published_tracked') {
            return true;
        }

        return $this->isFrontBlockEnabled($frontPage, 'template_note_enabled', true);
    }

    private function resolveAssemblyProfile(string $profile): string
    {
        $allowed = ['strict_academic', 'light_academic', 'institutional'];

        return in_array($profile, $allowed, true) ? $profile : 'strict_academic';
    }

    private function appendParagraphs(Section $section, string $content, ?string $sectionTitle = null): void
    {
        $normalizedTitle = $sectionTitle !== null ? mb_strtolower(trim($this->cleanText($sectionTitle), " \t\n\r\0\x0B.:;")) : '';

        foreach (preg_split('/\n+/', $content) ?: [] as $paragraph) {
            $clean = $this->cleanText($paragraph);
            if ($clean === '') {
                continue;
            }

            if ($normalizedTitle !== '') {
                $normalizedParagraph = mb_strtolower(trim($clean, " \t\n\r\0\x0B.:;"));
                if ($normalizedParagraph === $normalizedTitle) {
                    continue;
                }
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
        $clean = preg_replace('/^\s*>+\s*/m', '', $clean) ?? $clean;
        $clean = preg_replace('/\{\s*"[^"]+"\s*:\s*.*\}/u', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(section_title|section_code|payload|debug|hash|id_interno)\b\s*:?/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/com base nas regras de refinamento[^\.]*\.?/iu', '', $clean) ?? $clean;

        if ($this->editorialCleanupMode === 'strict_editorial_cleanup') {
            $blockedPhrases = [
                'Revisão humana necessária',
                'Resumo indisponível',
                'requer revisão manual',
                'sujeito a revisão humana',
                '[[REVISAR]]',
                '[[TODO_EDITORIAL]]',
            ];

            foreach ($blockedPhrases as $phrase) {
                $clean = preg_replace('/' . preg_quote($phrase, '/') . '/iu', '', $clean) ?? $clean;
            }
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

    private function resolveEditorialCleanupMode(string $mode): string
    {
        $allowed = ['default', 'strict_editorial_cleanup'];

        return in_array($mode, $allowed, true) ? $mode : 'default';
    }
}

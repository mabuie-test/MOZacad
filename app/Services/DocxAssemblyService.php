<?php
declare(strict_types=1);
namespace App\Services;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

final class DocxAssemblyService
{
    private string $editorialCleanupMode = 'default';

    public function assemble(array $formatted, string $title, array $templateResolution = []): PhpWord
    {
        Settings::setOutputEscapingEnabled(true);
        if (method_exists(Settings::class, 'setUpdateFields')) {
            Settings::setUpdateFields(true);
        }

        $phpWord      = new PhpWord();
        $rules        = is_array($formatted['rules'] ?? null) ? $formatted['rules'] : [];
        $templateMeta = $this->normalizeTemplateMeta($templateResolution);

        $fontFamily  = (string) ($rules['font_family'] ?? 'Times New Roman');
        $fontSize    = $this->safeInt($rules['font_size'] ?? 12, 12);
        $lineSpacing = $this->safeFloat($rules['line_spacing'] ?? 1.5, 1.5);
        $headingSize = $this->safeInt($rules['heading_font_size'] ?? 14, 14);

        $phpWord->setDefaultFontName($fontFamily);
        $phpWord->setDefaultFontSize($fontSize);

        // ── Estilos de parágrafo ──────────────────────────────────────────
        $phpWord->addParagraphStyle('body_text',        ['alignment' => Jc::BOTH,  'spaceAfter' => 160, 'lineHeight' => $lineSpacing, 'indentation' => ['firstLine' => 600]]);
        $phpWord->addParagraphStyle('plain_text',       ['alignment' => Jc::LEFT,  'spaceAfter' => 140, 'lineHeight' => $lineSpacing]);
        $phpWord->addParagraphStyle('quote_long',       ['alignment' => Jc::BOTH,  'spaceAfter' => 120, 'lineHeight' => 1.0, 'indentation' => ['left' => 720, 'right' => 720]]);
        $phpWord->addParagraphStyle('references_item',  ['alignment' => Jc::LEFT,  'spaceAfter' => 100, 'lineHeight' => 1.0, 'indentation' => ['hanging' => 360]]);
        $phpWord->addParagraphStyle('toc_note',         ['alignment' => Jc::CENTER,'spaceAfter' => 80]);

        // ── Estilos de título (outlineLevel obrigatório para TOC) ─────────
        $phpWord->addTitleStyle(1,
            ['bold' => true,  'size' => $headingSize,              'name' => $fontFamily],
            ['alignment' => Jc::LEFT, 'spaceBefore' => 220, 'spaceAfter' => 220, 'keepNext' => true, 'outlineLevel' => 0]
        );
        $phpWord->addTitleStyle(2,
            ['bold' => true,  'size' => max(12, $headingSize - 1), 'name' => $fontFamily],
            ['alignment' => Jc::LEFT, 'spaceBefore' => 320, 'spaceAfter' => 240, 'keepNext' => true, 'outlineLevel' => 1]
        );
        $phpWord->addTitleStyle(3,
            ['bold' => false, 'size' => max(11, $headingSize - 2), 'name' => $fontFamily],
            ['alignment' => Jc::LEFT, 'spaceBefore' => 200, 'spaceAfter' => 160, 'keepNext' => true, 'outlineLevel' => 2]
        );

        // ── Secção com margens institucionais ────────────────────────────
        $margins = is_array($rules['margins'] ?? null) ? $rules['margins'] : [];
        $section = $phpWord->addSection([
            'marginTop'    => $this->cmToTwip($margins['top']    ?? $rules['margin_top']    ?? 2.5),
            'marginBottom' => $this->cmToTwip($margins['bottom'] ?? $rules['margin_bottom'] ?? 2.5),
            'marginLeft'   => $this->cmToTwip($margins['left']   ?? $rules['margin_left']   ?? 3.0),
            'marginRight'  => $this->cmToTwip($margins['right']  ?? $rules['margin_right']  ?? 3.0),
        ]);

        $frontPage      = is_array($rules['front_page'] ?? null) ? $rules['front_page'] : [];
        $sections       = is_array($formatted['sections'] ?? null) ? $formatted['sections'] : [];
        $orderedSections = $this->normalizeOrderedSections($sections);
        $profile        = $this->resolveAssemblyProfile((string) ($rules['assembly_profile'] ?? 'strict_academic'));
        $this->editorialCleanupMode = $this->resolveEditorialCleanupMode((string) ($rules['editorial_cleanup_mode'] ?? 'default'));

        $this->addHeaderFooter($section, $frontPage, $rules);

        if ($this->isFrontBlockEnabled($frontPage, 'technical_cover_enabled', true)) {
            $this->addCoverPage($section, $title, $frontPage, $templateMeta, $rules);
        }

        if ($this->isFrontBlockEnabled($frontPage, 'title_page_enabled', true)) {
            $this->addTitlePage($section, $title, $frontPage, $profile);
        }

        // Resumo/Abstract (pré-textual, sem numeração de página no TOC)
        $this->addPreTextSections($section, $orderedSections, ['resumo', 'abstract'], $frontPage);

        if ($this->isFrontBlockEnabled($frontPage, 'table_of_contents_enabled', true)) {
            $this->addTableOfContents($section, $profile, $rules);
        }

        $this->addMainChapters($section, $orderedSections);
        $this->addReferences($section, $orderedSections);
        $this->addAnnexesAndAppendices($section, $orderedSections);

        return $phpWord;
    }

    public function buildTemplateApplicationRecord(array $templateResolution): array
    {
        return $this->normalizeTemplateMeta($templateResolution);
    }

    // ── Ordenação das secções ────────────────────────────────────────────

    private function normalizeOrderedSections(array $sections): array
    {
        $canonicalOrder = [
            'folha_de_rosto' => 10, 'resumo' => 20, 'indice' => 30,
            'introducao' => 40, 'objectivos' => 50, 'metodologia' => 60,
            'desenvolvimento' => 70, 'conclusao' => 80,
            'referencias' => 90, 'annex' => 100, 'other' => 110,
        ];

        $indexed = array_map(
            static fn (array $s, int $i): array => ['section' => $s, 'index' => $i],
            $sections, array_keys($sections)
        );

        usort($indexed, function (array $a, array $b) use ($canonicalOrder): int {
            $aClass = $this->classifySectionKey($a['section']);
            $bClass = $this->classifySectionKey($b['section']);
            $diff   = ($canonicalOrder[$aClass] ?? 110) <=> ($canonicalOrder[$bClass] ?? 110);
            return $diff !== 0 ? $diff : $a['index'] <=> $b['index'];
        });

        return array_values(array_map(static fn ($item) => $item['section'], $indexed));
    }

    private function classifySectionKey(array $section): string
    {
        $raw = mb_strtolower((string) ($section['code'] ?? '') . ' ' . (string) ($section['title'] ?? ''));
        if (str_contains($raw, 'rosto'))                                                                  return 'folha_de_rosto';
        if (str_contains($raw, 'resumo') || str_contains($raw, 'abstract'))                             return 'resumo';
        if (str_contains($raw, 'indice') || str_contains($raw, 'índice'))                               return 'indice';
        if (str_contains($raw, 'introdu'))                                                               return 'introducao';
        if (str_contains($raw, 'objet') || str_contains($raw, 'objec'))                                 return 'objectivos';
        if (str_contains($raw, 'metod'))                                                                 return 'metodologia';
        if (str_contains($raw, 'analis') || str_contains($raw, 'desenvol') || str_contains($raw, 'result') || str_contains($raw, 'discuss')) return 'desenvolvimento';
        if (str_contains($raw, 'conclus') || str_contains($raw, 'consideraç'))                          return 'conclusao';
        if (str_contains($raw, 'refer') || str_contains($raw, 'bibliograf'))                            return 'referencias';
        $code = trim((string) ($section['code'] ?? ''));
        if (str_starts_with($code, 'anexo') || str_starts_with($code, 'apendice'))                      return 'annex';
        return 'other';
    }

    // ── Cabeçalho / Rodapé ───────────────────────────────────────────────

    private function addHeaderFooter(Section $section, array $frontPage, array $rules): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('{PAGE}', ['size' => 10, 'name' => (string) ($rules['font_family'] ?? 'Times New Roman')], ['alignment' => Jc::RIGHT]);
    }

    // ── Capa ─────────────────────────────────────────────────────────────

    private function addCoverPage(Section $section, string $title, array $frontPage, array $templateMeta, array $rules): void
    {
        $font = (string) ($rules['font_family'] ?? 'Times New Roman');

        $section->addText(
            $this->cleanText((string) ($frontPage['institution_name'] ?? 'Instituição Académica')),
            ['bold' => true, 'size' => 14, 'name' => $font],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 120]
        );
        if (!empty($frontPage['faculty'])) {
            $section->addText($this->cleanText((string) $frontPage['faculty']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        }
        if (!empty($frontPage['department'])) {
            $section->addText($this->cleanText((string) $frontPage['department']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        }
        if (!empty($frontPage['course'])) {
            $section->addText($this->cleanText((string) $frontPage['course']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        }

        $section->addTextBreak(4);
        $section->addText(
            $this->cleanText($title),
            ['bold' => true, 'size' => 16, 'name' => $font],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 200]
        );

        if (!empty($frontPage['student_name'])) {
            $section->addText('Discente: ' . $this->cleanText((string) $frontPage['student_name']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        }
        if (!empty($frontPage['student_number'])) {
            $section->addText('Nº: ' . $this->cleanText((string) $frontPage['student_number']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        }
        if (!empty($frontPage['supervisor_name'])) {
            $section->addText('Orientador(a): ' . $this->cleanText((string) $frontPage['supervisor_name']), ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER, 'spaceAfter' => 80]);
        }

        $section->addTextBreak(5);
        $city = $this->cleanText((string) ($frontPage['city'] ?? 'Maputo'));
        $year = $this->cleanText((string) ($frontPage['year'] ?? date('Y')));
        $section->addText("{$city}, {$year}", ['size' => 12, 'name' => $font], ['alignment' => Jc::CENTER]);

        if ($this->shouldRenderTemplateNote($frontPage, $templateMeta)) {
            $section->addText(
                'Template: ' . $this->cleanText((string) ($templateMeta['template_file'] ?? 'programmatic'))
                . ' | ' . $this->cleanText((string) ($templateMeta['template_artifact_id'] ?? 'n/a')),
                ['size' => 7, 'color' => 'AAAAAA', 'name' => $font],
                ['alignment' => Jc::CENTER]
            );
        }

        $section->addPageBreak();
    }

    // ── Folha de rosto ───────────────────────────────────────────────────

    private function addTitlePage(Section $section, string $title, array $frontPage, string $profile): void
    {
        $section->addTitle('Folha de Rosto', 1);
        $section->addText($this->cleanText($title), ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);
        $section->addTextBreak(1);

        $note = $this->cleanText((string) ($frontPage['submission_note'] ?? ''));
        if ($note !== '') {
            $section->addText($note, ['italic' => true, 'size' => 12], ['alignment' => Jc::BOTH, 'spaceAfter' => 180, 'lineHeight' => 1.3, 'indentation' => ['left' => 480]]);
        } elseif ($profile === 'strict_academic') {
            $section->addText(
                'Trabalho apresentado como requisito para a avaliação académica.',
                ['italic' => true, 'size' => 12],
                ['alignment' => Jc::BOTH, 'spaceAfter' => 180, 'lineHeight' => 1.3, 'indentation' => ['left' => 480]]
            );
        }

        $section->addPageBreak();
    }

    // ── Secções pré-textuais (resumo/abstract) ───────────────────────────

    private function addPreTextSections(Section $section, array $sections, array $codes, array $frontPage): void
    {
        $found = false;
        foreach ($sections as $item) {
            if (!in_array(mb_strtolower((string) ($item['code'] ?? '')), $codes, true)) continue;
            $found = true;
            $titleText = $this->cleanText((string) ($item['title'] ?? 'Resumo'));
            $section->addTitle($titleText !== '' ? $titleText : 'Resumo', 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $titleText);
        }
        if ($found) $section->addPageBreak();
    }

    // ── Índice (TOC real com pontilhado e números de página) ─────────────

    private function addTableOfContents(Section $section, string $profile, array $rules): void
    {
        $margins     = is_array($rules['margins'] ?? null) ? $rules['margins'] : [];
        $leftMargin  = $this->cmToTwip($margins['left']  ?? $rules['margin_left']  ?? 3.0);
        $rightMargin = $this->cmToTwip($margins['right'] ?? $rules['margin_right'] ?? 3.0);
        $usableWidth = max(5000, 11906 - $leftMargin - $rightMargin);
        $fontSize    = $this->safeInt($rules['font_size'] ?? 12, 12);

        // O título "Índice" é adicionado sem outlineLevel para NÃO aparecer no próprio TOC
        $section->addText(
            'Índice',
            ['bold' => true, 'size' => $this->safeInt($rules['heading_font_size'] ?? 14, 14)],
            ['alignment' => Jc::LEFT, 'spaceBefore' => 220, 'spaceAfter' => 220]
        );

        // TOC nativo do PhpWord:
        // - tabLeader 'dot' → pontilhado entre título e número de página
        // - tabPos → posição da tabulação (limite da área útil)
        // - minDepth 1 / maxDepth 2 → apanha Heading 1 e Heading 2
        // Settings::setUpdateFields(true) já está no assemble() → Word actualiza ao abrir
        $section->addTOC(
            ['size' => $fontSize, 'name' => (string) ($rules['font_family'] ?? 'Times New Roman')],
            ['tabLeader' => 'dot', 'tabPos' => $usableWidth],
            1,
            2
        );

        // Nota instrutiva (pequena, discreta)
        $section->addText(
            'Para actualizar os números de página: seleccionar o índice → F9 (ou clique direito → "Actualizar campo").',
            ['size' => 8, 'italic' => true, 'color' => '999999'],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 80]
        );

        $section->addPageBreak();
    }

    // ── Capítulos principais ─────────────────────────────────────────────

    private function addMainChapters(Section $section, array $sections): void
    {
        $renderedFirst = false;

        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (in_array($code, ['resumo', 'abstract', 'references', 'referencias'], true)
                || str_starts_with($code, 'anexo')
                || str_starts_with($code, 'apendice')) {
                continue;
            }

            $title         = $this->cleanText((string) ($item['title'] ?? 'Capítulo'));
            $safeTitle     = $title !== '' ? $title : 'Capítulo';
            $isDevelopment = $this->classifySectionKey($item) === 'desenvolvimento';

            if ($renderedFirst) $section->addPageBreak();

            if (!$isDevelopment) {
                $section->addTitle($safeTitle, 1);
            }

            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $safeTitle, $isDevelopment);
            $renderedFirst = true;
        }
    }

    // ── Referências bibliográficas ────────────────────────────────────────

    private function addReferences(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!in_array($code, ['references', 'referencias'], true)) continue;

            $title = $this->cleanText((string) ($item['title'] ?? 'Referências'));
            $section->addPageBreak();
            $section->addTitle($title !== '' ? $title : 'Referências', 1);

            $lines = preg_split('/\n+/', (string) ($item['content'] ?? '')) ?: [];
            foreach ($lines as $line) {
                $clean = $this->cleanText($line);
                if ($clean !== '') {
                    $section->addText($clean, [], 'references_item');
                }
            }
            return;
        }
    }

    // ── Anexos e apêndices ────────────────────────────────────────────────

    private function addAnnexesAndAppendices(Section $section, array $sections): void
    {
        foreach ($sections as $item) {
            $code = mb_strtolower((string) ($item['code'] ?? ''));
            if (!str_starts_with($code, 'anexo') && !str_starts_with($code, 'apendice')) continue;
            $title     = $this->cleanText((string) ($item['title'] ?? 'Anexo'));
            $safeTitle = $title !== '' ? $title : 'Anexo';
            $section->addPageBreak();
            $section->addTitle($safeTitle, 1);
            $this->appendParagraphs($section, (string) ($item['content'] ?? ''), $safeTitle);
        }
    }

    // ── Renderização de parágrafos ────────────────────────────────────────

    private function appendParagraphs(Section $section, string $content, ?string $sectionTitle = null, bool $isDevelopment = false): void
    {
        $normalizedTitle = $sectionTitle !== null
            ? mb_strtolower(trim($this->cleanText($sectionTitle), " \t\n\r\0\x0B.:;"))
            : '';

        foreach (preg_split('/\n+/', $content) ?: [] as $paragraph) {
            $clean = $this->cleanText($paragraph);
            if ($clean === '') continue;

            // Evitar duplicar o título da secção como parágrafo
            if ($normalizedTitle !== '') {
                $normPar = mb_strtolower(trim($clean, " \t\n\r\0\x0B.:;"));
                if ($normPar === $normalizedTitle) continue;
            }

            // Subtítulos numerados no desenvolvimento (ex: "1. Título", "1.1 Subtítulo")
            if ($isDevelopment && preg_match('/^(\d+(?:\.\d+)*)(?:\.)?[ \t]+(.+)/u', $clean, $m) === 1) {
                $level = substr_count($m[1], '.') === 0 ? 1 : 2;
                if ($level === 1) $section->addPageBreak();
                $section->addTitle(trim($m[1] . '. ' . $m[2]), $level);
                continue;
            }

            // Citações longas (> 280 chars iniciadas com aspas) → recuadas
            $isLongQuote = str_starts_with($clean, '"') && mb_strlen($clean) > 280;
            $section->addText($clean, [], $isLongQuote ? 'quote_long' : 'body_text');
        }
    }

    // ── Limpeza de texto ──────────────────────────────────────────────────

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
            foreach (['Revisão humana necessária', 'Resumo indisponível', 'requer revisão manual', '[[REVISAR]]', '[[TODO_EDITORIAL]]'] as $phrase) {
                $clean = preg_replace('/' . preg_quote($phrase, '/') . '/iu', '', $clean) ?? $clean;
            }
        }

        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;
        $clean = preg_replace('/\s{2,}/u', ' ', $clean) ?? $clean;
        return trim($clean);
    }

    // ── Utilitários ───────────────────────────────────────────────────────

    private function normalizeTemplateMeta(array $tr): array
    {
        $traceability = is_array($tr['traceability'] ?? null) ? $tr['traceability'] : [];
        $file = (string) ($tr['selected_template'] ?? basename((string) ($tr['candidate_path'] ?? '')));
        return [
            'mode'                => (string) ($tr['mode'] ?? 'programmatic_fallback'),
            'template_file'       => $file !== '' ? $file : 'programmatic_fallback',
            'template_artifact_id'=> $traceability['artifact_id'] ?? null,
            'template_sha256'     => $traceability['tracked_checksum'] ?? null,
            'reason'              => (string) ($tr['reason'] ?? ''),
        ];
    }

    private function isFrontBlockEnabled(array $frontPage, string $flag, bool $default): bool
    {
        if (!array_key_exists($flag, $frontPage)) return $default;
        return filter_var($frontPage[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    private function shouldRenderTemplateNote(array $frontPage, array $templateMeta): bool
    {
        return $this->isFrontBlockEnabled($frontPage, 'template_note_enabled', false);
    }

    private function resolveAssemblyProfile(string $profile): string
    {
        return in_array($profile, ['strict_academic', 'light_academic', 'institutional'], true) ? $profile : 'strict_academic';
    }

    private function resolveEditorialCleanupMode(string $mode): string
    {
        return in_array($mode, ['default', 'strict_editorial_cleanup'], true) ? $mode : 'default';
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
        return (int) round($this->safeFloat($cm, 2.5) * 567.0);
    }
}

<?php
declare(strict_types=1);
namespace App\Services;

use App\Support\UnicodeWordCounter;

final class InstitutionFormattingService
{
    public function apply(array $sections, array $rules): array
    {
        $visualRules    = is_array($rules['visualRules']    ?? null) ? $rules['visualRules']    : [];
        $referenceRules = is_array($rules['referenceRules'] ?? null) ? $rules['referenceRules'] : [];
        $meta           = is_array($rules['meta']           ?? null) ? $rules['meta']           : [];

        $frontPage      = is_array($visualRules['front_page'] ?? null) ? $visualRules['front_page'] : [];

        // ── Resolve margens: suporta tanto estrutura plana quanto aninhada ──
        $margins = [
            'top'    => (float) ($visualRules['margins']['top']    ?? $visualRules['margin_top']    ?? 2.5),
            'bottom' => (float) ($visualRules['margins']['bottom'] ?? $visualRules['margin_bottom'] ?? 2.5),
            'left'   => (float) ($visualRules['margins']['left']   ?? $visualRules['margin_left']   ?? 3.0),
            'right'  => (float) ($visualRules['margins']['right']  ?? $visualRules['margin_right']  ?? 3.0),
        ];

        $referenceStyle = strtoupper(trim((string) ($referenceRules['style'] ?? 'APA')));

        $normalizedSections = $this->normalizeAcademicSections($sections);

        return [
            'rules' => [
                // Tipografia
                'font_family'        => (string) ($visualRules['font_family']       ?? 'Times New Roman'),
                'font_size'          => (float)  ($visualRules['font_size']         ?? 12),
                'heading_font_size'  => (float)  ($visualRules['heading_font_size'] ?? 14),
                'line_spacing'       => (float)  ($visualRules['line_spacing']      ?? 1.5),
                // Margens (estrutura aninhada para DocxAssemblyService)
                'margins'            => $margins,
                // Norma e estilo de referências
                'references_style'   => $referenceStyle,
                // Blocos de capa/índice
                'front_page'         => $frontPage,
                // Metadados de norma institucional (passados ao PromptComposer via rules)
                'institution_norm'   => $meta['institution_norm']   ?? [],
                'norm_notes'         => $meta['notes']              ?? [],
                'norm_profile'       => $meta['institution_norm']['profile'] ?? [],
                'template_resolution'=> $meta['template_resolution'] ?? ['mode' => 'programmatic_assembly'],
            ],
            'sections' => array_map(static function (array $section) use ($referenceStyle): array {
                $content = trim((string) ($section['content'] ?? ''));
                return $section + [
                    'word_count'            => UnicodeWordCounter::count($content),
                    'format_profile'        => 'institutional',
                    'applied_reference_style' => $referenceStyle,
                    'requires_manual_review'=> str_contains(mb_strtolower($content), 'revisão manual')
                                              || str_contains(mb_strtolower($content), '[verificar]')
                                              || (bool) ($section['requires_manual_completion'] ?? false),
                ];
            }, $normalizedSections),
            'meta' => [
                'formatted_at'       => date('c'),
                'section_count'      => count($sections),
                'reference_style'    => $referenceStyle,
                'font_family'        => (string) ($visualRules['font_family'] ?? 'Times New Roman'),
                'margins_applied'    => $margins,
            ],
        ];
    }

    // ── Ordenação das secções académicas ────────────────────────────────

    private function normalizeAcademicSections(array $sections): array
    {
        $priority  = ['introducao', 'objectivos', 'metodologia', 'desenvolvimento', 'conclusao', 'references'];
        $ordered   = [];
        $remaining = array_values($sections);

        foreach ($priority as $wanted) {
            foreach ($remaining as $idx => $section) {
                $key = $this->normalizeKey((string) ($section['code'] ?? $section['title'] ?? ''));
                if ($this->isEquivalentSection($wanted, $key)) {
                    $ordered[] = $section;
                    unset($remaining[$idx]);
                }
            }
        }

        // Secções não mapeadas vão para o final
        foreach ($remaining as $section) {
            $ordered[] = $section;
        }

        return array_values($ordered);
    }

    private function isEquivalentSection(string $canonical, string $value): bool
    {
        return in_array($value, match ($canonical) {
            'introducao'    => ['introducao', 'introduction'],
            'objectivos'    => ['objectivos', 'objetivos', 'objective', 'objectives'],
            'metodologia'   => ['metodologia', 'methodology'],
            'desenvolvimento' => ['desenvolvimento', 'fundamentacao', 'development', 'revisao literatura', 'revisao de literatura'],
            'conclusao'     => ['conclusao', 'consideracoes finais', 'considerações finais'],
            'references'    => ['references', 'referencias', 'referências', 'bibliografia'],
            default         => [$canonical],
        }, true);
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);
        $value = preg_replace('/[^a-z0-9\s_]/u', ' ', $value) ?? $value;
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }
}
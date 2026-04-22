<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ResolvedRuleSetDTO;

final class RuleResolverService
{
    public function resolve(array $institutionRules, array $workTypeRules, array $academicLevelRules, array $normDocumentContext = []): ResolvedRuleSetDTO
    {
        $metadata = is_array($normDocumentContext['metadata'] ?? null) ? $normDocumentContext['metadata'] : [];

        $defaultsVisual = [
            'font_family' => 'Times New Roman', 'font_size' => 12, 'heading_font_size' => 14, 'line_spacing' => 1.5,
            'margin_top' => 2.5, 'margin_right' => 3, 'margin_bottom' => 2.5, 'margin_left' => 3,
        ];
        $defaultsReference = ['style' => 'APA', 'citation_profile_id' => null];
        $defaultsStructure = ['custom_structure' => []];

        $institutionVisual = array_filter([
            'font_family' => $institutionRules['font_family'] ?? null,
            'font_size' => isset($institutionRules['font_size']) ? (float) $institutionRules['font_size'] : null,
            'heading_font_size' => isset($institutionRules['heading_font_size']) ? (float) $institutionRules['heading_font_size'] : null,
            'line_spacing' => isset($institutionRules['line_spacing']) ? (float) $institutionRules['line_spacing'] : null,
            'margin_top' => isset($institutionRules['margin_top']) ? (float) $institutionRules['margin_top'] : null,
            'margin_right' => isset($institutionRules['margin_right']) ? (float) $institutionRules['margin_right'] : null,
            'margin_bottom' => isset($institutionRules['margin_bottom']) ? (float) $institutionRules['margin_bottom'] : null,
            'margin_left' => isset($institutionRules['margin_left']) ? (float) $institutionRules['margin_left'] : null,
        ], static fn (mixed $v): bool => $v !== null);

        $workVisual = $this->decodeJson($workTypeRules['custom_visual_rules_json'] ?? null);
        $workReference = $this->decodeJson($workTypeRules['custom_reference_rules_json'] ?? null);
        $workStructure = $this->decodeJson($workTypeRules['custom_structure_json'] ?? null);

        $frontPageInstitution = $this->decodeJson($institutionRules['front_page_rules_json'] ?? null);
        $frontPageMetadata = is_array($metadata['front_page_overrides'] ?? null) ? $metadata['front_page_overrides'] : [];

        $visual = array_merge($defaultsVisual, $institutionVisual, $workVisual, (array) ($metadata['visual_overrides'] ?? []));
        $visual['front_page'] = array_merge($frontPageInstitution, $frontPageMetadata);

        $reference = array_merge($defaultsReference, array_filter([
            'style' => $institutionRules['references_style'] ?? null,
            'citation_profile_id' => $institutionRules['citation_profile_id'] ?? null,
        ], static fn (mixed $v): bool => $v !== null), $workReference);
        if (is_string($metadata['reference_style'] ?? null) && trim((string) $metadata['reference_style']) !== '') {
            $reference['style'] = strtoupper(trim((string) $metadata['reference_style']));
        }

        $structure = array_merge($defaultsStructure, [
            'work_type_id' => (int) ($workTypeRules['work_type_id'] ?? 0),
            'institution_id' => (int) ($workTypeRules['institution_id'] ?? 0),
            'academic_level_id' => (int) ($academicLevelRules['id'] ?? 0),
            'level_multiplier' => (float) ($academicLevelRules['multiplier'] ?? 1),
            'level_slug' => (string) ($academicLevelRules['slug'] ?? ''),
            'custom_structure' => $workStructure,
        ], (array) ($metadata['structure_overrides'] ?? []));

        return new ResolvedRuleSetDTO($visual, $reference, $structure, [
            'resolved_at' => date('c'),
            'resolution_precedence' => ['metadata.json', 'institution_work_type_rules', 'institution_rules', 'system_defaults'],
            'notes' => is_array($metadata['notes'] ?? null) ? $metadata['notes'] : (is_string($metadata['notes'] ?? null) ? [$metadata['notes']] : []),
            'institution_norm' => [
                'slug' => $normDocumentContext['slug'] ?? null,
                'source' => $normDocumentContext['source'] ?? 'none',
                'has_txt' => !empty($normDocumentContext['txt_path']),
                'has_pdf' => !empty($normDocumentContext['pdf_path']),
                'metadata' => $metadata,
                'excerpt' => mb_substr(trim((string) ($normDocumentContext['content'] ?? '')), 0, 3000),
            ],
        ]);
    }

    private function decodeJson(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') return [];
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}

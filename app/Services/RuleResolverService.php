<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ResolvedRuleSetDTO;

final class RuleResolverService
{
    public function resolve(array $institutionRules, array $workTypeRules, array $academicLevelRules): ResolvedRuleSetDTO
    {
        $frontPage = json_decode((string) ($institutionRules['front_page_rules_json'] ?? '{}'), true);
        $workVisual = json_decode((string) ($workTypeRules['custom_visual_rules_json'] ?? '{}'), true);
        $workReferences = json_decode((string) ($workTypeRules['custom_reference_rules_json'] ?? '{}'), true);

        return new ResolvedRuleSetDTO(
            visualRules: array_filter([
                'font_family' => $institutionRules['font_family'] ?? 'Times New Roman',
                'font_size' => (float) ($institutionRules['font_size'] ?? 12),
                'heading_font_size' => (float) ($institutionRules['heading_font_size'] ?? 14),
                'line_spacing' => (float) ($institutionRules['line_spacing'] ?? 1.5),
                'margin_top' => (float) ($institutionRules['margin_top'] ?? 2.5),
                'margin_right' => (float) ($institutionRules['margin_right'] ?? 3),
                'margin_bottom' => (float) ($institutionRules['margin_bottom'] ?? 2.5),
                'margin_left' => (float) ($institutionRules['margin_left'] ?? 3),
                'front_page' => is_array($frontPage) ? $frontPage : [],
            ]) + (is_array($workVisual) ? $workVisual : []),
            referenceRules: [
                'style' => $institutionRules['references_style'] ?? 'APA',
                'citation_profile_id' => $institutionRules['citation_profile_id'] ?? null,
            ] + (is_array($workReferences) ? $workReferences : []),
            structureRules: [
                'work_type_id' => $workTypeRules['work_type_id'] ?? null,
                'institution_id' => $workTypeRules['institution_id'] ?? null,
                'level_multiplier' => $academicLevelRules['multiplier'] ?? 1,
                'level_slug' => $academicLevelRules['slug'] ?? null,
            ],
            meta: [
                'resolved_at' => date('c'),
                'institution_rule_id' => $institutionRules['id'] ?? null,
                'institution_work_type_rule_id' => $workTypeRules['id'] ?? null,
            ]
        );
    }
}

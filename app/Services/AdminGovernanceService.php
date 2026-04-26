<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstitutionRuleRepository;
use App\Repositories\InstitutionWorkTypeRuleRepository;

final class AdminGovernanceService
{
    public function saveInstitutionRule(array $input): int
    {
        $institutionId = (int) ($input['institution_id'] ?? 0);
        if ($institutionId <= 0) {
            return 0;
        }

        (new InstitutionRuleRepository())->upsertByInstitution($institutionId, [
            'references_style' => strtoupper(trim((string) ($input['references_style'] ?? 'APA'))),
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
            'front_page_rules_json' => json_encode([
                'front_page_overrides' => $this->splitMultiline($input['front_page_overrides'] ?? ''),
                'visual_overrides' => $this->splitMultiline($input['visual_overrides'] ?? ''),
                'structure_overrides' => $this->splitMultiline($input['structure_overrides'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $institutionId;
    }

    public function saveInstitutionWorkTypeRule(array $input): array
    {
        $institutionId = (int) ($input['institution_id'] ?? 0);
        $workTypeId = (int) ($input['work_type_id'] ?? 0);
        if ($institutionId <= 0 || $workTypeId <= 0) {
            return ['institution_id' => 0, 'work_type_id' => 0];
        }

        $customStructure = [
            'sections' => $this->splitMultiline($input['structure_sections'] ?? ''),
            'required_elements' => $this->splitMultiline($input['structure_required_elements'] ?? ''),
        ];
        $customVisual = [
            'font_family' => trim((string) ($input['visual_font_family'] ?? '')),
            'font_size' => trim((string) ($input['visual_font_size'] ?? '')),
            'line_spacing' => trim((string) ($input['visual_line_spacing'] ?? '')),
            'extra_rules' => $this->splitMultiline($input['visual_rules'] ?? ''),
        ];
        $customReference = [
            'style' => strtoupper(trim((string) ($input['reference_style'] ?? ''))),
            'sources_min' => trim((string) ($input['reference_sources_min'] ?? '')),
            'rules' => $this->splitMultiline($input['reference_rules'] ?? ''),
        ];

        (new InstitutionWorkTypeRuleRepository())->upsert($institutionId, $workTypeId, [
            'custom_structure_json' => $this->toJsonOrNull($customStructure),
            'custom_visual_rules_json' => $this->toJsonOrNull($customVisual),
            'custom_reference_rules_json' => $this->toJsonOrNull($customReference),
            'notes' => trim((string) ($input['notes'] ?? '')) ?: null,
        ]);

        return ['institution_id' => $institutionId, 'work_type_id' => $workTypeId];
    }

    private function splitMultiline(mixed $value): array
    {
        $parts = preg_split('/[\r\n]+/', (string) $value) ?: [];
        $normalized = [];
        foreach ($parts as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

    private function toJsonOrNull(array $payload): ?string
    {
        $filtered = array_filter($payload, static function (mixed $value): bool {
            if (is_array($value)) {
                return $value !== [];
            }

            return trim((string) $value) !== '';
        });

        return $filtered === [] ? null : json_encode($filtered, JSON_UNESCAPED_UNICODE);
    }
}

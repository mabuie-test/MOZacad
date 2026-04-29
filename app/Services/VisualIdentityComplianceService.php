<?php

declare(strict_types=1);

namespace App\Services;

final class VisualIdentityComplianceService
{
    public function validate(array $resolvedRules): array
    {
        $issues = [];
        $visual = is_array($resolvedRules['visualRules'] ?? null) ? $resolvedRules['visualRules'] : [];
        $frontPage = is_array($visual['front_page'] ?? null) ? $visual['front_page'] : [];

        $margins = is_array($visual['margins'] ?? null) ? $visual['margins'] : [];
        foreach (['top', 'bottom', 'left', 'right'] as $edge) {
            $val = isset($margins[$edge]) ? (float) $margins[$edge] : 0.0;
            if ($val < 2.0) {
                $issues[] = ['severity' => 'critical', 'rule' => 'visual_margin', 'message' => "Margem {$edge} inferior ao mínimo institucional (2.0cm)."];
            }
        }

        $fontFamily = trim((string) ($visual['font_family'] ?? ''));
        if ($fontFamily === '') {
            $issues[] = ['severity' => 'major', 'rule' => 'visual_font', 'message' => 'Fonte institucional não definida.'];
        }

        if (trim((string) ($frontPage['institution_name'] ?? '')) === '') {
            $issues[] = ['severity' => 'major', 'rule' => 'visual_cover', 'message' => 'Capa sem nome da instituição.'];
        }

        return $issues;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

final class VisualIdentityComplianceService
{
    public function validate(array $resolvedRules, array $templateResolution = []): array
    {
        $issues = [];
        $margins = (array) ($resolvedRules['visualRules']['margins'] ?? []);
        $mode = (string) ($templateResolution['mode'] ?? '');
        foreach (['top','bottom','left','right'] as $side) {
            if (!array_key_exists($side, $margins)) {
                $issues[] = ['severity' => 'warning', 'rule' => 'margin_not_verified', 'message' => 'Margem não verificada automaticamente.', 'target' => $side];
                continue;
            }
            $value = (float) $margins[$side];
            if ($mode === 'template_published_tracked' && $value <= 0) {
                continue;
            }
            if ($value > 0 && $value < 2.0) {
                $issues[] = ['severity' => 'critical', 'rule' => 'margin_minimum', 'message' => sprintf('Margem %s inferior a 2.0cm.', $side), 'target' => $side];
            }
        }

        return $issues;
    }
}

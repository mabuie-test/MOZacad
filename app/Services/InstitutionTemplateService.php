<?php

declare(strict_types=1);

namespace App\Services;

final class InstitutionTemplateService
{
    /**
     * @return array{mode:string,selected_template:?string,reason:string,candidate_path:?string}
     */
    public function resolve(array $institution, int $workTypeId): array
    {
        $slug = trim((string) ($institution['slug'] ?? ''));
        $templatesBase = (new StoragePathService())->templatesBase();
        $candidate = $slug !== ''
            ? $templatesBase . '/' . $slug . '/work-type-' . $workTypeId . '.docx'
            : null;

        if ($candidate !== null && is_file($candidate)) {
            return [
                'mode' => 'template_detected_read_only',
                'selected_template' => basename($candidate),
                'reason' => 'Template institucional válido detectado (estado operacional: leitura/diagnóstico).',
                'candidate_path' => $candidate,
            ];
        }

        return [
            'mode' => 'programmatic_fallback',
            'selected_template' => null,
            'reason' => 'Sem template institucional publicado para este tipo; fallback programático aplicado.',
            'candidate_path' => $candidate,
        ];
    }
}

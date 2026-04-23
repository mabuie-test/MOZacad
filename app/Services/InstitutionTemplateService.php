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
                'mode' => 'programmatic_assembly',
                'selected_template' => basename($candidate),
                'reason' => 'Template detectado, mas montagem oficial permanece programática nesta versão.',
                'candidate_path' => $candidate,
            ];
        }

        return [
            'mode' => 'programmatic_assembly',
            'selected_template' => null,
            'reason' => 'Sem template institucional activo; fallback programático explícito.',
            'candidate_path' => $candidate,
        ];
    }
}

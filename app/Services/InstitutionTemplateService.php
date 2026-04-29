<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TemplateArtifactRepository;

final class InstitutionTemplateService
{
    public function __construct(private readonly TemplateArtifactRepository $artifacts = new TemplateArtifactRepository()) {}

    /**
     * @return array{mode:string,selected_template:?string,reason:string,candidate_path:?string,traceability:array<string,mixed>}
     */
    public function resolve(array $institution, int $workTypeId): array
    {
        $slug = trim((string) ($institution['slug'] ?? ''));
        $templatesBase = (new StoragePathService())->templatesBase();
        $candidate = $slug !== ''
            ? $templatesBase . '/' . $slug . '/work-type-' . $workTypeId . '.docx'
            : null;

        $tracking = $this->artifacts->findActive((int) ($institution['id'] ?? 0), $workTypeId, 'work_type_template');
        $traceability = [
            'artifact_id' => isset($tracking['id']) ? (int) $tracking['id'] : null,
            'tracked_in_sql' => $tracking !== null,
            'tracked_path' => $tracking['file_path'] ?? null,
            'tracked_checksum' => $tracking['checksum_sha256'] ?? null,
            'published_by_user_id' => isset($tracking['published_by_user_id']) ? (int) $tracking['published_by_user_id'] : null,
            'published_at' => $tracking['created_at'] ?? null,
        ];

        if ($candidate !== null && is_file($candidate)) {
            if ($tracking === null) {
                return [
                    'mode' => 'drift_filesystem_only',
                    'selected_template' => basename($candidate),
                    'reason' => 'Template encontrado no storage sem registo SQL activo (drift detectado).',
                    'candidate_path' => $candidate,
                    'traceability' => $traceability,
                ];
            }

            if ((string) $tracking['file_path'] !== $candidate) {
                return [
                    'mode' => 'drift_path_mismatch',
                    'selected_template' => basename($candidate),
                    'reason' => 'Template em disco difere do caminho publicado no catálogo SQL.',
                    'candidate_path' => $candidate,
                    'traceability' => $traceability,
                ];
            }

            return [
                'mode' => 'template_published_tracked',
                'selected_template' => basename($candidate),
                'reason' => 'Template institucional válido detectado e rastreado em SQL.',
                'candidate_path' => $candidate,
                'traceability' => $traceability,
            ];
        }

        return [
            'mode' => $tracking !== null ? 'drift_sql_missing_file' : 'programmatic_fallback',
            'selected_template' => null,
            'reason' => $tracking !== null
                ? 'Catálogo SQL indica template activo, mas ficheiro está ausente no storage.'
                : 'Sem template institucional publicado para este tipo; fallback programático aplicado.',
            'candidate_path' => $candidate,
            'traceability' => $traceability,
        ];
    }
}

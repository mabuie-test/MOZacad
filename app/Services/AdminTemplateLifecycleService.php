<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\TemplateArtifactRepository;
use App\Repositories\TemplateRepository;
use RuntimeException;

final class AdminTemplateLifecycleService
{
    public function __construct(
        private readonly TemplateArtifactRepository $artifacts = new TemplateArtifactRepository(),
        private readonly TemplateRepository $templates = new TemplateRepository(),
    ) {}

    public function activateArtifactVersion(int $artifactId): array
    {
        $artifact = $this->artifacts->findById($artifactId);
        if ($artifact === null) {
            throw new RuntimeException('Artefacto não encontrado.');
        }


        $artifactType = (string) ($artifact['artifact_type'] ?? '');
        if (in_array($artifactType, ['norm_pdf', 'norm_txt', 'norm_metadata'], true)) {
            $institutionId = (int) ($artifact['institution_id'] ?? 0);
            if ($institutionId > 0) {
                $norm = (new InstitutionNormDocumentService())->resolveForInstitution(['id' => $institutionId]);
                if (($norm['source'] ?? '') === 'pdf_unparsed' || trim((string) ($norm['content'] ?? '')) === '') {
                    throw new RuntimeException('Não é possível activar a norma: parsing/OCR falhou. Forneça norma.txt, instale pdftotext/ocrmypdf no servidor ou configure NORM_OCR_PIPELINE_ENDPOINT para OCR remoto antes de produção.');
                }
            }
        }

        if (!$this->artifacts->activateArtifact($artifactId)) {
            throw new RuntimeException('Falha ao activar versão do artefacto.');
        }

        $artifactType = (string) ($artifact['artifact_type'] ?? '');
        if ($artifactType === 'work_type_template') {
            $institutionId = (int) ($artifact['institution_id'] ?? 0);
            $workTypeId = (int) ($artifact['work_type_id'] ?? 0);
            $filePath = (string) ($artifact['file_path'] ?? '');
            if ($institutionId > 0 && $workTypeId > 0 && $filePath !== '') {
                $this->templates->upsertPublishedTemplate($institutionId, $workTypeId, $filePath);
            }
        }

        return [
            'artifact_id' => $artifactId,
            'artifact_type' => $artifactType,
            'institution_id' => (int) ($artifact['institution_id'] ?? 0),
            'work_type_id' => $artifact['work_type_id'] !== null ? (int) $artifact['work_type_id'] : null,
        ];
    }
}

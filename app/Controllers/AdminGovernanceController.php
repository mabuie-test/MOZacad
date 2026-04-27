<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\InstitutionRepository;
use App\Services\AdminGovernanceService;
use App\Services\AdminTemplateLifecycleService;
use App\Services\AdminTemplateOperationService;
use RuntimeException;

final class AdminGovernanceController extends AdminActionController
{
    public function saveInstitutionRule(): void
    {
        if (!$this->guardAdminPost()) return;

        $institutionId = (new AdminGovernanceService())->saveInstitutionRule($_POST);
        if ($institutionId <= 0) {
            $this->adminError('institution_id obrigatório.', 422, '/admin/institution-rules');
            return;
        }

        $this->audit('admin.institution_rule.saved', 'institution', $institutionId);
        $this->adminSuccess('Regras institucionais guardadas.', '/admin/institution-rules');
    }

    public function saveInstitutionWorkTypeRule(): void
    {
        if (!$this->guardAdminPost()) return;

        $saved = (new AdminGovernanceService())->saveInstitutionWorkTypeRule($_POST);
        if (($saved['institution_id'] ?? 0) <= 0 || ($saved['work_type_id'] ?? 0) <= 0) {
            $this->adminError('institution_id e work_type_id são obrigatórios.', 422, '/admin/institution-rules');
            return;
        }

        $this->audit('admin.institution_work_type_rule.saved', 'work_type', (int) $saved['work_type_id'], ['institution_id' => (int) $saved['institution_id']]);
        $this->adminSuccess('Regra por tipo de trabalho guardada.', '/admin/institution-rules');
    }

    public function publishNorm(): void
    {
        if (!$this->guardAdminPost()) return;

        $institutionId = (int) ($_POST['institution_id'] ?? 0);
        if ($institutionId <= 0) {
            $this->adminError('institution_id obrigatório para publicar normas.', 422, '/admin/templates');
            return;
        }

        try {
            $result = (new AdminTemplateOperationService())->publishNormArtifacts($institutionId, $_FILES, (int) ($_SESSION['auth_user_id'] ?? 0));
            $this->audit('admin.norms.published', 'institution', $institutionId, $result);
            $this->adminSuccess('Normas institucionais publicadas com sucesso.', '/admin/templates');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/templates');
        }
    }

    public function publishWorkTypeTemplate(): void
    {
        if (!$this->guardAdminPost()) return;

        $institutionId = (int) ($_POST['institution_id'] ?? 0);
        $workTypeId = (int) ($_POST['work_type_id'] ?? 0);
        if ($institutionId <= 0 || $workTypeId <= 0) {
            $this->adminError('institution_id e work_type_id são obrigatórios para template.', 422, '/admin/templates');
            return;
        }

        try {
            $result = (new AdminTemplateOperationService())->publishWorkTypeTemplate($institutionId, $workTypeId, $_FILES['template_docx'] ?? null, (int) ($_SESSION['auth_user_id'] ?? 0));
            $this->audit('admin.template.published', 'work_type', $workTypeId, $result + ['institution_id' => $institutionId]);
            $institution = (new InstitutionRepository())->findById($institutionId);
            $redirect = '/admin/templates';
            if ($institution !== null) {
                $redirect .= '?institution_id=' . $institutionId;
            }
            $this->adminSuccess('Template publicado com sucesso.', $redirect);
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/templates');
        }
    }

    public function activateTemplateArtifact(int $artifactId): void
    {
        if (!$this->guardAdminPost()) return;

        try {
            $result = (new AdminTemplateLifecycleService())->activateArtifactVersion($artifactId);
            $this->audit('admin.template_artifact.activated', 'template_artifact', $artifactId, $result);
            $this->adminSuccess('Versão do artefacto activada com sucesso.', '/admin/templates', $result);
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/templates');
        }
    }
}

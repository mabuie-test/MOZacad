<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminAcademicCatalogService;

final class AdminCatalogController extends BaseController
{
    public function createInstitution(): void { $this->upsertInstitution(0); }
    public function updateInstitution(int $id): void { $this->upsertInstitution($id); }
    public function createCourse(): void { $this->upsertCourse(0); }
    public function updateCourse(int $id): void { $this->upsertCourse($id); }
    public function createDiscipline(): void { $this->upsertDiscipline(0); }
    public function updateDiscipline(int $id): void { $this->upsertDiscipline($id); }
    public function createWorkType(): void { $this->upsertWorkType(0); }
    public function updateWorkType(int $id): void { $this->upsertWorkType($id); }

    private function upsertInstitution(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $savedId = (new AdminAcademicCatalogService())->upsertInstitution($id, $_POST);
        if ($savedId === null) {
            $this->adminError('Nome obrigatório.', 422, '/admin/institutions');
            return;
        }

        $this->audit($id > 0 ? 'admin.institution.updated' : 'admin.institution.created', 'institution', $savedId);
        $this->adminSuccess($id > 0 ? 'Instituição atualizada.' : 'Instituição criada.', '/admin/institutions', ['institution_id' => $savedId]);
    }

    private function upsertCourse(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $savedId = (new AdminAcademicCatalogService())->upsertCourse($id, $_POST);
        if ($savedId === null) {
            $this->adminError('Instituição e nome são obrigatórios.', 422, '/admin/courses');
            return;
        }

        $this->audit($id > 0 ? 'admin.course.updated' : 'admin.course.created', 'course', $savedId);
        $this->adminSuccess($id > 0 ? 'Curso atualizado.' : 'Curso criado.', '/admin/courses', ['course_id' => $savedId]);
    }

    private function upsertDiscipline(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $savedId = (new AdminAcademicCatalogService())->upsertDiscipline($id, $_POST);
        if ($savedId === null) {
            $this->adminError('Nome obrigatório.', 422, '/admin/disciplines');
            return;
        }

        $this->audit($id > 0 ? 'admin.discipline.updated' : 'admin.discipline.created', 'discipline', $savedId);
        $this->adminSuccess($id > 0 ? 'Disciplina atualizada.' : 'Disciplina criada.', '/admin/disciplines', ['discipline_id' => $savedId]);
    }

    private function upsertWorkType(int $id): void
    {
        if (!$this->guardAdminPost()) return;

        $savedId = (new AdminAcademicCatalogService())->upsertWorkType($id, $_POST);
        if ($savedId === null) {
            $this->adminError('Nome e slug são obrigatórios.', 422, '/admin/work-types');
            return;
        }

        $this->audit($id > 0 ? 'admin.work_type.updated' : 'admin.work_type.created', 'work_type', $savedId);
        $this->adminSuccess($id > 0 ? 'Tipo de trabalho atualizado.' : 'Tipo de trabalho criado.', '/admin/work-types', ['work_type_id' => $savedId]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\CouponRepository;
use App\Services\AdminAcademicCatalogService;
use App\Services\AdminCommercialService;
use App\Services\AdminGovernanceService;
use App\Services\AdminHumanReviewService;
use App\Services\AdminOverviewService;
use App\Services\AdminPricingService;
use RuntimeException;

final class AdminController extends BaseController
{
    public function index(): void { $this->renderSection('overview'); }
    public function users(): void { $this->renderSection('users'); }
    public function orders(): void { $this->renderSection('orders'); }
    public function payments(): void { $this->renderSection('payments'); }
    public function humanReviewQueue(): void { $this->renderSection('human-review'); }
    public function institutions(): void { $this->renderSection('institutions'); }
    public function courses(): void { $this->renderSection('courses'); }
    public function disciplines(): void { $this->renderSection('disciplines'); }
    public function workTypes(): void { $this->renderSection('work-types'); }
    public function pricing(): void { $this->renderSection('pricing'); }
    public function discounts(): void { $this->renderSection('discounts'); }
    public function institutionRules(): void { $this->renderSection('institution-rules'); }
    public function templates(): void { $this->renderSection('templates'); }
    public function coupons(): void { $this->renderSection('coupons'); }

    public function createInstitution(): void { $this->upsertInstitution(0); }
    public function updateInstitution(int $id): void { $this->upsertInstitution($id); }
    public function createCourse(): void { $this->upsertCourse(0); }
    public function updateCourse(int $id): void { $this->upsertCourse($id); }
    public function createDiscipline(): void { $this->upsertDiscipline(0); }
    public function updateDiscipline(int $id): void { $this->upsertDiscipline($id); }
    public function createWorkType(): void { $this->upsertWorkType(0); }
    public function updateWorkType(int $id): void { $this->upsertWorkType($id); }

    public function saveInstitutionRule(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

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
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $saved = (new AdminGovernanceService())->saveInstitutionWorkTypeRule($_POST);
        if (($saved['institution_id'] ?? 0) <= 0 || ($saved['work_type_id'] ?? 0) <= 0) {
            $this->adminError('institution_id e work_type_id são obrigatórios.', 422, '/admin/institution-rules');
            return;
        }

        $this->audit('admin.institution_work_type_rule.saved', 'work_type', (int) $saved['work_type_id'], ['institution_id' => (int) $saved['institution_id']]);
        $this->adminSuccess('Regra por tipo de trabalho guardada.', '/admin/institution-rules');
    }

    public function createCoupon(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $service = new AdminCommercialService();
        $payload = $service->couponPayloadFromRequest($_POST);
        if ($payload === null) {
            $this->adminError('Dados inválidos para criar cupão.', 422, '/admin/coupons');
            return;
        }

        $id = $service->createCoupon($payload);
        if ($id === null) {
            $this->adminError('Já existe um cupão activo com esse código.', 409, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.created', 'coupon', $id, ['code' => $payload['code']]);
        $this->adminSuccess('Cupão criado com sucesso.', '/admin/coupons', ['coupon_id' => $id]);
    }

    public function updateCoupon(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $service = new AdminCommercialService();
        $payload = $service->couponPayloadFromRequest($_POST);
        if ($payload === null) {
            $this->adminError('Dados inválidos para actualizar cupão.', 422, '/admin/coupons');
            return;
        }

        if (!$service->updateCoupon($id, $payload)) {
            $this->adminError('Cupão não encontrado.', 404, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.updated', 'coupon', $id, ['code' => $payload['code']]);
        $this->adminSuccess('Cupão actualizado.', '/admin/coupons', ['coupon_id' => $id]);
    }

    public function toggleCoupon(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $active = !empty($_POST['is_active']);
        $updated = (new CouponRepository())->setActive($id, $active);
        if (!$updated) {
            $this->adminError('Cupão não encontrado para alteração de estado.', 404, '/admin/coupons');
            return;
        }

        $this->audit('admin.coupon.toggled', 'coupon', $id, ['is_active' => $active]);
        $this->adminSuccess($active ? 'Cupão activado.' : 'Cupão inactivado.', '/admin/coupons', ['coupon_id' => $id, 'is_active' => $active]);
    }

    public function assignHumanReview(int $queueId): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $reviewerId = (int) ($_POST['reviewer_id'] ?? 0);
        if ($reviewerId <= 0) {
            $this->adminError('reviewer_id é obrigatório.', 422, '/admin/human-review');
            return;
        }

        try {
            (new AdminHumanReviewService())->assign((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $reviewerId);
            $this->audit('admin.human_review.assigned', 'human_review_queue', $queueId, ['reviewer_id' => $reviewerId]);
            $this->adminSuccess('Revisor atribuído com sucesso.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        }
    }

    public function decideHumanReview(int $queueId): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        try {
            $decision = trim((string) ($_POST['decision'] ?? ''));
            (new AdminHumanReviewService())->decide((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $decision, trim((string) ($_POST['notes'] ?? '')) ?: null);
            $this->audit('admin.human_review.decided', 'human_review_queue', $queueId, ['decision' => $decision]);
            $this->adminSuccess('Decisão de revisão humana guardada.', '/admin/human-review');
        } catch (\Throwable $e) {
            $this->adminError('Falha ao processar decisão da revisão humana.', 500, '/admin/human-review', ['error' => $e->getMessage()]);
        }
    }

    public function createDiscount(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $service = new AdminCommercialService();
        $id = $service->createDiscount($_POST, (int) ($_SESSION['auth_user_id'] ?? 1));
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($id === null) {
            $this->adminError('Dados inválidos para criar desconto.', 422, '/admin/discounts');
            return;
        }

        $this->audit('admin.discount.created', 'user_discount', $id, ['user_id' => $userId]);
        $this->adminSuccess('Desconto criado.', '/admin/discounts', ['discount_id' => $id]);
    }

    public function updateDiscount(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        (new AdminCommercialService())->updateDiscount($id, $_POST);
        $this->audit('admin.discount.updated', 'user_discount', $id);
        $this->adminSuccess('Desconto atualizado.', '/admin/discounts', ['discount_id' => $id]);
    }

    public function upsertPricingRule(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $ruleCode = trim((string) ($_POST['rule_code'] ?? ''));
        $ruleValue = trim((string) ($_POST['rule_value'] ?? ''));
        if ($ruleCode === '' || $ruleValue === '') {
            $this->adminError('rule_code e rule_value são obrigatórios.', 422, '/admin/pricing');
            return;
        }
        (new AdminPricingService())->upsertRule((int) ($_SESSION['auth_user_id'] ?? 0), $ruleCode, $ruleValue, trim((string) ($_POST['description'] ?? '')) ?: null, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');
        $this->audit('admin.pricing_rule.saved', 'pricing_rule', null, ['rule_code' => $ruleCode]);
        $this->adminSuccess('Regra de pricing guardada.', '/admin/pricing', ['rule_code' => $ruleCode]);
    }

    public function upsertPricingExtra(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $extraCode = trim((string) ($_POST['extra_code'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? -1);
        if ($extraCode === '' || $name === '' || $amount < 0) {
            $this->adminError('extra_code, name e amount válido são obrigatórios.', 422, '/admin/pricing');
            return;
        }
        (new AdminPricingService())->upsertExtra((int) ($_SESSION['auth_user_id'] ?? 0), $extraCode, $name, $amount, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');
        $this->audit('admin.pricing_extra.saved', 'pricing_extra', null, ['extra_code' => $extraCode]);
        $this->adminSuccess('Extra de pricing guardado.', '/admin/pricing', ['extra_code' => $extraCode]);
    }

    private function upsertInstitution(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

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
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

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
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

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
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $savedId = (new AdminAcademicCatalogService())->upsertWorkType($id, $_POST);
        if ($savedId === null) {
            $this->adminError('Nome e slug são obrigatórios.', 422, '/admin/work-types');
            return;
        }

        $this->audit($id > 0 ? 'admin.work_type.updated' : 'admin.work_type.created', 'work_type', $savedId);
        $this->adminSuccess($id > 0 ? 'Tipo de trabalho atualizado.' : 'Tipo de trabalho criado.', '/admin/work-types', ['work_type_id' => $savedId]);
    }

    private function renderSection(string $section): void
    {
        if (!$this->requireAdminAccess()) return;

        $payload = (new AdminOverviewService())->payload($section, $_GET);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }

        $this->view('admin/index', $payload);
    }

    private function adminSuccess(string $message, string $redirectPath, array $payload = []): void
    {
        $this->successResponse($message, $redirectPath, $payload);
    }

    private function adminError(string $message, int $status, string $redirectPath, array $payload = []): void
    {
        $this->errorResponse($message, $status, $redirectPath, $payload);
    }

    private function audit(string $action, string $subjectType, ?int $subjectId = null, array $payload = []): void
    {
        (new AuditLogRepository())->log((int) ($_SESSION['auth_user_id'] ?? 0), $action, $subjectType, $subjectId, $payload);
    }
}

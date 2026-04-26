<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Repositories\CouponRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\InstitutionRuleRepository;
use App\Repositories\InstitutionWorkTypeRuleRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PricingExtraRepository;
use App\Repositories\PricingRuleRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserDiscountRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\AdminHumanReviewService;
use App\Services\AdminPricingService;
use App\Services\InstitutionNormDocumentService;
use App\Services\InstitutionTemplateService;
use App\Services\PricingConfig;
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
        $institutionId = (int) ($_POST['institution_id'] ?? 0);
        if ($institutionId <= 0) {
            $this->adminError('institution_id obrigatório.', 422, '/admin/institution-rules');
            return;
        }

        (new InstitutionRuleRepository())->upsertByInstitution($institutionId, [
            'references_style' => strtoupper(trim((string) ($_POST['references_style'] ?? 'APA'))),
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'front_page_rules_json' => json_encode([
                'front_page_overrides' => $this->splitMultiline($_POST['front_page_overrides'] ?? ''),
                'visual_overrides' => $this->splitMultiline($_POST['visual_overrides'] ?? ''),
                'structure_overrides' => $this->splitMultiline($_POST['structure_overrides'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
        ]);
        $this->audit('admin.institution_rule.saved', 'institution', $institutionId);

        $this->adminSuccess('Regras institucionais guardadas.', '/admin/institution-rules');
    }

    public function saveInstitutionWorkTypeRule(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $institutionId = (int) ($_POST['institution_id'] ?? 0);
        $workTypeId = (int) ($_POST['work_type_id'] ?? 0);
        if ($institutionId <= 0 || $workTypeId <= 0) {
            $this->adminError('institution_id e work_type_id são obrigatórios.', 422, '/admin/institution-rules');
            return;
        }

        $customStructure = [
            'sections' => $this->splitMultiline($_POST['structure_sections'] ?? ''),
            'required_elements' => $this->splitMultiline($_POST['structure_required_elements'] ?? ''),
        ];
        $customVisual = [
            'font_family' => trim((string) ($_POST['visual_font_family'] ?? '')),
            'font_size' => trim((string) ($_POST['visual_font_size'] ?? '')),
            'line_spacing' => trim((string) ($_POST['visual_line_spacing'] ?? '')),
            'extra_rules' => $this->splitMultiline($_POST['visual_rules'] ?? ''),
        ];
        $customReference = [
            'style' => strtoupper(trim((string) ($_POST['reference_style'] ?? ''))),
            'sources_min' => trim((string) ($_POST['reference_sources_min'] ?? '')),
            'rules' => $this->splitMultiline($_POST['reference_rules'] ?? ''),
        ];

        (new InstitutionWorkTypeRuleRepository())->upsert($institutionId, $workTypeId, [
            'custom_structure_json' => $this->toJsonOrNull($customStructure),
            'custom_visual_rules_json' => $this->toJsonOrNull($customVisual),
            'custom_reference_rules_json' => $this->toJsonOrNull($customReference),
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
        ]);
        $this->audit('admin.institution_work_type_rule.saved', 'work_type', $workTypeId, ['institution_id' => $institutionId]);
        $this->adminSuccess('Regra por tipo de trabalho guardada.', '/admin/institution-rules');
    }

    public function createCoupon(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $payload = $this->couponPayloadFromRequest();
        if ($payload === null) {
            $this->adminError('Dados inválidos para criar cupão.', 422, '/admin/coupons');
            return;
        }

        $repo = new CouponRepository();
        if ($repo->findActiveByCode($payload['code']) !== null) {
            $this->adminError('Já existe um cupão activo com esse código.', 409, '/admin/coupons');
            return;
        }

        $id = $repo->create($payload);
        $this->audit('admin.coupon.created', 'coupon', $id, ['code' => $payload['code']]);
        $this->adminSuccess('Cupão criado com sucesso.', '/admin/coupons', ['coupon_id' => $id]);
    }

    public function updateCoupon(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $repo = new CouponRepository();
        if ($repo->findById($id) === null) {
            $this->adminError('Cupão não encontrado.', 404, '/admin/coupons');
            return;
        }

        $payload = $this->couponPayloadFromRequest();
        if ($payload === null) {
            $this->adminError('Dados inválidos para actualizar cupão.', 422, '/admin/coupons');
            return;
        }

        $repo->update($id, $payload);
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
        $userId = (int) ($_POST['user_id'] ?? 0);
        $type = (string) ($_POST['discount_type'] ?? '');
        $value = (float) ($_POST['discount_value'] ?? 0);
        if ($userId <= 0 || !in_array($type, ['percent', 'fixed', 'extra_waiver'], true) || $value < 0) {
            $this->adminError('Dados inválidos para criar desconto.', 422, '/admin/discounts');
            return;
        }

        $id = (new UserDiscountRepository())->create([
            'user_id' => $userId,
            'name' => trim((string) ($_POST['name'] ?? 'Desconto personalizado')),
            'discount_type' => $type,
            'discount_value' => $value,
            'work_type_id' => !empty($_POST['work_type_id']) ? (int) $_POST['work_type_id'] : null,
            'extra_code' => $_POST['extra_code'] ?? null,
            'usage_limit' => !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null,
            'starts_at' => $_POST['starts_at'] ?? null,
            'ends_at' => $_POST['ends_at'] ?? null,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'created_by_admin_id' => (int) ($_SESSION['auth_user_id'] ?? 1),
            'notes' => $_POST['notes'] ?? null,
        ]);

        $this->audit('admin.discount.created', 'user_discount', $id, ['user_id' => $userId]);
        $this->adminSuccess('Desconto criado.', '/admin/discounts', ['discount_id' => $id]);
    }

    public function updateDiscount(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        (new UserDiscountRepository())->update($id, [
            'name' => trim((string) ($_POST['name'] ?? 'Desconto personalizado')),
            'discount_type' => (string) ($_POST['discount_type'] ?? 'fixed'),
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'work_type_id' => !empty($_POST['work_type_id']) ? (int) $_POST['work_type_id'] : null,
            'extra_code' => $_POST['extra_code'] ?? null,
            'usage_limit' => !empty($_POST['usage_limit']) ? (int) $_POST['usage_limit'] : null,
            'starts_at' => $_POST['starts_at'] ?? null,
            'ends_at' => $_POST['ends_at'] ?? null,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'notes' => $_POST['notes'] ?? null,
        ]);
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
        $payload = ['name' => trim((string) ($_POST['name'] ?? '')), 'short_name' => trim((string) ($_POST['short_name'] ?? '')) ?: null, 'slug' => trim((string) ($_POST['slug'] ?? '')) ?: null, 'is_active' => !empty($_POST['is_active'])];
        if ($payload['name'] === '') { $this->adminError('Nome obrigatório.', 422, '/admin/institutions'); return; }

        $repo = new InstitutionRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) $repo->update($id, $payload);

        $this->audit($id > 0 ? 'admin.institution.updated' : 'admin.institution.created', 'institution', $savedId);
        $this->adminSuccess($id > 0 ? 'Instituição atualizada.' : 'Instituição criada.', '/admin/institutions', ['institution_id' => $savedId]);
    }

    private function upsertCourse(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $payload = ['institution_id' => (int) ($_POST['institution_id'] ?? 0), 'name' => trim((string) ($_POST['name'] ?? '')), 'code' => trim((string) ($_POST['code'] ?? '')) ?: null, 'is_active' => !empty($_POST['is_active'])];
        if ($payload['institution_id'] <= 0 || $payload['name'] === '') { $this->adminError('Instituição e nome são obrigatórios.', 422, '/admin/courses'); return; }

        $repo = new CourseRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) $repo->update($id, $payload);

        $this->audit($id > 0 ? 'admin.course.updated' : 'admin.course.created', 'course', $savedId);
        $this->adminSuccess($id > 0 ? 'Curso atualizado.' : 'Curso criado.', '/admin/courses', ['course_id' => $savedId]);
    }

    private function upsertDiscipline(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $payload = ['institution_id' => (int) ($_POST['institution_id'] ?? 0), 'course_id' => (int) ($_POST['course_id'] ?? 0), 'name' => trim((string) ($_POST['name'] ?? '')), 'code' => trim((string) ($_POST['code'] ?? '')) ?: null, 'is_active' => !empty($_POST['is_active'])];
        if ($payload['name'] === '') { $this->adminError('Nome obrigatório.', 422, '/admin/disciplines'); return; }

        $repo = new DisciplineRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) $repo->update($id, $payload);

        $this->audit($id > 0 ? 'admin.discipline.updated' : 'admin.discipline.created', 'discipline', $savedId);
        $this->adminSuccess($id > 0 ? 'Disciplina atualizada.' : 'Disciplina criada.', '/admin/disciplines', ['discipline_id' => $savedId]);
    }

    private function upsertWorkType(int $id): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'slug' => trim((string) ($_POST['slug'] ?? '')),
            'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
            'is_active' => !empty($_POST['is_active']),
            'base_price' => (float) ($_POST['base_price'] ?? 0),
            'default_complexity' => (string) ($_POST['default_complexity'] ?? 'medium'),
            'requires_human_review' => !empty($_POST['requires_human_review']),
            'is_premium_type' => !empty($_POST['is_premium_type']),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
        ];
        if ($payload['name'] === '' || $payload['slug'] === '') { $this->adminError('Nome e slug são obrigatórios.', 422, '/admin/work-types'); return; }

        $repo = new WorkTypeRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) $repo->update($id, $payload);

        $this->audit($id > 0 ? 'admin.work_type.updated' : 'admin.work_type.created', 'work_type', $savedId);
        $this->adminSuccess($id > 0 ? 'Tipo de trabalho atualizado.' : 'Tipo de trabalho criado.', '/admin/work-types', ['work_type_id' => $savedId]);
    }

    private function renderSection(string $section): void
    {
        if (!$this->requireAdminAccess()) return;

        $payload = $this->adminPayload($section);
        if ($this->wantsJson()) {
            $this->json($payload);
            return;
        }

        $this->view('admin/index', $payload);
    }

    private function adminPayload(string $section): array
    {
        $institutions = (new InstitutionRepository())->allForAdmin();
        $workTypes = (new WorkTypeRepository())->all(200);
        $orders = (new OrderRepository())->listAll(300);
        $payments = (new PaymentRepository())->listAll(300);
        $queueRows = (new HumanReviewQueueRepository())->listQueue(300);
        $discounts = (new UserDiscountRepository())->listAll(300);

        $orderStatusFilter = trim((string) ($_GET['order_status'] ?? ''));
        $paymentStatusFilter = trim((string) ($_GET['payment_status'] ?? ''));
        $reviewStatusFilter = trim((string) ($_GET['review_status'] ?? ''));

        if ($orderStatusFilter !== '') {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => (string) ($row['status'] ?? '') === $orderStatusFilter));
        }
        if ($paymentStatusFilter !== '') {
            $payments = array_values(array_filter($payments, static fn (array $row): bool => (string) ($row['status'] ?? '') === $paymentStatusFilter));
        }
        if ($reviewStatusFilter !== '') {
            $queueRows = array_values(array_filter($queueRows, static fn (array $row): bool => (string) ($row['status'] ?? '') === $reviewStatusFilter));
        }

        $overview = [
            'orders_pending_payment' => count(array_filter($orders, static fn (array $o): bool => (string) ($o['status'] ?? '') === 'pending_payment')),
            'orders_under_review' => count(array_filter($orders, static fn (array $o): bool => in_array((string) ($o['status'] ?? ''), ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
            'payments_failed' => count(array_filter($payments, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['failed', 'cancelled', 'expired'], true))),
            'queue_unassigned' => count(array_filter($queueRows, static fn (array $q): bool => empty($q['reviewer_id']) && in_array((string) ($q['status'] ?? ''), ['pending', 'assigned'], true))),
        ];

        $normService = new InstitutionNormDocumentService();
        $templateService = new InstitutionTemplateService();
        $normMatrix = [];
        foreach ($institutions as $institution) {
            $norm = $normService->resolveForInstitution($institution);
            $templateRows = [];
            foreach ($workTypes as $wt) {
                $templateRows[] = ['work_type' => $wt, 'state' => $templateService->resolve($institution, (int) $wt['id'])];
            }
            $normMatrix[] = ['institution' => $institution, 'norm' => $norm, 'templates' => $templateRows];
        }

        return [
            'activeSection' => $section,
            'overview' => $overview,
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'reviewStatusFilter' => $reviewStatusFilter,
            'users' => (new UserRepository())->all(300),
            'orders' => $orders,
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'reviewers' => (new UserRepository())->listByRole('human_reviewer', 80),
            'discounts' => $discounts,
            'institutions' => $institutions,
            'courses' => (new CourseRepository())->all(300),
            'disciplines' => (new DisciplineRepository())->all(300),
            'workTypes' => $workTypes,
            'pricingRules' => (new PricingRuleRepository())->all(300),
            'pricingExtras' => (new PricingExtraRepository())->all(300),
            'coupons' => (new CouponRepository())->allWithUsage(200),
            'institutionRules' => (new InstitutionRuleRepository())->all(300),
            'institutionWorkTypeRules' => (new InstitutionWorkTypeRuleRepository())->all(300),
            'templates' => (new TemplateRepository())->all(300),
            'normMatrix' => $normMatrix,
            'pricingConfig' => [
                'currency' => (new PricingConfig())->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => (new PricingConfig())->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => (new PricingConfig())->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => (new PricingConfig())->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
        ];
    }

    private function couponPayloadFromRequest(): ?array
    {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $discountType = trim((string) ($_POST['discount_type'] ?? ''));
        $discountValue = (float) ($_POST['discount_value'] ?? -1);
        $usageLimitRaw = trim((string) ($_POST['usage_limit'] ?? ''));
        $startsAt = $this->normalizeDateTime($_POST['starts_at'] ?? null);
        $endsAt = $this->normalizeDateTime($_POST['ends_at'] ?? null);

        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            return null;
        }
        if (!in_array($discountType, ['percent', 'fixed'], true) || $discountValue < 0) {
            return null;
        }
        if ($discountType === 'percent' && $discountValue > 100) {
            return null;
        }

        $usageLimit = $usageLimitRaw === '' ? null : (int) $usageLimitRaw;
        if ($usageLimit !== null && $usageLimit <= 0) {
            return null;
        }
        if ($startsAt !== null && $endsAt !== null && strtotime($startsAt) > strtotime($endsAt)) {
            return null;
        }

        return [
            'code' => $code,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'usage_limit' => $usageLimit,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
        ];
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
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

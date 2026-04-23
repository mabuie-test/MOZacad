<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PricingExtraRepository;
use App\Repositories\PricingRuleRepository;
use App\Repositories\UserDiscountRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\AdminHumanReviewService;
use App\Services\AdminPricingService;
use App\Services\PricingConfig;
use RuntimeException;

final class AdminController extends BaseController
{
    public function index(): void
    {
        if (!$this->requireAdminAccess()) {
            return;
        }

        $statusFilter = trim((string) ($_GET['review_status'] ?? ''));
        $orderStatusFilter = trim((string) ($_GET['order_status'] ?? ''));
        $paymentStatusFilter = trim((string) ($_GET['payment_status'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = max(10, min(100, (int) ($_GET['per_page'] ?? 20)));
        $offset = ($page - 1) * $perPage;

        $queueRows = (new HumanReviewQueueRepository())->listQueue(500);
        if ($statusFilter !== '') {
            $queueRows = array_values(array_filter($queueRows, static fn (array $row): bool => (string) ($row['status'] ?? '') === $statusFilter));
        }

        $orders = (new OrderRepository())->listAll(500);
        if ($orderStatusFilter !== '') {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => (string) ($row['status'] ?? '') === $orderStatusFilter));
        }
        $payments = (new PaymentRepository())->listAll(500);
        if ($paymentStatusFilter !== '') {
            $payments = array_values(array_filter($payments, static fn (array $row): bool => (string) ($row['status'] ?? '') === $paymentStatusFilter));
        }

        $this->view('admin/index', [
            'flashMessage' => isset($_GET['message']) ? (string) $_GET['message'] : null,
            'users' => (new UserRepository())->all(20),
            'orders' => array_slice($orders, $offset, $perPage),
            'payments' => array_slice($payments, $offset, $perPage),
            'humanReviewQueue' => array_slice($queueRows, $offset, $perPage),
            'reviewers' => (new UserRepository())->listByRole('human_reviewer', 50),
            'discounts' => (new UserDiscountRepository())->listAll(20),
            'institutions' => (new InstitutionRepository())->all(),
            'courses' => (new CourseRepository())->all(20),
            'disciplines' => (new DisciplineRepository())->all(20),
            'workTypes' => (new WorkTypeRepository())->all(20),
            'pricingRules' => (new PricingRuleRepository())->all(100),
            'pricingExtras' => (new PricingExtraRepository())->all(100),
            'reviewStatusFilter' => $statusFilter,
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function users(): void
    {
        if (!$this->requireAdminAccess()) return;
        $users = (new UserRepository())->all(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'users' => array_slice($users, 0, 20),
                'activeSection' => 'users',
            ]));
            return;
        }
        $this->json(['users' => $users]);
    }
    public function orders(): void
    {
        if (!$this->requireAdminAccess()) return;
        $userId = (int) ($_GET['user_id'] ?? 0);
        $orders = $userId > 0 ? (new OrderRepository())->listByUser($userId) : (new OrderRepository())->listAll(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'orders' => array_slice($orders, 0, 30),
                'activeSection' => 'orders',
            ]));
            return;
        }
        $this->json(['orders' => $orders]);
    }
    public function payments(): void
    {
        if (!$this->requireAdminAccess()) return;
        $payments = (new PaymentRepository())->listAll(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'payments' => array_slice($payments, 0, 30),
                'activeSection' => 'payments',
            ]));
            return;
        }
        $this->json(['payments' => $payments]);
    }

    public function humanReviewQueue(): void
    {
        if (!$this->requireAdminAccess()) return;
        $queue = (new HumanReviewQueueRepository())->listQueue(200);
        $reviewers = (new UserRepository())->listByRole('human_reviewer', 200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'humanReviewQueue' => array_slice($queue, 0, 30),
                'reviewers' => $reviewers,
                'activeSection' => 'human-review',
            ]));
            return;
        }
        $this->json([
            'human_review_queue' => $queue,
            'reviewers' => $reviewers,
        ]);
    }

    public function assignHumanReview(int $queueId): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $reviewerId = (int) ($_POST['reviewer_id'] ?? 0);
        if ($reviewerId <= 0) {
            $this->json(['message' => 'reviewer_id é obrigatório.'], 422);
            return;
        }

        try {
            (new AdminHumanReviewService())->assign((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $reviewerId);
        } catch (RuntimeException $e) {
            $this->json(['message' => $e->getMessage()], 422);
            return;
        }

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Revisor atribuído com sucesso.');
            return;
        }
        $this->json(['message' => 'Revisor atribuído com sucesso.', 'queue_id' => $queueId, 'reviewer_id' => $reviewerId]);
    }

    public function decideHumanReview(int $queueId): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $decision = trim((string) ($_POST['decision'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));

        try {
            (new AdminHumanReviewService())->decide((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $decision, $notes !== '' ? $notes : null);
        } catch (\Throwable $e) {
            $this->json(['message' => 'Falha ao processar decisão da revisão humana.', 'error' => $e->getMessage()], 500);
            return;
        }

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Decisão de revisão humana guardada com sucesso.');
            return;
        }
        $this->json(['message' => 'Decisão guardada com sucesso.', 'queue_id' => $queueId, 'decision' => $decision]);
    }

    public function discounts(): void
    {
        if (!$this->requireAdminAccess()) return;

        $userId = (int) ($_GET['user_id'] ?? 0);
        $repo = new UserDiscountRepository();
        $discounts = $userId > 0 ? $repo->findEligible($userId, !empty($_GET['work_type_id']) ? (int) $_GET['work_type_id'] : null) : $repo->listAll(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'discounts' => array_slice($discounts, 0, 50),
                'activeSection' => 'discounts',
            ]));
            return;
        }
        $this->json(['discounts' => $discounts]);
    }

    public function createDiscount(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $userId = (int) ($_POST['user_id'] ?? 0);
        $type = (string) ($_POST['discount_type'] ?? '');
        $value = (float) ($_POST['discount_value'] ?? 0);
        if ($userId <= 0 || !in_array($type, ['percent', 'fixed', 'extra_waiver'], true) || $value < 0) {
            $this->json(['message' => 'Dados inválidos para criar desconto.'], 422);
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

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Desconto criado com sucesso.');
            return;
        }
        $this->json(['message' => 'Desconto criado.', 'discount_id' => $id], 201);
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

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Desconto atualizado com sucesso.');
            return;
        }
        $this->json(['message' => 'Desconto atualizado.', 'discount_id' => $id]);
    }

    public function institutions(): void {
        if (!$this->requireAdminAccess()) return;
        $institutions = (new InstitutionRepository())->all();
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData(['institutions' => $institutions, 'activeSection' => 'institutions']));
            return;
        }
        $this->json(['institutions' => $institutions]);
    }
    public function courses(): void {
        if (!$this->requireAdminAccess()) return;
        $courses = (new CourseRepository())->all(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData(['courses' => $courses, 'activeSection' => 'courses']));
            return;
        }
        $this->json(['courses' => $courses]);
    }
    public function disciplines(): void {
        if (!$this->requireAdminAccess()) return;
        $disciplines = (new DisciplineRepository())->all(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData(['disciplines' => $disciplines, 'activeSection' => 'disciplines']));
            return;
        }
        $this->json(['disciplines' => $disciplines]);
    }
    public function workTypes(): void {
        if (!$this->requireAdminAccess()) return;
        $workTypes = (new WorkTypeRepository())->all(200);
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData(['workTypes' => $workTypes, 'activeSection' => 'work-types']));
            return;
        }
        $this->json(['work_types' => $workTypes]);
    }

    public function pricing(): void
    {
        if (!$this->requireAdminAccess()) return;

        $config = new PricingConfig();
        $payload = [
            'pricing' => [
                'currency' => $config->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => $config->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => $config->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => $config->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
            'rules' => (new PricingRuleRepository())->all(300),
            'extras' => (new PricingExtraRepository())->all(300),
        ];
        if ($this->isHtmlRequest()) {
            $this->view('admin/index', $this->buildAdminViewData([
                'pricingConfig' => $payload['pricing'],
                'pricingRules' => $payload['rules'],
                'pricingExtras' => $payload['extras'],
                'activeSection' => 'pricing',
            ]));
            return;
        }
        $this->json($payload);
    }

    public function upsertPricingRule(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $ruleCode = trim((string) ($_POST['rule_code'] ?? ''));
        $ruleValue = trim((string) ($_POST['rule_value'] ?? ''));
        if ($ruleCode === '' || $ruleValue === '') {
            $this->json(['message' => 'rule_code e rule_value são obrigatórios.'], 422);
            return;
        }

        (new AdminPricingService())->upsertRule((int) ($_SESSION['auth_user_id'] ?? 0), $ruleCode, $ruleValue, !empty($_POST['description']) ? (string) $_POST['description'] : null, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Regra de pricing guardada com sucesso.');
            return;
        }
        $this->json(['message' => 'Regra de pricing guardada com sucesso.', 'rule_code' => $ruleCode]);
    }

    public function upsertPricingExtra(): void
    {
        if (!$this->requireAdminAccess() || !$this->requireCsrfToken()) return;

        $extraCode = trim((string) ($_POST['extra_code'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $amount = (float) ($_POST['amount'] ?? -1);
        if ($extraCode === '' || $name === '' || $amount < 0) {
            $this->json(['message' => 'extra_code, name e amount válido são obrigatórios.'], 422);
            return;
        }

        (new AdminPricingService())->upsertExtra((int) ($_SESSION['auth_user_id'] ?? 0), $extraCode, $name, $amount, !isset($_POST['is_active']) || (string) $_POST['is_active'] !== '0');

        if ($this->isHtmlRequest()) {
            $this->redirectToAdminWithMessage('Extra de pricing guardado com sucesso.');
            return;
        }
        $this->json(['message' => 'Extra de pricing guardado com sucesso.', 'extra_code' => $extraCode]);
    }

    private function redirectToAdminWithMessage(string $message): void
    {
        header('Location: /admin?message=' . rawurlencode($message));
    }

    private function buildAdminViewData(array $overrides = []): array
    {
        return array_merge([
            'flashMessage' => isset($_GET['message']) ? (string) $_GET['message'] : null,
            'users' => (new UserRepository())->all(20),
            'orders' => (new OrderRepository())->listAll(20),
            'payments' => (new PaymentRepository())->listAll(20),
            'humanReviewQueue' => (new HumanReviewQueueRepository())->listQueue(20),
            'reviewers' => (new UserRepository())->listByRole('human_reviewer', 50),
            'discounts' => (new UserDiscountRepository())->listAll(20),
            'institutions' => (new InstitutionRepository())->all(),
            'courses' => (new CourseRepository())->all(20),
            'disciplines' => (new DisciplineRepository())->all(20),
            'workTypes' => (new WorkTypeRepository())->all(20),
            'pricingRules' => (new PricingRuleRepository())->all(100),
            'pricingExtras' => (new PricingExtraRepository())->all(100),
            'pricingConfig' => [
                'currency' => (new PricingConfig())->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => (new PricingConfig())->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => (new PricingConfig())->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => (new PricingConfig())->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
            'reviewStatusFilter' => '',
            'orderStatusFilter' => '',
            'paymentStatusFilter' => '',
            'page' => 1,
            'perPage' => 20,
            'activeSection' => 'overview',
        ], $overrides);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserDiscountRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\PricingConfig;

final class AdminController extends BaseController
{
    public function index(): void
    {
        $this->view('admin/index');
    }

    public function users(): void
    {
        $this->json(['users' => (new UserRepository())->all(200)]);
    }

    public function orders(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $orders = $userId > 0 ? (new OrderRepository())->listByUser($userId) : (new OrderRepository())->listAll(200);
        $this->json(['orders' => $orders]);
    }

    public function payments(): void
    {
        $payments = (new PaymentRepository())->listAll(200);
        $this->json(['payments' => $payments]);
    }

    public function humanReviewQueue(): void
    {
        $queue = (new HumanReviewQueueRepository())->listQueue(200);
        $this->json(['human_review_queue' => $queue]);
    }

    public function discounts(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        $repo = new UserDiscountRepository();
        $discounts = $userId > 0
            ? $repo->findEligible($userId, !empty($_GET['work_type_id']) ? (int) $_GET['work_type_id'] : null)
            : $repo->listAll(200);

        $this->json(['discounts' => $discounts]);
    }

    public function createDiscount(): void
    {
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

        $this->json(['message' => 'Desconto criado.', 'discount_id' => $id], 201);
    }

    public function updateDiscount(int $id): void
    {
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
        $this->json(['message' => 'Desconto atualizado.', 'discount_id' => $id]);
    }

    public function institutions(): void
    {
        $this->json(['institutions' => (new InstitutionRepository())->all()]);
    }

    public function courses(): void
    {
        $this->json(['courses' => (new CourseRepository())->all(200)]);
    }

    public function disciplines(): void
    {
        $this->json(['disciplines' => (new DisciplineRepository())->all(200)]);
    }

    public function workTypes(): void
    {
        $this->json(['work_types' => (new WorkTypeRepository())->all(200)]);
    }

    public function pricing(): void
    {
        $config = new PricingConfig();
        $this->json([
            'pricing' => [
                'currency' => $config->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => $config->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => $config->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => $config->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AcademicLevelRepository;
use App\Repositories\OrderRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\PricingService;
use App\Services\RevisionService;

final class OrderController extends BaseController
{
    public function index(): void
    {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            $this->json(['message' => 'Informe user_id para listar pedidos.'], 422);
            return;
        }

        $orders = (new OrderRepository())->listByUser($userId);
        $this->json(['orders' => $orders]);
    }

    public function create(): void { $this->view('orders/create'); }

    public function store(): void
    {
        $data = [
            'user_id' => (int) ($_POST['user_id'] ?? 0),
            'institution_id' => (int) ($_POST['institution_id'] ?? 0),
            'course_id' => (int) ($_POST['course_id'] ?? 0),
            'discipline_id' => (int) ($_POST['discipline_id'] ?? 0),
            'academic_level_id' => (int) ($_POST['academic_level_id'] ?? 0),
            'work_type_id' => (int) ($_POST['work_type_id'] ?? 0),
            'title_or_theme' => trim((string) ($_POST['title_or_theme'] ?? '')),
            'subtitle' => $_POST['subtitle'] ?? null,
            'problem_statement' => $_POST['problem_statement'] ?? null,
            'general_objective' => $_POST['general_objective'] ?? null,
            'specific_objectives_json' => json_encode($_POST['specific_objectives'] ?? [], JSON_UNESCAPED_UNICODE),
            'hypothesis' => $_POST['hypothesis'] ?? null,
            'keywords_json' => json_encode($_POST['keywords'] ?? [], JSON_UNESCAPED_UNICODE),
            'target_pages' => (int) ($_POST['target_pages'] ?? 0),
            'complexity_level' => (string) ($_POST['complexity_level'] ?? 'medium'),
            'deadline_date' => (string) ($_POST['deadline_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'))),
            'notes' => $_POST['notes'] ?? null,
            'status' => 'pending_payment',
            'final_price' => 0.0,
        ];

        if ($data['user_id'] <= 0 || $data['work_type_id'] <= 0 || $data['target_pages'] <= 0 || $data['title_or_theme'] === '') {
            $this->json(['message' => 'Campos obrigatórios inválidos.'], 422);
            return;
        }

        $workType = (new WorkTypeRepository())->findById($data['work_type_id']);
        $academicLevel = (new AcademicLevelRepository())->findById($data['academic_level_id']);
        if ($workType === null || $academicLevel === null) {
            $this->json(['message' => 'Tipo de trabalho ou nível académico inválido.'], 422);
            return;
        }

        $orderRepo = new OrderRepository();
        $orderId = $orderRepo->create($data);
        $orderRepo->createRequirement([
            'order_id' => $orderId,
            'needs_institution_cover' => !empty($_POST['needs_institution_cover']) ? 1 : 0,
            'needs_abstract' => isset($_POST['needs_abstract']) ? (int) !!$_POST['needs_abstract'] : 1,
            'needs_bilingual_abstract' => !empty($_POST['needs_bilingual_abstract']) ? 1 : 0,
            'needs_methodology_review' => !empty($_POST['needs_methodology_review']) ? 1 : 0,
            'needs_humanized_revision' => !empty($_POST['needs_humanized_revision']) ? 1 : 0,
            'needs_slides' => !empty($_POST['needs_slides']) ? 1 : 0,
            'needs_defense_summary' => !empty($_POST['needs_defense_summary']) ? 1 : 0,
            'notes' => $_POST['requirement_notes'] ?? null,
        ]);

        $extras = [
            'needs_institution_cover' => !empty($_POST['needs_institution_cover']),
            'needs_bilingual_abstract' => !empty($_POST['needs_bilingual_abstract']),
            'needs_methodology_review' => !empty($_POST['needs_methodology_review']),
            'needs_humanized_revision' => !empty($_POST['needs_humanized_revision']),
            'needs_slides' => !empty($_POST['needs_slides']),
            'needs_defense_summary' => !empty($_POST['needs_defense_summary']),
        ];

        $pricing = (new PricingService())->calculate([
            'order_id' => $orderId,
            'user_id' => $data['user_id'],
            'work_type_id' => $data['work_type_id'],
            'work_type_slug' => (string) $workType['slug'],
            'target_pages' => $data['target_pages'],
            'academic_level_multiplier' => (float) ($academicLevel['multiplier'] ?? 1),
            'complexity_multiplier' => $this->complexityMultiplier($data['complexity_level']),
            'urgency_multiplier' => $this->urgencyMultiplier($data['deadline_date']),
            'extras' => $extras,
            'requires_human_review' => (bool) ($workType['requires_human_review'] ?? false),
        ]);

        $orderRepo->updateFinalPrice($orderId, $pricing->finalTotal);

        $this->json(['order_id' => $orderId, 'pricing' => $pricing->toArray()], 201);
    }

    public function show(int $id): void
    {
        $order = (new OrderRepository())->findDetailedById($id);
        if ($order === null) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        $this->json(['order' => $order]);
    }

    public function pay(int $id): void
    {
        $order = (new OrderRepository())->findById($id);
        if ($order === null) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        $this->json([
            'order_id' => $id,
            'status' => $order['status'],
            'amount' => $order['final_price'],
            'hint' => 'Inicie com POST /payments/mpesa/initiate',
        ]);
    }

    public function requestRevision(int $id): void
    {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($userId <= 0 || $reason === '') {
            $this->json(['message' => 'user_id e reason são obrigatórios.'], 422);
            return;
        }

        (new RevisionService())->request($id, $userId, $reason);
        $this->json(['message' => 'Pedido de revisão registado']);
    }

    private function complexityMultiplier(string $complexity): float
    {
        return match ($complexity) {
            'low' => 1.0,
            'high' => 1.35,
            'very_high' => 1.6,
            default => 1.15,
        };
    }

    private function urgencyMultiplier(string $deadline): float
    {
        $hours = max(1, (strtotime($deadline) - time()) / 3600);
        return match (true) {
            $hours <= 24 => 2.0,
            $hours <= 72 => 1.5,
            $hours <= 120 => 1.2,
            default => 1.0,
        };
    }
}

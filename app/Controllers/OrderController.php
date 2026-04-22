<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AcademicLevelRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderAttachmentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\OrderApplicationService;
use App\Services\PaymentApplicationService;
use App\Services\RevisionService;
use Throwable;

final class OrderController extends BaseController
{
    public function index(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $this->json(['orders' => (new OrderRepository())->listByUser($userId)]);
    }

    public function create(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $this->json([
            'institutions' => (new InstitutionRepository())->all(),
            'courses' => (new CourseRepository())->all(),
            'disciplines' => (new DisciplineRepository())->all(),
            'academic_levels' => (new AcademicLevelRepository())->all(),
            'work_types' => (new WorkTypeRepository())->all(),
        ]);
    }

    public function store(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0 || !$this->requireCsrfToken()) {
            return;
        }

        $data = [
            'user_id' => $userId,
            'institution_id' => (int) ($_POST['institution_id'] ?? 0),
            'course_id' => (int) ($_POST['course_id'] ?? 0),
            'discipline_id' => (int) ($_POST['discipline_id'] ?? 0),
            'academic_level_id' => (int) ($_POST['academic_level_id'] ?? 0),
            'work_type_id' => (int) ($_POST['work_type_id'] ?? 0),
            'title_or_theme' => trim((string) ($_POST['title_or_theme'] ?? '')),
            'subtitle' => trim((string) ($_POST['subtitle'] ?? '')) ?: null,
            'problem_statement' => trim((string) ($_POST['problem_statement'] ?? '')) ?: null,
            'general_objective' => trim((string) ($_POST['general_objective'] ?? '')) ?: null,
            'specific_objectives_json' => json_encode($this->normalizeListInput($_POST['specific_objectives'] ?? []), JSON_UNESCAPED_UNICODE),
            'hypothesis' => trim((string) ($_POST['hypothesis'] ?? '')) ?: null,
            'keywords_json' => json_encode($this->normalizeListInput($_POST['keywords'] ?? []), JSON_UNESCAPED_UNICODE),
            'target_pages' => (int) ($_POST['target_pages'] ?? 0),
            'complexity_level' => (string) ($_POST['complexity_level'] ?? 'medium'),
            'deadline_date' => (string) ($_POST['deadline_date'] ?? date('Y-m-d H:i:s', strtotime('+7 days'))),
            'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
            'status' => 'pending_payment',
            'final_price' => 0.0,
        ];

        if ($data['institution_id'] <= 0 || $data['course_id'] <= 0 || $data['discipline_id'] <= 0 || $data['academic_level_id'] <= 0 || $data['work_type_id'] <= 0 || $data['target_pages'] <= 0 || $data['title_or_theme'] === '') {
            $this->json(['message' => 'Campos obrigatórios inválidos para criação do pedido.'], 422);
            return;
        }

        if (strtotime($data['deadline_date']) === false) {
            $this->json(['message' => 'deadline_date inválida.'], 422);
            return;
        }

        $extras = [
            'needs_institution_cover' => !empty($_POST['needs_institution_cover']),
            'needs_bilingual_abstract' => !empty($_POST['needs_bilingual_abstract']),
            'needs_methodology_review' => !empty($_POST['needs_methodology_review']),
            'needs_humanized_revision' => !empty($_POST['needs_humanized_revision']),
            'needs_slides' => !empty($_POST['needs_slides']),
            'needs_defense_summary' => !empty($_POST['needs_defense_summary']),
        ];

        try {
            $result = (new OrderApplicationService())->createOrder($userId, $data, $extras, $_FILES['attachments'] ?? [], $_POST);
            $this->json($result, 201);
        } catch (Throwable $e) {
            $this->json(['message' => 'Falha ao criar pedido.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $order = (new OrderRepository())->findDetailedById($id);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        $this->json(['order' => $order, 'attachments' => (new OrderAttachmentRepository())->listByOrderId($id)]);
    }

    public function pay(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $payments = new PaymentApplicationService();
        $order = $payments->userOrderById($id, $userId);
        if ($order === null) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        if ((float) $order['final_price'] <= 0) {
            $this->json(['message' => 'Pedido sem valor final.'], 422);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->json(['order_id' => $id, 'status' => $order['status'], 'amount' => (float) $order['final_price']]);
            return;
        }

        if (!$this->requireCsrfToken()) {
            return;
        }

        $msisdn = trim((string) ($_POST['msisdn'] ?? ''));
        if ($msisdn === '') {
            $this->json(['message' => 'msisdn é obrigatório para iniciar o pagamento.'], 422);
            return;
        }

        try {
            $flow = $payments->initiateOrderMpesa($id, $userId, $msisdn, !empty($_POST['callback_url']) ? (string) $_POST['callback_url'] : null, !empty($_POST['internal_notes']) ? (string) $_POST['internal_notes'] : null);
            $this->json(['message' => 'Pagamento iniciado com sucesso.', 'order_id' => $id, 'invoice_id' => (int) $flow['invoice_id'], 'payment' => $flow['payment']], 201);
        } catch (Throwable $e) {
            $this->json(['message' => 'Falha ao iniciar pagamento.', 'error' => $e->getMessage()], 502);
        }
    }

    public function requestRevision(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0 || !$this->requireCsrfToken()) {
            return;
        }

        $order = (new OrderRepository())->findById($id);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            $this->json(['message' => 'reason é obrigatório.'], 422);
            return;
        }

        $revisionId = (new RevisionService())->request($id, $userId, $reason);
        $this->json(['message' => 'Pedido de revisão registado', 'revision_id' => $revisionId]);
    }

    private function normalizeListInput(mixed $input): array
    {
        $parts = is_string($input) ? (preg_split('/[\r\n,;]+/', $input) ?: []) : (is_array($input) ? $input : []);

        $normalized = [];
        foreach ($parts as $value) {
            $item = trim((string) $value);
            if ($item !== '') {
                $normalized[] = $item;
            }
        }

        return array_values(array_unique($normalized));
    }
}

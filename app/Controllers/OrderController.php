<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Helpers\Database;
use App\Repositories\AcademicLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\OrderAttachmentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\OrderPaymentFlowService;
use App\Services\PricingService;
use App\Services\RevisionService;
use App\Services\SecureUploadService;
use Throwable;

final class OrderController extends BaseController
{
    public function index(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $orders = (new OrderRepository())->listByUser($userId);
        $this->json(['orders' => $orders]);
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
        if ($userId <= 0) {
            return;
        }
        if (!$this->requireCsrfToken()) {
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

        if (
            $data['institution_id'] <= 0
            || $data['course_id'] <= 0
            || $data['discipline_id'] <= 0
            || $data['academic_level_id'] <= 0
            || $data['work_type_id'] <= 0
            || $data['target_pages'] <= 0
            || $data['title_or_theme'] === ''
        ) {
            $this->json(['message' => 'Campos obrigatórios inválidos para criação do pedido.'], 422);
            return;
        }

        if (strtotime($data['deadline_date']) === false) {
            $this->json(['message' => 'deadline_date inválida. Use formato de data válido.'], 422);
            return;
        }

        $workType = (new WorkTypeRepository())->findById($data['work_type_id']);
        $academicLevel = (new AcademicLevelRepository())->findById($data['academic_level_id']);
        if ($workType === null || $academicLevel === null) {
            $this->json(['message' => 'Tipo de trabalho ou nível académico inválido.'], 422);
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

        $db = Database::connect();
        $orderRepo = new OrderRepository();

        try {
            $db->beginTransaction();

            $orderId = $orderRepo->create($data);
            $orderRepo->createRequirement([
                'order_id' => $orderId,
                'needs_institution_cover' => $extras['needs_institution_cover'] ? 1 : 0,
                'needs_abstract' => isset($_POST['needs_abstract']) ? (int) !!$_POST['needs_abstract'] : 1,
                'needs_bilingual_abstract' => $extras['needs_bilingual_abstract'] ? 1 : 0,
                'needs_methodology_review' => $extras['needs_methodology_review'] ? 1 : 0,
                'needs_humanized_revision' => $extras['needs_humanized_revision'] ? 1 : 0,
                'needs_slides' => $extras['needs_slides'] ? 1 : 0,
                'needs_defense_summary' => $extras['needs_defense_summary'] ? 1 : 0,
                'notes' => trim((string) ($_POST['requirement_notes'] ?? '')) ?: null,
            ]);

            $pricing = (new PricingService())->calculate([
                'order_id' => $orderId,
                'user_id' => $userId,
                'work_type_id' => $data['work_type_id'],
                'work_type_slug' => (string) $workType['slug'],
                'target_pages' => $data['target_pages'],
                'academic_level_multiplier' => (float) ($academicLevel['multiplier'] ?? 1),
                'complexity_multiplier' => $this->complexityMultiplier($data['complexity_level']),
                'urgency_multiplier' => $this->urgencyMultiplier($data['deadline_date']),
                'extras' => $extras,
                'requires_human_review' => (bool) ($workType['requires_human_review'] ?? false),
                'coupon_code' => trim((string) ($_POST['coupon_code'] ?? '')),
            ]);

            $orderRepo->updateFinalPrice($orderId, $pricing->finalTotal);

            $uploaded = (new SecureUploadService())->storeMany($_FILES['attachments'] ?? [], 'orders/' . $orderId);
            $attachmentRepo = new OrderAttachmentRepository();
            foreach ($uploaded as $file) {
                $attachmentRepo->create([
                    'order_id' => $orderId,
                    'attachment_type' => 'supporting_document',
                    'file_name' => $file['original_name'],
                    'file_path' => $file['path'],
                    'mime_type' => $file['mime'],
                ]);
            }

            (new AuditLogRepository())->log($userId, 'order.create', 'order', $orderId, [
                'work_type_id' => $data['work_type_id'],
                'target_pages' => $data['target_pages'],
                'attachments_count' => count($uploaded),
            ]);

            $db->commit();

            $this->json(['order_id' => $orderId, 'pricing' => $pricing->toArray(), 'attachments_count' => count($uploaded)], 201);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
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

        $attachments = (new OrderAttachmentRepository())->listByOrderId($id);
        $this->json(['order' => $order, 'attachments' => $attachments]);
    }

    public function pay(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }

        $order = (new OrderRepository())->findById($id);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->json(['message' => 'Pedido não encontrado.'], 404);
            return;
        }

        if ((float) $order['final_price'] <= 0) {
            $this->json(['message' => 'Pedido sem valor final. Recalcule o pricing antes do pagamento.'], 422);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->json([
                'order_id' => $id,
                'status' => $order['status'],
                'amount' => (float) $order['final_price'],
                'instructions' => 'Submeta POST para este endpoint com msisdn para iniciar M-Pesa C2B.',
            ]);
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
            $flow = (new OrderPaymentFlowService())->initiateOrderPayment(
                $id,
                (int) $order['user_id'],
                $msisdn,
                !empty($_POST['callback_url']) ? (string) $_POST['callback_url'] : null,
                !empty($_POST['internal_notes']) ? (string) $_POST['internal_notes'] : null
            );
            $invoiceId = (int) $flow['invoice_id'];
            $payment = $flow['payment'];
        } catch (Throwable $e) {
            $this->json(['message' => 'Falha ao iniciar pagamento.', 'error' => $e->getMessage()], 502);
            return;
        }

        $this->json([
            'message' => 'Pagamento iniciado com sucesso.',
            'order_id' => $id,
            'invoice_id' => $invoiceId,
            'payment' => $payment,
        ], 201);
    }

    public function requestRevision(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) {
            return;
        }
        if (!$this->requireCsrfToken()) {
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

    /**
     * @return array<int,string>
     */
    private function normalizeListInput(mixed $input): array
    {
        if (is_string($input)) {
            $parts = preg_split('/[\r\n,;]+/', $input) ?: [];
        } elseif (is_array($input)) {
            $parts = $input;
        } else {
            return [];
        }

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

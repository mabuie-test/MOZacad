<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AcademicLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrderAttachmentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RevisionRepository;
use App\Repositories\WorkTypeRepository;
use App\Services\OrderApplicationService;
use App\Services\PaymentApplicationService;
use App\Services\RevisionService;
use RuntimeException;
use Throwable;

final class OrderController extends BaseController
{
    public function index(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $orders = (new OrderRepository())->listByUser($userId);
        if ($this->isHtmlRequest()) {
            $this->view('orders/index', ['orders' => $orders]);
            return;
        }

        $this->json(['orders' => $orders]);
    }

    public function create(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $payload = [
            'institutions' => (new InstitutionRepository())->all(),
            'courses' => (new CourseRepository())->all(),
            'disciplines' => (new DisciplineRepository())->all(),
            'academic_levels' => (new AcademicLevelRepository())->all(),
            'work_types' => (new WorkTypeRepository())->all(),
        ];

        if ($this->isHtmlRequest()) {
            $this->view('orders/create', $payload);
            return;
        }

        $this->json($payload);
    }

    public function metaCourses(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $institutionId = (int) ($_GET['institution_id'] ?? 0);
        if ($institutionId <= 0) {
            $this->json(['courses' => []]);
            return;
        }

        $this->json(['courses' => (new CourseRepository())->byInstitution($institutionId)]);
    }

    public function metaDisciplines(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $courseId = (int) ($_GET['course_id'] ?? 0);
        if ($courseId <= 0) {
            $this->json(['disciplines' => []]);
            return;
        }

        $this->json(['disciplines' => (new DisciplineRepository())->byCourse($courseId)]);
    }

    public function store(): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0 || !$this->requireCsrfToken()) return;

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
            $this->errorResponse('Preencha instituição, curso, disciplina, nível, tipo, páginas e tema.', 422, '/orders/create');
            return;
        }
        if (strtotime($data['deadline_date']) === false) {
            $this->errorResponse('Prazo inválido.', 422, '/orders/create');
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
            (new AuditLogRepository())->log($userId, 'order.created', 'order', (int) ($result['order_id'] ?? 0), ['extras' => $extras]);
            $this->successResponse('Pedido criado com sucesso.', '/orders/' . (int) ($result['order_id'] ?? 0), $result, 201);
        } catch (Throwable $e) {
            $this->errorResponse('Falha ao criar pedido.', 500, '/orders/create', ['error' => $e->getMessage()]);
        }
    }

    public function show(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $order = (new OrderRepository())->findDetailedById($id);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->errorResponse('Pedido não encontrado.', 404, '/orders');
            return;
        }

        $attachments = (new OrderAttachmentRepository())->listByOrderId($id);
        $invoiceRepo = new InvoiceRepository();
        $paymentRepo = new PaymentRepository();
        $invoice = $invoiceRepo->findOpenByOrderId($id) ?? $invoiceRepo->findLatestByOrderId($id);
        $payment = $paymentRepo->findOpenByOrderId($id) ?? $paymentRepo->findLatestByOrderId($id);
        $paymentHistory = array_values(array_filter($paymentRepo->listRecentByUser($userId, 50), static fn (array $row): bool => (int) ($row['order_id'] ?? 0) === $id));
        $documents = array_values(array_filter((new GeneratedDocumentRepository())->listByUser($userId, 50), static fn(array $doc): bool => (int) $doc['order_id'] === $id));
        $revision = (new RevisionRepository())->findLatestByOrderId($id);

        if ($this->isHtmlRequest()) {
            $this->view('orders/show', compact('order', 'attachments', 'invoice', 'payment', 'paymentHistory', 'documents', 'revision'));
            return;
        }

        $this->json(['order' => $order, 'attachments' => $attachments, 'invoice' => $invoice, 'payment' => $payment, 'payment_history' => $paymentHistory, 'documents' => $documents, 'revision' => $revision]);
    }

    public function pay(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0) return;

        $payments = new PaymentApplicationService();
        $order = $payments->userOrderById($id, $userId);
        if ($order === null) {
            $this->errorResponse('Pedido não encontrado.', 404, '/orders');
            return;
        }
        if ((float) $order['final_price'] <= 0) {
            $this->errorResponse('Pedido sem valor final. Aguarde cotação.', 422, '/orders/' . $id);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $openPayment = (new PaymentRepository())->findOpenByOrderId($id);
            $invoice = (new InvoiceRepository())->findOpenByOrderId($id);

            if ($this->isHtmlRequest()) {
                $this->view('orders/pay', ['order' => $order, 'openPayment' => $openPayment, 'invoice' => $invoice]);
                return;
            }

            $this->json(['order_id' => $id, 'status' => $order['status'], 'amount' => (float) $order['final_price'], 'payment' => $openPayment]);
            return;
        }

        if (!$this->requireCsrfToken()) return;

        $msisdn = trim((string) ($_POST['msisdn'] ?? ''));
        if ($msisdn === '') {
            $this->errorResponse('Número M-Pesa é obrigatório para iniciar pagamento.', 422, '/orders/' . $id . '/pay');
            return;
        }

        try {
            $flow = $payments->initiateOrderMpesa($id, $userId, $msisdn, !empty($_POST['callback_url']) ? (string) $_POST['callback_url'] : null, !empty($_POST['internal_notes']) ? (string) $_POST['internal_notes'] : null);
            (new AuditLogRepository())->log($userId, 'order.payment_initiated', 'order', $id, ['invoice_id' => (int) $flow['invoice_id']]);
            $this->successResponse('Pagamento iniciado com sucesso.', '/orders/' . $id, ['order_id' => $id, 'invoice_id' => (int) $flow['invoice_id'], 'payment' => $flow['payment']], 201);
        } catch (RuntimeException $e) {
            $this->errorResponse($e->getMessage(), 422, '/orders/' . $id . '/pay');
        } catch (Throwable $e) {
            $this->errorResponse('Falha ao iniciar pagamento.', 502, '/orders/' . $id . '/pay', ['error' => $e->getMessage()]);
        }
    }

    public function requestRevision(int $id): void
    {
        $userId = $this->requireAuthUserId();
        if ($userId <= 0 || !$this->requireCsrfToken()) return;

        $order = (new OrderRepository())->findById($id);
        if ($order === null || (int) $order['user_id'] !== $userId) {
            $this->errorResponse('Pedido não encontrado.', 404, '/orders');
            return;
        }

        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($reason === '') {
            $this->errorResponse('Descreva o motivo da revisão.', 422, '/orders/' . $id);
            return;
        }

        try {
            $revisionId = (new RevisionService())->request($id, $userId, $reason);
            (new AuditLogRepository())->log($userId, 'order.revision_requested', 'order', $id, ['revision_id' => $revisionId]);
            $this->successResponse('Pedido de revisão registado.', '/orders/' . $id, ['revision_id' => $revisionId]);
        } catch (RuntimeException $e) {
            $this->errorResponse($e->getMessage(), 422, '/orders/' . $id);
        }
    }

    private function normalizeListInput(mixed $input): array
    {
        $parts = is_string($input) ? (preg_split('/[\r\n,;]+/', $input) ?: []) : (is_array($input) ? $input : []);

        $normalized = [];
        foreach ($parts as $value) {
            $item = trim((string) $value);
            if ($item !== '') $normalized[] = $item;
        }

        return array_values(array_unique($normalized));
    }
}

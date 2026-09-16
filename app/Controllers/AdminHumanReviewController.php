<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Helpers\Database;
use App\Services\StoragePathService;
use App\Services\AdminHumanReviewService;
use App\Services\HumanReviewQueueService;
use RuntimeException;

final class AdminHumanReviewController extends BaseController
{
    public function uploadOrderDocument(int $id): void
    {
        $permission = 'human_review.approve';
        if (!$this->guardAdminPermissionPost($permission, '/admin/orders')) return;

        $relativePath = null;
        $db = Database::connect();
        try {
            $db->beginTransaction();
            $order = (new OrderRepository())->lockByIdForUpdate($id);
            $payment = (new PaymentRepository())->findLatestByOrderId($id);
            if (!is_array($order) || !is_array($payment) || (string) ($payment['status'] ?? '') !== 'paid') {
                throw new RuntimeException('Só é possível carregar um documento para um pedido com pagamento confirmado.');
            }
            if (!in_array((string) ($order['status'] ?? ''), ['awaiting_manual_upload', 'returned_for_revision', 'revision_requested'], true)) {
                throw new RuntimeException('Este pedido não está disponível para upload manual.');
            }

            if ((new HumanReviewQueueRepository())->findOpenByOrderIdForUpdate($id) !== null) {
                throw new RuntimeException('Este pedido já possui um documento em revisão. Conclua ou devolva a revisão antes de enviar outra versão.');
            }

            $relativePath = $this->storeManualUpload($id, $_FILES['document'] ?? null, 'manual-delivery');
            $documents = new GeneratedDocumentRepository();
            $latest = $documents->findLatestByOrderId($id);
            $version = ((int) ($latest['version'] ?? 0)) + 1;
            $documentId = $documents->create($id, $relativePath, 'pending_human_review', $version, ['mode' => 'manual_upload']);
            (new OrderRepository())->updateStatus($id, 'under_human_review');
            $queueId = (new HumanReviewQueueService())->enqueue($id, $documentId, $version, null, (int) ($_SESSION['auth_user_id'] ?? 0));
            $db->commit();
            $this->audit('admin.order.manual_document_uploaded', 'order', $id, ['document_id' => $documentId, 'queue_id' => $queueId, 'file_path' => $relativePath], $permission);
            $this->adminSuccess('Documento carregado e encaminhado para revisão humana.', '/admin/orders');
        } catch (RuntimeException $e) {
            $this->rollbackAndRemoveUpload($db, $relativePath);
            $this->adminError($e->getMessage(), 422, '/admin/orders');
        } catch (\Throwable) {
            $this->rollbackAndRemoveUpload($db, $relativePath);
            $this->adminError('Falha ao carregar documento manual.', 500, '/admin/orders');
        }
    }

    public function assignHumanReview(int $queueId): void
    {
        $permission = 'human_review.assign';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        $reviewerId = (int) ($_POST['reviewer_id'] ?? 0);
        if ($reviewerId <= 0) {
            $this->adminError('reviewer_id é obrigatório.', 422, '/admin/human-review');
            return;
        }

        try {
            (new AdminHumanReviewService())->assign((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $reviewerId);
            $this->audit('admin.human_review.assigned', 'human_review_queue', $queueId, ['reviewer_id' => $reviewerId], $permission);
            $this->adminSuccess('Revisor atribuído com sucesso.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        }
    }

    public function decideHumanReview(int $queueId): void
    {
        $permission = 'human_review.approve';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        $decision = trim((string) ($_POST['decision'] ?? ''));
        try {
            $enforceAssignedReviewer = !isset($_POST['enforce_assigned_reviewer']) || (string) $_POST['enforce_assigned_reviewer'] !== '0';
            (new AdminHumanReviewService())->decide((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $decision, trim((string) ($_POST['notes'] ?? '')) ?: null, $enforceAssignedReviewer);
            $this->audit('admin.human_review.decided', 'human_review_queue', $queueId, ['decision' => $decision], $permission);
            $this->adminSuccess('Decisão de revisão humana guardada.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        } catch (\Throwable) {
            $this->adminError('Falha ao processar decisão da revisão humana.', 500, '/admin/human-review');
        }
    }

    public function uploadManualDocument(int $queueId): void
    {
        $permission = 'human_review.approve';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        try {
            $queue = (new HumanReviewQueueRepository())->findById($queueId);
            if (!is_array($queue)) {
                throw new RuntimeException('Item de revisão humana não encontrado.');
            }

            $relativePath = $this->storeManualUpload((int) ($queue['order_id'] ?? 0), $_FILES['corrected_document'] ?? null, 'human-review-corrections');
            (new GeneratedDocumentRepository())->updateFilePathAndStatus((int) ($queue['generated_document_id'] ?? 0), $relativePath, 'pending_human_review');
            $this->audit('admin.human_review.manual_document_uploaded', 'generated_document', (int) ($queue['generated_document_id'] ?? 0), ['queue_id' => $queueId, 'file_path' => $relativePath], $permission);
            $this->adminSuccess('Documento retificado carregado com sucesso.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        } catch (\Throwable) {
            $this->adminError('Falha ao carregar documento retificado.', 500, '/admin/human-review');
        }
    }

    private function storeManualUpload(int $orderId, mixed $file, string $directory): string
    {
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload do documento é obrigatório.');
        }
        $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'pdf'], true)) {
            throw new RuntimeException('Formato inválido. Use DOCX ou PDF.');
        }
        $maxBytes = max(1, (int) ($_ENV['MANUAL_DOCUMENT_MAX_SIZE_MB'] ?? 25)) * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) < 1 || (int) ($file['size'] ?? 0) > $maxBytes) {
            throw new RuntimeException(sprintf('O ficheiro deve ter entre 1 byte e %d MB.', (int) ($maxBytes / 1024 / 1024)));
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $allowedMimes = $ext === 'pdf'
            ? ['application/pdf']
            : ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('O conteúdo do ficheiro não corresponde ao formato seleccionado.');
        }
        $relativeDir = $directory . '/order-' . $orderId;
        $absoluteDir = (new StoragePathService())->uploadsBase() . '/' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Falha ao preparar directório para upload.');
        }
        $fileName = sprintf('pedido-%d-%d.%s', $orderId, time(), $ext);
        if (!move_uploaded_file((string) $file['tmp_name'], $absoluteDir . '/' . $fileName)) {
            throw new RuntimeException('Falha ao mover ficheiro enviado.');
        }
        return $relativeDir . '/' . $fileName;
    }

    private function rollbackAndRemoveUpload(\PDO $db, ?string $relativePath): void
    {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        if ($relativePath !== null) {
            $path = (new StoragePathService())->uploadsBase() . '/' . $relativePath;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}

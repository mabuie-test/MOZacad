<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\GeneratedDocumentRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Services\StoragePathService;
use App\Services\AdminHumanReviewService;
use RuntimeException;

final class AdminHumanReviewController extends BaseController
{
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

            $file = $_FILES['corrected_document'] ?? null;
            if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Upload do documento retificado é obrigatório.');
            }

            $ext = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
            if (!in_array($ext, ['docx', 'pdf'], true)) {
                throw new RuntimeException('Formato inválido. Use DOCX ou PDF.');
            }

            $paths = new StoragePathService();
            $relativeDir = 'human-review-corrections/order-' . (int) ($queue['order_id'] ?? 0);
            $absoluteDir = $paths->uploadsBase() . '/' . $relativeDir;
            if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
                throw new RuntimeException('Falha ao preparar directório para upload.');
            }
            $fileName = sprintf('doc-%d-v%d-retificado-%d.%s', (int) ($queue['generated_document_id'] ?? 0), (int) ($queue['generated_document_version'] ?? 1), time(), $ext);
            $absolutePath = $absoluteDir . '/' . $fileName;
            if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
                throw new RuntimeException('Falha ao mover ficheiro enviado.');
            }

            $relativePath = $relativeDir . '/' . $fileName;
            (new GeneratedDocumentRepository())->updateFilePathAndStatus((int) ($queue['generated_document_id'] ?? 0), $relativePath, 'pending_human_review');
            $this->audit('admin.human_review.manual_document_uploaded', 'generated_document', (int) ($queue['generated_document_id'] ?? 0), ['queue_id' => $queueId, 'file_path' => $relativePath], $permission);
            $this->adminSuccess('Documento retificado carregado com sucesso.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        } catch (\Throwable) {
            $this->adminError('Falha ao carregar documento retificado.', 500, '/admin/human-review');
        }
    }
}

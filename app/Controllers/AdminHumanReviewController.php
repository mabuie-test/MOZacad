<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AdminHumanReviewService;
use RuntimeException;

final class AdminHumanReviewController extends BaseController
{
    public function assignHumanReview(int $queueId): void
    {
        if (!$this->guardAdminPost()) return;

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
        if (!$this->guardAdminPost()) return;

        $decision = trim((string) ($_POST['decision'] ?? ''));
        try {
            (new AdminHumanReviewService())->decide((int) ($_SESSION['auth_user_id'] ?? 0), $queueId, $decision, trim((string) ($_POST['notes'] ?? '')) ?: null);
            $this->audit('admin.human_review.decided', 'human_review_queue', $queueId, ['decision' => $decision]);
            $this->adminSuccess('Decisão de revisão humana guardada.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        } catch (\Throwable) {
            $this->adminError('Falha ao processar decisão da revisão humana.', 500, '/admin/human-review');
        }
    }
}

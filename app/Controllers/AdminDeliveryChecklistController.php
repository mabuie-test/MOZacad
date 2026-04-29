<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DeliveryChecklistRepository;
use RuntimeException;

final class AdminDeliveryChecklistController extends BaseController
{
    public function updateItem(int $documentId, int $version): void
    {
        $permission = 'delivery.checklist.review';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        $item = trim((string) ($_POST['checklist_item'] ?? ''));
        $status = trim((string) ($_POST['status'] ?? 'pending'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $isChecked = isset($_POST['is_checked']) && (string) $_POST['is_checked'] === '1';

        if ($item === '') {
            $this->adminError('checklist_item é obrigatório.', 422, '/admin/human-review');
            return;
        }

        try {
            $actorId = (int) ($_SESSION['auth_user_id'] ?? 0);
            (new DeliveryChecklistRepository())->updateItemStatus($documentId, $version, $item, $isChecked, $status, $actorId, $notes !== '' ? $notes : null);
            $this->audit('admin.delivery_checklist.item_updated', 'generated_document', $documentId, [
                'document_version' => $version,
                'checklist_item' => $item,
                'is_checked' => $isChecked,
                'status' => $status,
                'notes' => $notes,
            ], $permission);
            $this->adminSuccess('Item do checklist atualizado.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        }
    }

    public function signAsReviewer(int $documentId, int $version): void
    {
        $permission = 'delivery.checklist.review';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        try {
            $actorId = (int) ($_SESSION['auth_user_id'] ?? 0);
            (new DeliveryChecklistRepository())->signReviewer($documentId, $version, $actorId);
            $this->audit('admin.delivery_checklist.reviewer_signed', 'generated_document', $documentId, [
                'document_version' => $version,
            ], $permission);
            $this->adminSuccess('Checklist assinado como revisor.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        }
    }

    public function signAsApprover(int $documentId, int $version): void
    {
        $permission = 'delivery.checklist.approve';
        if (!$this->guardAdminPermissionPost($permission, '/admin/human-review')) return;

        try {
            $actorId = (int) ($_SESSION['auth_user_id'] ?? 0);
            (new DeliveryChecklistRepository())->signApprover($documentId, $version, $actorId);
            $this->audit('admin.delivery_checklist.approver_signed', 'generated_document', $documentId, [
                'document_version' => $version,
            ], $permission);
            $this->adminSuccess('Checklist assinado como aprovador final.', '/admin/human-review');
        } catch (RuntimeException $e) {
            $this->adminError($e->getMessage(), 422, '/admin/human-review');
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\AuditLogRepository;
use App\Repositories\OrderAttachmentRepository;
use App\Repositories\OrderRepository;
use App\Repositories\WorkTypeRepository;
use App\Repositories\AcademicLevelRepository;
use Throwable;

final class OrderApplicationService
{
    public function __construct(
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly PricingService $pricing = new PricingService(),
        private readonly SecureUploadService $uploads = new SecureUploadService(),
        private readonly OrderAttachmentRepository $attachments = new OrderAttachmentRepository(),
        private readonly AuditLogRepository $audit = new AuditLogRepository(),
        private readonly ApplicationLoggerService $logger = new ApplicationLoggerService(),
    ) {}

    public function createOrder(int $userId, array $data, array $extras, array $files, array $rawPost): array
    {
        $workType = (new WorkTypeRepository())->findById((int) $data['work_type_id']);
        $academicLevel = (new AcademicLevelRepository())->findById((int) $data['academic_level_id']);
        if ($workType === null || $academicLevel === null) {
            throw new \RuntimeException('Tipo de trabalho ou nível académico inválido.');
        }

        $db = Database::connect();
        try {
            $db->beginTransaction();
            $orderId = $this->orders->create($data);
            $this->orders->createRequirement([
                'order_id' => $orderId,
                'needs_institution_cover' => $extras['needs_institution_cover'] ? 1 : 0,
                'needs_abstract' => isset($rawPost['needs_abstract']) ? (int) !!$rawPost['needs_abstract'] : 1,
                'needs_bilingual_abstract' => $extras['needs_bilingual_abstract'] ? 1 : 0,
                'needs_methodology_review' => $extras['needs_methodology_review'] ? 1 : 0,
                'needs_humanized_revision' => $extras['needs_humanized_revision'] ? 1 : 0,
                'needs_slides' => $extras['needs_slides'] ? 1 : 0,
                'needs_defense_summary' => $extras['needs_defense_summary'] ? 1 : 0,
                'notes' => trim((string) ($rawPost['requirement_notes'] ?? '')) ?: null,
            ]);

            $pricing = $this->pricing->calculate([
                'order_id' => $orderId,
                'user_id' => $userId,
                'work_type_id' => (int) $data['work_type_id'],
                'work_type_slug' => (string) $workType['slug'],
                'target_pages' => (int) $data['target_pages'],
                'academic_level_multiplier' => (float) ($academicLevel['multiplier'] ?? 1),
                'complexity_multiplier' => $this->complexityMultiplier((string) $data['complexity_level']),
                'urgency_multiplier' => $this->urgencyMultiplier((string) $data['deadline_date']),
                'extras' => $extras,
                'requires_human_review' => (bool) ($workType['requires_human_review'] ?? false),
                'coupon_code' => trim((string) ($rawPost['coupon_code'] ?? '')),
            ]);

            $this->orders->updateFinalPrice($orderId, $pricing->finalTotal);

            $uploaded = $this->uploads->storeMany($files, 'orders/' . $orderId);
            foreach ($uploaded as $file) {
                $this->attachments->create([
                    'order_id' => $orderId,
                    'attachment_type' => 'supporting_document',
                    'file_name' => $file['original_name'],
                    'file_path' => $file['path'],
                    'mime_type' => $file['mime'],
                ]);
            }

            $this->audit->log($userId, 'order.create', 'order', $orderId, ['attachments_count' => count($uploaded)]);
            $db->commit();

            $this->logger->info('order.created', ['order_id' => $orderId, 'user_id' => $userId, 'final_total' => $pricing->finalTotal]);

            return ['order_id' => $orderId, 'pricing' => $pricing->toArray(), 'attachments_count' => count($uploaded)];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->logger->error('order.create.failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function complexityMultiplier(string $complexity): float
    {
        return match (strtolower(trim($complexity))) {
            'low' => 1.0,
            'high' => 1.35,
            'very_high' => 1.6,
            default => 1.15,
        };
    }

    private function urgencyMultiplier(string $deadlineDate): float
    {
        $hours = (int) floor(((strtotime($deadlineDate) ?: time()) - time()) / 3600);
        return match (true) {
            $hours <= 24 => 2.0,
            $hours <= 72 => 1.5,
            $hours <= 168 => 1.2,
            default => 1.0,
        };
    }
}

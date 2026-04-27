<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Repositories\AIJobRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\OrderRepository;
use Throwable;

final class AIJobDispatchService
{
    public function __construct(
        private readonly AIJobRepository $jobs = new AIJobRepository(),
        private readonly AuditLogRepository $audit = new AuditLogRepository(),
        private readonly OrderRepository $orders = new OrderRepository(),
        private readonly DebitoLoggerService $logger = new DebitoLoggerService(),
    ) {}

    public function enqueueDocumentGeneration(array $order, array $payment, string $source = 'payment_transition'): ?int
    {
        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId <= 0) {
            return null;
        }

        $stage = 'document_generation';
        $db = Database::connect();
        $managesTransaction = !$db->inTransaction();
        if ($managesTransaction) {
            $db->beginTransaction();
        }
        try {
            $lockedOrder = $this->orders->lockByIdForUpdate($orderId);
            if (!is_array($lockedOrder)) {
                if ($managesTransaction && $db->inTransaction()) {
                    $db->rollBack();
                }
                return null;
            }

            $existing = $this->jobs->findOpenByOrderAndStage($orderId, $stage);
            if ($existing !== null) {
                if ($managesTransaction) {
                    $db->commit();
                }
                $this->logger->info('AI job dispatch skipped (open job exists)', [
                    'order_id' => $orderId,
                    'existing_job_id' => (int) $existing['id'],
                    'stage' => $stage,
                    'source' => $source,
                ]);

                return null;
            }

            $payload = [
                'order_id' => $orderId,
                'user_id' => (int) ($lockedOrder['user_id'] ?? 0),
                'work_type_id' => (int) ($lockedOrder['work_type_id'] ?? 0),
                'institution_id' => (int) ($lockedOrder['institution_id'] ?? 0),
                'title_or_theme' => (string) ($lockedOrder['title_or_theme'] ?? ''),
                'payment_id' => (int) ($payment['id'] ?? 0),
                'payment_reference' => (string) ($payment['external_reference'] ?? $payment['internal_reference'] ?? ''),
                'dispatched_at' => date('c'),
                'source' => $source,
            ];

            $jobId = $this->jobs->create($orderId, $stage, 'queued', $payload);
            if ($managesTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($managesTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->audit->log(
            null,
            'ai_job.dispatch',
            'order',
            $orderId,
            ['job_id' => $jobId, 'stage' => $stage, 'source' => $source]
        );

        $this->logger->info('AI job dispatched', [
            'job_id' => $jobId,
            'order_id' => $orderId,
            'stage' => $stage,
            'source' => $source,
        ]);

        return $jobId;
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AIJobRepository;
use App\Repositories\PaymentRepository;

final class ReconcileSuccessfulPaymentsService
{
    /**
     * @return array{checked:int,reconciled:int,jobs_created:int,skipped:int,errors:int}
     */
    public function run(): array
    {
        $payments = new PaymentRepository();
        $transitions = new PaymentStateTransitionService();
        $jobs = new AIJobRepository();

        $summary = [
            'checked' => 0,
            'reconciled' => 0,
            'jobs_created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        $batchLimit = max(10, (int) ($_ENV['RECONCILE_SUCCESSFUL_PAYMENTS_LIMIT'] ?? 500));
        $rows = $payments->findSuccessfulButNotPaid($batchLimit);

        foreach ($rows as $payment) {
            $summary['checked']++;

            $paymentId = (int) ($payment['id'] ?? 0);
            $orderId = (int) ($payment['order_id'] ?? 0);
            $beforeOpenJob = $jobs->findOpenByOrderAndStage($orderId, 'document_generation');

            try {
                $reference = trim((string) ($payment['external_reference'] ?? ''));
                if ($reference === '') {
                    $reference = trim((string) ($payment['internal_reference'] ?? 'reconcile:' . $paymentId));
                }

                $updated = $transitions->apply(
                    $payment,
                    $reference,
                    'paid',
                    'SUCCESSFUL',
                    [
                        'status' => 'SUCCESSFUL',
                        'message' => 'Pagamento reconciliado por rotina operacional.',
                        'source' => 'reconcile_successful_payments',
                        'reconciled_at' => date('Y-m-d H:i:s'),
                    ],
                    'reconcile_script'
                );

                $after = $payments->findById($paymentId);
                if ($updated && is_array($after) && (string) ($after['status'] ?? '') === 'paid') {
                    $summary['reconciled']++;
                    $afterOpenJob = $jobs->findOpenByOrderAndStage($orderId, 'document_generation');
                    if ($beforeOpenJob === null && $afterOpenJob !== null) {
                        $summary['jobs_created']++;
                    }
                    continue;
                }

                $summary['skipped']++;
            } catch (\Throwable $e) {
                $summary['errors']++;
            }
        }

        return $summary;
    }
}

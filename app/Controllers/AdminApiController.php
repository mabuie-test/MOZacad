<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Services\AdminOverviewService;

final class AdminApiController extends BaseController
{
    private const SECTION_PERMISSIONS = [
        'overview' => 'admin.overview.view',
        'users' => 'admin.users.view',
        'orders' => 'admin.orders.view',
        'payments' => 'admin.payments.view',
        'institutions' => 'catalog.institutions.view',
        'courses' => 'catalog.courses.view',
        'disciplines' => 'catalog.disciplines.view',
        'work-types' => 'catalog.work_types.view',
        'pricing' => 'pricing.view',
        'discounts' => 'commercial.discounts.view',
        'institution-rules' => 'governance.rules.view',
        'templates' => 'governance.templates.view',
        'coupons' => 'commercial.coupons.view',
        'human-review' => 'human_review.queue.view',
        'permissions' => 'permissions.manage',
        'audit-logs' => 'audit.logs.view',
    ];

    public function section(string $section): void
    {
        if (!$this->requireAuthUserId()) return;
        if (!$this->requireFirstPartyApiAccess()) return;

        $normalized = strtolower(trim($section));
        $required = self::SECTION_PERMISSIONS[$normalized] ?? '';
        if ($required === '') {
            $this->json(['message' => 'Secção administrativa inválida.'], 404);
            return;
        }

        if (!$this->requireAdminPermission($required, '/admin')) {
            return;
        }

        if ($normalized === 'audit-logs') {
            $repo = new AuditLogRepository();
            $this->json(['items' => $repo->search($_GET)]);
            return;
        }

        $payload = (new AdminOverviewService())->payload($normalized, $_GET);
        $this->json($payload);
    }

    public function exportAuditLogs(): void
    {
        if (!$this->requireAuthUserId()) return;
        if (!$this->requireFirstPartyApiAccess()) return;
        if (!$this->requireAdminPermission('audit.logs.export', '/admin')) return;

        $format = strtolower(trim((string)($_GET['format'] ?? 'json')));
        $rows = (new AuditLogRepository())->search($_GET, 1000);

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="audit-trail-export.csv"');
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['id','actor_id','action','subject_type','subject_id','permission_code','payload_json','previous_hash','event_hash','created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [$row['id'] ?? null,$row['actor_id'] ?? null,$row['action'] ?? null,$row['subject_type'] ?? null,$row['subject_id'] ?? null,$row['permission_code'] ?? null,$row['payload_json'] ?? null,$row['previous_hash'] ?? null,$row['event_hash'] ?? null,$row['created_at'] ?? null]);
            }
            fclose($out);
            return;
        }

        $this->json(['items' => $rows, 'meta' => ['format' => 'json', 'retention_policy' => 'hot=180d archive=5y purge_after=5y']]);
    }
}

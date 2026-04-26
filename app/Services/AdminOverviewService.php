<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CouponRepository;
use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\HumanReviewQueueRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\InstitutionRuleRepository;
use App\Repositories\InstitutionWorkTypeRuleRepository;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PricingExtraRepository;
use App\Repositories\PricingRuleRepository;
use App\Repositories\TemplateRepository;
use App\Repositories\UserDiscountRepository;
use App\Repositories\UserRepository;
use App\Repositories\WorkTypeRepository;

final class AdminOverviewService
{
    public function payload(string $section, array $filters = []): array
    {
        $institutions = (new InstitutionRepository())->allForAdmin();
        $workTypes = (new WorkTypeRepository())->all(200);
        $orders = (new OrderRepository())->listAll(300);
        $payments = (new PaymentRepository())->listAll(300);
        $queueRows = (new HumanReviewQueueRepository())->listQueue(300);
        $discounts = (new UserDiscountRepository())->listAll(300);

        $orderStatusFilter = trim((string) ($filters['order_status'] ?? ''));
        $paymentStatusFilter = trim((string) ($filters['payment_status'] ?? ''));
        $reviewStatusFilter = trim((string) ($filters['review_status'] ?? ''));

        if ($orderStatusFilter !== '') {
            $orders = array_values(array_filter($orders, static fn (array $row): bool => (string) ($row['status'] ?? '') === $orderStatusFilter));
        }
        if ($paymentStatusFilter !== '') {
            $payments = array_values(array_filter($payments, static fn (array $row): bool => (string) ($row['status'] ?? '') === $paymentStatusFilter));
        }
        if ($reviewStatusFilter !== '') {
            $queueRows = array_values(array_filter($queueRows, static fn (array $row): bool => (string) ($row['status'] ?? '') === $reviewStatusFilter));
        }

        $overview = [
            'orders_pending_payment' => count(array_filter($orders, static fn (array $o): bool => (string) ($o['status'] ?? '') === 'pending_payment')),
            'orders_under_review' => count(array_filter($orders, static fn (array $o): bool => in_array((string) ($o['status'] ?? ''), ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
            'payments_failed' => count(array_filter($payments, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['failed', 'cancelled', 'expired'], true))),
            'queue_unassigned' => count(array_filter($queueRows, static fn (array $q): bool => empty($q['reviewer_id']) && in_array((string) ($q['status'] ?? ''), ['pending', 'assigned'], true))),
        ];

        $normService = new InstitutionNormDocumentService();
        $templateService = new InstitutionTemplateService();
        $normMatrix = [];
        foreach ($institutions as $institution) {
            $norm = $normService->resolveForInstitution($institution);
            $templateRows = [];
            foreach ($workTypes as $wt) {
                $templateRows[] = ['work_type' => $wt, 'state' => $templateService->resolve($institution, (int) $wt['id'])];
            }
            $normMatrix[] = ['institution' => $institution, 'norm' => $norm, 'templates' => $templateRows];
        }

        return [
            'activeSection' => $section,
            'overview' => $overview,
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'reviewStatusFilter' => $reviewStatusFilter,
            'users' => (new UserRepository())->all(300),
            'orders' => $orders,
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'reviewers' => (new UserRepository())->listByRole('human_reviewer', 80),
            'discounts' => $discounts,
            'institutions' => $institutions,
            'courses' => (new CourseRepository())->all(300),
            'disciplines' => (new DisciplineRepository())->all(300),
            'workTypes' => $workTypes,
            'pricingRules' => (new PricingRuleRepository())->all(300),
            'pricingExtras' => (new PricingExtraRepository())->all(300),
            'coupons' => (new CouponRepository())->allWithUsage(200),
            'institutionRules' => (new InstitutionRuleRepository())->all(300),
            'institutionWorkTypeRules' => (new InstitutionWorkTypeRuleRepository())->all(300),
            'templates' => (new TemplateRepository())->all(300),
            'normMatrix' => $normMatrix,
            'templatesOperationalMode' => 'publishable',
            'pricingConfig' => [
                'currency' => (new PricingConfig())->get('PRICING_CURRENCY', 'MZN'),
                'per_page_default' => (new PricingConfig())->get('PRICING_PER_PAGE_DEFAULT', 40),
                'included_pages' => (new PricingConfig())->get('PRICING_INCLUDED_PAGES_DEFAULT', 10),
                'min_order' => (new PricingConfig())->get('PRICING_MIN_ORDER_AMOUNT', 500),
            ],
        ];
    }
}

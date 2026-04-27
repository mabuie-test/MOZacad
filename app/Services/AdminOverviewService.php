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
        $collections = $this->loadCollectionsForSection($section, $filters);
        $orders = $collections['orders'];
        $payments = $collections['payments'];
        $queueRows = $collections['humanReviewQueue'];
        $discounts = $collections['discounts'];

        $normMatrix = [];
        if (in_array($section, ['overview', 'templates', 'institution-rules'], true)) {
            $normService = new InstitutionNormDocumentService();
            $templateService = new InstitutionTemplateService();
            foreach ($institutions as $institution) {
                $norm = $normService->resolveForInstitution($institution);
                $templateRows = [];
                foreach ($workTypes as $wt) {
                    $templateRows[] = ['work_type' => $wt, 'state' => $templateService->resolve($institution, (int) $wt['id'])];
                }
                $normMatrix[] = ['institution' => $institution, 'norm' => $norm, 'templates' => $templateRows];
            }
        }

        return [
            'activeSection' => $section,
            'overview' => $this->buildOverview($orders, $payments, $queueRows),
            'orderStatusFilter' => $collections['orderStatusFilter'],
            'paymentStatusFilter' => $collections['paymentStatusFilter'],
            'reviewStatusFilter' => $collections['reviewStatusFilter'],
            'users' => in_array($section, ['overview', 'users', 'discounts'], true) ? (new UserRepository())->all(300) : [],
            'orders' => $orders,
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'reviewers' => in_array($section, ['overview', 'human-review'], true) ? (new UserRepository())->listByRole('human_reviewer', 80) : [],
            'discounts' => $discounts,
            'institutions' => $institutions,
            'courses' => in_array($section, ['overview', 'courses'], true) ? (new CourseRepository())->all(300) : [],
            'disciplines' => in_array($section, ['overview', 'disciplines'], true) ? (new DisciplineRepository())->all(300) : [],
            'workTypes' => $workTypes,
            'pricingRules' => in_array($section, ['overview', 'pricing'], true) ? (new PricingRuleRepository())->all(300) : [],
            'pricingExtras' => in_array($section, ['overview', 'pricing'], true) ? (new PricingExtraRepository())->all(300) : [],
            'coupons' => in_array($section, ['overview', 'coupons'], true) ? (new CouponRepository())->allWithUsage(200) : [],
            'institutionRules' => in_array($section, ['overview', 'institution-rules'], true) ? (new InstitutionRuleRepository())->all(300) : [],
            'institutionWorkTypeRules' => in_array($section, ['overview', 'institution-rules'], true) ? (new InstitutionWorkTypeRuleRepository())->all(300) : [],
            'templates' => in_array($section, ['overview', 'templates'], true) ? (new TemplateRepository())->all(300) : [],
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

    private function loadCollectionsForSection(string $section, array $filters): array
    {
        $orders = in_array($section, ['overview', 'orders', 'payments'], true) ? (new OrderRepository())->listAll(300) : [];
        $payments = in_array($section, ['overview', 'payments', 'orders'], true) ? (new PaymentRepository())->listAll(300) : [];
        $queueRows = in_array($section, ['overview', 'human-review'], true) ? (new HumanReviewQueueRepository())->listQueue(300) : [];
        $discounts = in_array($section, ['overview', 'discounts'], true) ? (new UserDiscountRepository())->listAll(300) : [];

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

        return [
            'orders' => $orders,
            'payments' => $payments,
            'humanReviewQueue' => $queueRows,
            'discounts' => $discounts,
            'orderStatusFilter' => $orderStatusFilter,
            'paymentStatusFilter' => $paymentStatusFilter,
            'reviewStatusFilter' => $reviewStatusFilter,
        ];
    }

    private function buildOverview(array $orders, array $payments, array $queueRows): array
    {
        return [
            'orders_pending_payment' => count(array_filter($orders, static fn (array $o): bool => (string) ($o['status'] ?? '') === 'pending_payment')),
            'orders_under_review' => count(array_filter($orders, static fn (array $o): bool => in_array((string) ($o['status'] ?? ''), ['under_human_review', 'revision_requested', 'returned_for_revision'], true))),
            'payments_failed' => count(array_filter($payments, static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['failed', 'cancelled', 'expired'], true))),
            'queue_unassigned' => count(array_filter($queueRows, static fn (array $q): bool => empty($q['reviewer_id']) && in_array((string) ($q['status'] ?? ''), ['pending', 'assigned'], true))),
        ];
    }
}

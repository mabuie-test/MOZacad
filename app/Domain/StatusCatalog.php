<?php

declare(strict_types=1);

namespace App\Domain;

final class StatusCatalog
{
    public const ORDER = [
        'draft',
        'pending_payment',
        'queued',
        'paused_admin',
        'under_human_review',
        'delivery_blocked',
        'ready',
        'revision_requested',
        'returned_for_revision',
        'approved',
    ];

    public const DOCUMENT = [
        'pending_human_review',
        'generated',
        'qa_approved',
        'final_approved',
        'approved',
        'rejected',
    ];

    public const HUMAN_REVIEW_QUEUE = [
        'pending',
        'assigned',
        'qa_approved',
        'final_approved',
        'rejected',
    ];

    public static function orderStatuses(): array { return self::ORDER; }
    public static function documentStatuses(): array { return self::DOCUMENT; }
    public static function humanReviewQueueStatuses(): array { return self::HUMAN_REVIEW_QUEUE; }
}

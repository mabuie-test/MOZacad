<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OrderBriefingDTO;

final class RequirementInterpreterService
{
    public function interpret(array $order, array $requirements): OrderBriefingDTO
    {
        return new OrderBriefingDTO(
            orderId: (int)$order['id'],
            title: trim((string)$order['title_or_theme']),
            problem: $order['problem_statement'] ? trim((string)$order['problem_statement']) : null,
            generalObjective: $order['general_objective'] ? trim((string)$order['general_objective']) : null,
            specificObjectives: json_decode($order['specific_objectives_json'] ?? '[]', true) ?: [],
            keywords: json_decode($order['keywords_json'] ?? '[]', true) ?: [],
            extras: $requirements,
            raw: ['order' => $order, 'requirements' => $requirements],
        );
    }
}

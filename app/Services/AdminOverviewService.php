<?php

declare(strict_types=1);

namespace App\Services;

final class AdminOverviewService
{
    public function __construct(
        private readonly AdminOperationsReadService $operations = new AdminOperationsReadService(),
        private readonly AdminCatalogReadService $catalog = new AdminCatalogReadService(),
        private readonly AdminCommercialReadService $commercial = new AdminCommercialReadService(),
        private readonly AdminGovernanceReadService $governance = new AdminGovernanceReadService(),
    ) {}

    public function payload(string $section, array $filters = []): array
    {
        $catalog = $this->catalog->load($section);
        $operations = $this->operations->load($section, $filters);
        $commercial = $this->commercial->load($section);
        $governance = $this->governance->load($section, $catalog['institutions'], $catalog['workTypes']);

        return array_merge(
            ['activeSection' => $section],
            $operations,
            $catalog,
            $commercial,
            $governance,
        );
    }
}

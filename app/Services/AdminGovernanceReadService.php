<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\InstitutionRuleRepository;
use App\Repositories\InstitutionWorkTypeRuleRepository;
use App\Repositories\TemplateArtifactRepository;
use App\Repositories\TemplateRepository;

final class AdminGovernanceReadService
{
    public function load(string $section, array $institutions, array $workTypes): array
    {
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
            'institutionRules' => in_array($section, ['overview', 'institution-rules'], true) ? (new InstitutionRuleRepository())->all(300) : [],
            'institutionWorkTypeRules' => in_array($section, ['overview', 'institution-rules'], true) ? (new InstitutionWorkTypeRuleRepository())->all(300) : [],
            'templates' => in_array($section, ['overview', 'templates'], true) ? (new TemplateRepository())->all(300) : [],
            'templateArtifacts' => in_array($section, ['overview', 'templates'], true) ? (new TemplateArtifactRepository())->listRecent(400) : [],
            'normMatrix' => $normMatrix,
            'templatesOperationalMode' => 'publishable_with_lifecycle',
        ];
    }
}

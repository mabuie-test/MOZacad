<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\WorkTypeRepository;

final class AdminCatalogReadService
{
    public function load(string $section): array
    {
        $institutions = (new InstitutionRepository())->allForAdmin();
        $workTypes = (new WorkTypeRepository())->all(200);

        return [
            'institutions' => $institutions,
            'courses' => in_array($section, ['overview', 'courses'], true) ? (new CourseRepository())->all(300) : [],
            'disciplines' => in_array($section, ['overview', 'disciplines'], true) ? (new DisciplineRepository())->all(300) : [],
            'workTypes' => $workTypes,
        ];
    }
}

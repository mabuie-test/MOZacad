<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CourseRepository;
use App\Repositories\DisciplineRepository;
use App\Repositories\InstitutionRepository;
use App\Repositories\WorkTypeRepository;

final class AdminAcademicCatalogService
{
    public function upsertInstitution(int $id, array $input): ?int
    {
        $payload = [
            'name' => trim((string) ($input['name'] ?? '')),
            'short_name' => trim((string) ($input['short_name'] ?? '')) ?: null,
            'slug' => trim((string) ($input['slug'] ?? '')) ?: null,
            'is_active' => !empty($input['is_active']),
        ];
        if ($payload['name'] === '') {
            return null;
        }

        $repo = new InstitutionRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) {
            $repo->update($id, $payload);
        }

        return $savedId;
    }

    public function upsertCourse(int $id, array $input): ?int
    {
        $payload = [
            'institution_id' => (int) ($input['institution_id'] ?? 0),
            'name' => trim((string) ($input['name'] ?? '')),
            'code' => trim((string) ($input['code'] ?? '')) ?: null,
            'is_active' => !empty($input['is_active']),
        ];
        if ($payload['institution_id'] <= 0 || $payload['name'] === '') {
            return null;
        }

        $repo = new CourseRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) {
            $repo->update($id, $payload);
        }

        return $savedId;
    }

    public function upsertDiscipline(int $id, array $input): ?int
    {
        $payload = [
            'institution_id' => (int) ($input['institution_id'] ?? 0),
            'course_id' => (int) ($input['course_id'] ?? 0),
            'name' => trim((string) ($input['name'] ?? '')),
            'code' => trim((string) ($input['code'] ?? '')) ?: null,
            'is_active' => !empty($input['is_active']),
        ];
        if ($payload['name'] === '') {
            return null;
        }

        $repo = new DisciplineRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) {
            $repo->update($id, $payload);
        }

        return $savedId;
    }

    public function upsertWorkType(int $id, array $input): ?int
    {
        $payload = [
            'name' => trim((string) ($input['name'] ?? '')),
            'slug' => trim((string) ($input['slug'] ?? '')),
            'description' => trim((string) ($input['description'] ?? '')) ?: null,
            'is_active' => !empty($input['is_active']),
            'base_price' => (float) ($input['base_price'] ?? 0),
            'default_complexity' => (string) ($input['default_complexity'] ?? 'medium'),
            'requires_human_review' => !empty($input['requires_human_review']),
            'is_premium_type' => !empty($input['is_premium_type']),
            'display_order' => (int) ($input['display_order'] ?? 0),
        ];
        if ($payload['name'] === '' || $payload['slug'] === '') {
            return null;
        }

        $repo = new WorkTypeRepository();
        $savedId = $id > 0 ? $id : $repo->create($payload);
        if ($id > 0) {
            $repo->update($id, $payload);
        }

        return $savedId;
    }
}

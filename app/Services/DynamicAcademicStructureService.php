<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use Throwable;

final class DynamicAcademicStructureService
{
    private const DEFAULT_WORD_RANGES = [
        'resumo' => [120, 220],
        'abstract' => [120, 220],
        'introducao' => [200, 400],
        'metodologia' => [260, 520],
        'conclusao' => [160, 320],
        'references' => [80, 240],
    ];

    private readonly ApplicationLoggerService $logger;

    public function __construct(?ApplicationLoggerService $logger = null)
    {
        $this->logger = $logger ?? new ApplicationLoggerService();
    }

    public function buildDynamicBlueprint(array $order, array $briefing, array $workType, array $baseBlueprint, array $rules): array
    {
        $title = (string) ($briefing['title'] ?? $order['topic'] ?? '');
        $normalizedTitle = $this->normalizeTitle($title);
        $pages = (int) ($order['target_pages'] ?? 5);

        foreach ($this->thematicProfiles() as $profile) {
            if ($this->matchesProfile($normalizedTitle, $profile['criteria'])) {
                $sections = $profile['sections'];

                if ($pages <= 3) {
                    $sections = array_slice($sections, 0, 7);
                }

                return $this->mapSections($sections, $profile['id'], $profile['word_ranges_by_code'] ?? []);
            }
        }

        return $baseBlueprint;
    }

    private function thematicProfiles(): array
    {
        try {
            $catalog = Config::get('academic_thematic_profiles');
        } catch (Throwable $e) {
            $this->logger->error('dynamic_structure.profile_catalog_unavailable', ['error' => $e->getMessage()]);
            return [];
        }

        $validProfiles = [];

        foreach ($catalog as $idx => $profile) {
            if (!is_array($profile)) {
                $this->logger->error('dynamic_structure.profile_invalid_type', ['index' => $idx]);
                continue;
            }

            if (!$this->isValidProfile($profile)) {
                $this->logger->error('dynamic_structure.profile_invalid_schema', [
                    'index' => $idx,
                    'id' => $profile['id'] ?? null,
                ]);
                continue;
            }

            $validProfiles[] = $profile;
        }

        return $validProfiles;
    }

    private function isValidProfile(array $profile): bool
    {
        if (!is_string($profile['id'] ?? null) || trim((string) $profile['id']) === '') {
            return false;
        }

        if (!is_array($profile['criteria'] ?? null) || $profile['criteria'] === []) {
            return false;
        }

        if (!is_array($profile['sections'] ?? null) || $profile['sections'] === []) {
            return false;
        }

        foreach ($profile['criteria'] as $criterion) {
            if (!is_array($criterion) || !isset($criterion[0], $criterion[1]) || !is_array($criterion[1]) || $criterion[1] === []) {
                return false;
            }
        }

        foreach ($profile['sections'] as $section) {
            if (!is_string($section) || trim($section) === '') {
                return false;
            }
        }

        if (!isset($profile['word_ranges_by_code'])) {
            return true;
        }

        if (!is_array($profile['word_ranges_by_code'])) {
            return false;
        }

        foreach ($profile['word_ranges_by_code'] as $code => $range) {
            if (!is_string($code) || !is_array($range) || count($range) !== 2) {
                return false;
            }
            if (!is_int($range[0]) || !is_int($range[1]) || $range[0] < 0 || $range[1] < $range[0]) {
                return false;
            }
        }

        return true;
    }

    private function matchesProfile(string $normalizedTitle, array $criteria): bool
    {
        // Regra editorial de matching: grupos em AND; variantes de cada grupo em OR.
        foreach ($criteria as $criterion) {
            if (!isset($criterion[1]) || !is_array($criterion[1])) {
                continue;
            }

            $matchesAnyVariant = false;

            foreach ($criterion[1] as $variant) {
                $normalizedVariant = $this->normalizeTitle((string) $variant);

                if ($normalizedVariant !== '' && str_contains($normalizedTitle, $normalizedVariant)) {
                    $matchesAnyVariant = true;
                    break;
                }
            }

            if (!$matchesAnyVariant) {
                return false;
            }
        }

        return true;
    }

    private function mapSections(array $titles, string $profileId, array $wordRangesByCode = []): array
    {
        $out = [];

        foreach ($titles as $idx => $title) {
            $code = $this->resolveSectionCode((string) $title) ?? 'sec_' . ($idx + 1);
            [$minWords, $maxWords] = $this->resolveWordRange($code, $idx, count($titles), $wordRangesByCode);

            $out[] = [
                'code' => $code,
                'title' => $title,
                'min_words' => $minWords,
                'max_words' => $maxWords,
                'dynamic_profile_id' => $profileId,
            ];
        }

        return $out;
    }

    private function resolveSectionCode(string $title): ?string
    {
        $normalized = $this->normalizeTitle($title);

        $containsMap = [
            'resumo' => 'resumo',
            'abstract' => 'abstract',
            'introducao' => 'introducao',
            'enquadramento' => 'enquadramento',
            'metodologia' => 'metodologia',
            'metodo' => 'metodologia',
            'referencias' => 'references',
            'bibliografia' => 'references',
            'conclusao' => 'conclusao',
            'consideracoes finais' => 'conclusao',
        ];

        foreach ($containsMap as $needle => $code) {
            if (str_contains($normalized, $needle)) {
                return $code;
            }
        }

        return null;
    }

    private function normalizeTitle(string $title): string
    {
        $normalized = mb_strtolower(trim($title));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = is_string($ascii) ? $ascii : $normalized;
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? '');
    }

    private function resolveWordRange(string $code, int $idx, int $total, array $wordRangesByCode = []): array
    {
        if (isset($wordRangesByCode[$code]) && is_array($wordRangesByCode[$code])) {
            return $wordRangesByCode[$code];
        }

        if (isset(self::DEFAULT_WORD_RANGES[$code])) {
            return self::DEFAULT_WORD_RANGES[$code];
        }

        if ($idx === 0 || $idx === $total - 1) {
            return [160, 320];
        }

        return [220, 520];
    }
}

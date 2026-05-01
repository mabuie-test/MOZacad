<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Config;
use Closure;
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

    private const MIN_SINGLE_VARIANT_LENGTH = 4;
    private const MULTI_TERM_WINDOW = 3;

    private readonly ApplicationLoggerService $logger;
    private readonly Closure $catalogLoader;

    public function __construct(?ApplicationLoggerService $logger = null, ?callable $catalogLoader = null)
    {
        $this->logger = $logger ?? new ApplicationLoggerService();
        $this->catalogLoader = $catalogLoader !== null
            ? Closure::fromCallable($catalogLoader)
            : static fn (): mixed => Config::get('academic_thematic_profiles');
    }

    public function buildDynamicBlueprint(array $order, array $briefing, array $workType, array $baseBlueprint, array $rules): array
    {
        $title = (string) ($briefing['title'] ?? $order['topic'] ?? '');
        $normalizedTitle = $this->normalizeTitle($title);
        $titleTokens = $this->tokenize($normalizedTitle, false);
        $pages = (int) ($order['target_pages'] ?? 5);

        $matches = [];

        foreach ($this->thematicProfiles() as $profile) {
            $matchedCriteria = $this->matchedCriteria($normalizedTitle, $titleTokens, $profile['criteria']);
            if ($matchedCriteria !== null) {
                $matches[] = ['profile' => $profile, 'matched_criteria' => $matchedCriteria];
            }
        }

        if ($matches === []) {
            return $baseBlueprint;
        }

        usort($matches, fn (array $a, array $b): int => $this->compareMatches($a, $b));

        $selected = $matches[0];
        $profile = $selected['profile'];
        $sections = $profile['sections'];

        if ($pages <= 3) {
            $sections = array_slice($sections, 0, 7);
        }

        $topPriority = (int) ($profile['priority'] ?? 0);
        $topSpecificity = $this->matchSpecificityScore($selected);
        $priorityTiedCandidates = array_filter($matches, static function (array $match) use ($topPriority): bool {
            $candidateProfile = $match['profile'];
            return (int) ($candidateProfile['priority'] ?? 0) === $topPriority;
        });

        $tiedCandidates = array_values(array_map(function (array $match): array {
            $candidateProfile = $match['profile'];
            return [
                'id' => (string) ($candidateProfile['id'] ?? ''),
                'priority' => (int) ($candidateProfile['priority'] ?? 0),
                'specificity' => $this->matchSpecificityScore($match),
            ];
        }, array_filter($matches, function (array $match) use ($topPriority, $topSpecificity): bool {
            $candidateProfile = $match['profile'];
            return (int) ($candidateProfile['priority'] ?? 0) === $topPriority
                && $this->matchSpecificityScore($match) === $topSpecificity;
        })));

        $this->logger->info('dynamic_structure.profile_selected', [
            'dynamic_profile_id' => $profile['id'],
            'priority' => $topPriority,
            'specificity' => $topSpecificity,
            'matched_criteria' => $selected['matched_criteria'],
            'title_tokens' => $titleTokens,
            'candidate_count' => count($matches),
            'tie_break_applied' => count($priorityTiedCandidates) > 1,
            'tie_break_rule' => 'priority>specificity>id',
            'tied_candidates' => $tiedCandidates,
            'tie_breaker_order' => ['priority_desc', 'specificity_desc', 'id_asc'],
        ]);

        return $this->mapSections($sections, $profile['id'], $profile['word_ranges_by_code'] ?? []);
    }



    private function compareMatches(array $a, array $b): int
    {
        $profileA = $a['profile'];
        $profileB = $b['profile'];

        $priorityCompare = ((int) ($profileB['priority'] ?? 0)) <=> ((int) ($profileA['priority'] ?? 0));
        if ($priorityCompare !== 0) {
            return $priorityCompare;
        }

        $specificityCompare = $this->matchSpecificityScore($b) <=> $this->matchSpecificityScore($a);
        if ($specificityCompare !== 0) {
            return $specificityCompare;
        }

        return strcmp((string) ($profileA['id'] ?? ''), (string) ($profileB['id'] ?? ''));
    }


    private function matchSpecificityScore(array $match): int
    {
        $matchedCriteria = $match['matched_criteria'] ?? [];
        if (!is_array($matchedCriteria) || $matchedCriteria === []) {
            return 0;
        }

        $criteriaCount = count($matchedCriteria);
        $variantTerms = 0;

        foreach ($matchedCriteria as $item) {
            $variant = $this->normalizeTitle((string) ($item['variant'] ?? ''));
            $variantTerms += count($this->tokenize($variant, false));
        }

        return ($criteriaCount * 1000) + $variantTerms;
    }

    private function profileSpecificityScore(array $profile): int
    {
        $criteria = $profile['criteria'] ?? [];
        if (!is_array($criteria) || $criteria === []) {
            return 0;
        }

        $criteriaCount = 0;
        $variantTerms = 0;

        foreach ($criteria as $criterion) {
            if (!is_array($criterion) || !isset($criterion[1]) || !is_array($criterion[1])) {
                continue;
            }

            $criteriaCount++;

            foreach ($criterion[1] as $variant) {
                $tokens = $this->tokenize($this->normalizeTitle((string) $variant), false);
                $variantTerms += count($tokens);
            }
        }

        return ($criteriaCount * 1000) + $variantTerms;
    }

    private function thematicProfiles(): array
    {
        try {
            $catalog = ($this->catalogLoader)();
        } catch (Throwable $e) {
            $this->logger->error('dynamic_structure.profile_catalog_unavailable', ['error' => $e->getMessage()]);
            return [];
        }

        if (!is_array($catalog)) {
            $this->logger->error('dynamic_structure.profile_catalog_invalid_root_type', [
                'received_type' => gettype($catalog),
                'config_key' => 'academic_thematic_profiles',
                'environment' => getenv('APP_ENV') ?: null,
            ]);

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

        if (!isset($profile['priority']) || !is_int($profile['priority'])) {
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

            foreach ($criterion[1] as $variant) {
                $normalizedVariant = $this->normalizeTitle((string) $variant);
                if ($normalizedVariant === '') {
                    return false;
                }

                if (!str_contains($normalizedVariant, ' ') && mb_strlen($normalizedVariant) < self::MIN_SINGLE_VARIANT_LENGTH) {
                    return false;
                }
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

    private function matchedCriteria(string $normalizedTitle, array $titleTokens, array $criteria): ?array
    {
        $matched = [];
        foreach ($criteria as $criterion) {
            if (!isset($criterion[1]) || !is_array($criterion[1])) {
                continue;
            }

            $criterionName = (string) ($criterion[0] ?? 'unnamed');
            $matchesAnyVariant = false;

            foreach ($criterion[1] as $variant) {
                $normalizedVariant = $this->normalizeTitle((string) $variant);

                if ($normalizedVariant !== '' && $this->matchesVariant($normalizedTitle, $titleTokens, $normalizedVariant)) {
                    $matchesAnyVariant = true;
                    $matched[] = ['criterion' => $criterionName, 'variant' => $normalizedVariant];
                    break;
                }
            }

            if (!$matchesAnyVariant) {
                return null;
            }
        }

        return $matched;
    }

    private function matchesVariant(string $normalizedTitle, array $titleTokens, string $normalizedVariant): bool
    {
        $variantTokens = $this->tokenize($normalizedVariant);
        if ($variantTokens === []) {
            return false;
        }

        if (count($variantTokens) === 1) {
            return in_array($variantTokens[0], $titleTokens, true);
        }

        if (str_contains($normalizedTitle, $normalizedVariant)) {
            return true;
        }

        return $this->tokensInWindow($titleTokens, $variantTokens, self::MULTI_TERM_WINDOW);
    }

    private function tokenize(string $text, bool $unique = true): array
    {
        if ($text === '') {
            return [];
        }

        $tokens = preg_split('/\s+/', $text) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        return $unique ? array_values(array_unique($tokens)) : $tokens;
    }

    private function tokensInWindow(array $titleTokens, array $variantTokens, int $window): bool
    {
        $positionsByToken = [];
        foreach (array_values($titleTokens) as $idx => $token) {
            $positionsByToken[$token][] = $idx;
        }

        foreach ($variantTokens as $token) {
            if (!isset($positionsByToken[$token])) {
                return false;
            }
        }

        $allPositions = [];
        foreach ($variantTokens as $token) {
            $allPositions = array_merge($allPositions, $positionsByToken[$token]);
        }

        if ($allPositions === []) {
            return false;
        }

        sort($allPositions);
        return ($allPositions[count($allPositions) - 1] - $allPositions[0]) <= (count($variantTokens) - 1 + $window);
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

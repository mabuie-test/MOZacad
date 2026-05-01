<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\UnicodeWordCounter;

final class AcademicContentQualityService
{
    private array $forbidden = ['no contexto do tema', 'o estudo apresenta síntese académica', 'referências organizadas conforme'];

    private array $analyticalConnectors = [
        'portanto',
        'contudo',
        'assim',
        'além disso',
        'por conseguinte',
        'todavia',
        'não obstante',
        'em contraste',
        'em síntese',
        'consequentemente',
    ];

    public function validateSection(array $section, array $briefing, array $blueprint): array
    {
        $rawContent = trim((string) ($section['content'] ?? ''));
        $content = mb_strtolower($rawContent);
        $issues = [];

        $wordIssues = $this->validateWordCount($section, $content, $blueprint);
        if ($wordIssues !== []) {
            $issues = [...$issues, ...$wordIssues];
        }

        if (str_contains($content, '#') || str_contains($content, '**')) {
            $issues[] = 'format.markdown_detected';
        }

        foreach ($this->forbidden as $phrase) {
            if (str_contains($content, $phrase)) {
                $issues[] = 'content.forbidden_phrase:' . $phrase;
                break;
            }
        }

        $typeIssues = $this->validateSectionTypeCriteria($section, $content);
        if ($typeIssues !== []) {
            $issues = [...$issues, ...$typeIssues];
        }

        $conceptualIssues = $this->validateConceptualDensity($content, $briefing);
        if ($conceptualIssues !== []) {
            $issues = [...$issues, ...$conceptualIssues];
        }

        return ['ok' => $issues === [], 'issues' => array_values(array_unique($issues))];
    }

    public function validateDocument(array $sections, array $briefing, array $blueprint): array
    {
        $issues = [];
        $robustAnalyticalSections = 0;

        foreach ($sections as $section) {
            $res = $this->validateSection($section, $briefing, $blueprint);
            if (!$res['ok']) {
                $issues[] = ['title' => $section['title'] ?? 'secção', 'issues' => $res['issues']];
            }

            if ($this->isAnalyticalSectionRobust($section, $briefing, $blueprint)) {
                $robustAnalyticalSections++;
            }
        }

        if ($robustAnalyticalSections < 1) {
            $issues[] = [
                'title' => 'documento',
                'issues' => ['coverage.missing_robust_analytical_section'],
            ];
        }

        return ['ok' => $issues === [], 'issues' => $issues];
    }

    private function validateWordCount(array $section, string $content, array $blueprint): array
    {
        $wordCount = UnicodeWordCounter::count($content);
        $sectionBlueprint = $this->resolveBlueprintSection($section, $blueprint);

        $issues = [];
        $minWords = isset($sectionBlueprint['min_words']) ? (int) $sectionBlueprint['min_words'] : null;
        $maxWords = isset($sectionBlueprint['max_words']) ? (int) $sectionBlueprint['max_words'] : null;

        if ($minWords !== null && $wordCount < $minWords) {
            $issues[] = sprintf('length.below_min:%d/%d', $wordCount, $minWords);
        }

        if ($maxWords !== null && $maxWords > 0 && $wordCount > $maxWords) {
            $issues[] = sprintf('length.above_max:%d/%d', $wordCount, $maxWords);
        }

        return $issues;
    }


    private function validateSectionTypeCriteria(array $section, string $content): array
    {
        $slug = $this->normalizeSectionSlug($section);

        return match ($slug) {
            'introducao' => $this->validateIntroductionCriteria($content),
            'metodologia' => $this->validateMethodologyCriteria($content),
            'conclusao' => $this->validateConclusionCriteria($content),
            default => [],
        };
    }

    private function validateIntroductionCriteria(string $content): array
    {
        $issues = [];

        if (!$this->containsAny($content, ['problema', 'lacuna', 'questão de investigação', 'pergunta de investigação'])) {
            $issues[] = 'criteria.introducao.missing_problem';
        }

        if (!$this->containsAny($content, ['objetivo', 'objetivos', 'propõe-se', 'pretende-se'])) {
            $issues[] = 'criteria.introducao.missing_objectives';
        }

        return $issues;
    }

    private function validateMethodologyCriteria(string $content): array
    {
        $issues = [];

        if (!$this->containsAny($content, ['desenho', 'design', 'abordagem metodológica'])) {
            $issues[] = 'criteria.metodologia.missing_design';
        }

        if (!$this->containsAny($content, ['procedimento', 'procedimentos', 'etapas', 'protocolo'])) {
            $issues[] = 'criteria.metodologia.missing_procedures';
        }

        if (!$this->containsAny($content, ['validade', 'critério de validade', 'fiabilidade', 'confiabilidade'])) {
            $issues[] = 'criteria.metodologia.missing_validity_criteria';
        }

        return $issues;
    }

    private function validateConclusionCriteria(string $content): array
    {
        $issues = [];

        if (!$this->containsAny($content, ['síntese', 'em síntese', 'conclui-se', 'conclusão'])) {
            $issues[] = 'criteria.conclusao.missing_synthesis';
        }

        if (!$this->containsAny($content, ['implicações', 'limitações', 'limitação', 'trabalho futuro'])) {
            $issues[] = 'criteria.conclusao.missing_implications_or_limitations';
        }

        return $issues;
    }

    private function validateConceptualDensity(string $content, array $briefing): array
    {
        $issues = [];

        $connectorsFound = 0;
        foreach ($this->analyticalConnectors as $connector) {
            if (str_contains($content, $connector)) {
                $connectorsFound++;
            }
        }

        if ($connectorsFound < 2) {
            $issues[] = sprintf('density.low_analytical_connectors:%d/2', $connectorsFound);
        }

        $technicalTerms = $this->extractTechnicalTerms($briefing);
        if ($technicalTerms !== []) {
            $termsFound = 0;
            foreach ($technicalTerms as $term) {
                if (str_contains($content, $term)) {
                    $termsFound++;
                }
            }

            $requiredTerms = min(2, count($technicalTerms));
            if ($termsFound < $requiredTerms) {
                $issues[] = sprintf('density.low_briefing_terms:%d/%d', $termsFound, $requiredTerms);
            }
        }

        return $issues;
    }

    private function isAnalyticalSectionRobust(array $section, array $briefing, array $blueprint): bool
    {
        $content = mb_strtolower(trim((string) ($section['content'] ?? '')));
        $wordIssues = $this->validateWordCount($section, $content, $blueprint);
        $typeIssues = $this->validateSectionTypeCriteria($section, $content);
        $densityIssues = $this->validateConceptualDensity($content, $briefing);

        return $wordIssues === [] && $typeIssues === [] && $densityIssues === [];
    }

    private function extractTechnicalTerms(array $briefing): array
    {
        $terms = [];
        foreach (['keywords', 'technical_terms', 'concepts'] as $field) {
            if (!isset($briefing[$field])) {
                continue;
            }

            $value = $briefing[$field];
            if (is_array($value)) {
                foreach ($value as $term) {
                    if (!is_string($term)) {
                        continue;
                    }
                    $clean = mb_strtolower(trim($term));
                    if ($clean !== '') {
                        $terms[] = $clean;
                    }
                }
                continue;
            }

            if (is_string($value)) {
                $parts = preg_split('/[,;\n]+/', $value) ?: [];
                foreach ($parts as $part) {
                    $clean = mb_strtolower(trim($part));
                    if ($clean !== '') {
                        $terms[] = $clean;
                    }
                }
            }
        }

        return array_values(array_unique($terms));
    }

    private function resolveBlueprintSection(array $section, array $blueprint): array
    {
        $targetCode = mb_strtolower(trim((string) ($section['code'] ?? '')));
        $targetTitle = mb_strtolower(trim((string) ($section['title'] ?? '')));

        $sections = $blueprint['sections'] ?? $blueprint;
        if (!is_array($sections)) {
            return [];
        }

        foreach ($sections as $bpSection) {
            if (!is_array($bpSection)) {
                continue;
            }

            $bpCode = mb_strtolower(trim((string) ($bpSection['code'] ?? '')));
            $bpTitle = mb_strtolower(trim((string) ($bpSection['title'] ?? '')));

            if (($targetCode !== '' && $bpCode === $targetCode) || ($targetTitle !== '' && $bpTitle === $targetTitle)) {
                return $bpSection;
            }
        }

        return [];
    }

    private function normalizeSectionSlug(array $section): string
    {
        $candidate = mb_strtolower(trim((string) ($section['code'] ?? $section['title'] ?? '')));

        if (str_contains($candidate, 'introdu')) {
            return 'introducao';
        }
        if (str_contains($candidate, 'metod')) {
            return 'metodologia';
        }
        if (str_contains($candidate, 'conclu')) {
            return 'conclusao';
        }

        return $candidate;
    }

    private function containsAny(string $content, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($content, $needle)) {
                return true;
            }
        }

        return false;
    }
}

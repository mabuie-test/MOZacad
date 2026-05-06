<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Academic\OperationalMetaPatterns;
use App\Domain\Academic\QualityThresholds;

final class DocumentEditorialQualityGateService
{
    private AcademicSectionClassifierService $sectionClassifier;

    /**
     * Matriz única de severidade (missing vs too_short):
     * - Secção obrigatória ausente/vazia (missing|empty): critical
     * - Secção de metodologia curta (too_short):
     *   - <90 palavras: critical (reforço automático insuficiente)
     *   - 90-119 palavras: major (aceita com auto-reforço e validação final)
     */
    public function __construct(?AcademicSectionClassifierService $sectionClassifier = null)
    {
        $this->sectionClassifier = $sectionClassifier ?? new AcademicSectionClassifierService();
    }

    public function validate(array $sections): array
    {
        $issues = [];
        $fullText = mb_strtolower($this->collectText($sections));

        $blockedPatterns = OperationalMetaPatterns::blockedRegexPatterns();

        foreach ($blockedPatterns as $pattern) {
            $rule = $this->ruleForPattern($pattern);
            if (preg_match($pattern, $fullText) === 1) {
                $issues[] = ['severity' => 'critical', 'rule' => $rule, 'message' => 'Conteúdo técnico/meta detectado no corpo final.'];
            }
        }

        $required = ['introducao', 'objectivos', 'metodologia', 'conclusao', 'referencias'];
        foreach ($required as $req) {
            $section = $this->findSection($sections, $req);
            if ($section === null || trim((string) ($section['content'] ?? '')) === '') {
                $issues[] = ['severity' => 'critical', 'rule' => 'required_section_missing_or_empty', 'message' => 'Secção obrigatória ausente/vazia: ' . $req];
            }
        }
        $issues = array_merge($issues, $this->validateLogicalOrder($sections));
        $issues = array_merge($issues, $this->validateContentDensity($sections));
        $issues = array_merge($issues, $this->validateDevelopmentAndCitationConsistency($sections));

        $refs = $this->findSection($sections, 'referencias');
        if ($refs !== null) {
            $refLines = array_values(array_filter(array_map('trim', preg_split('/\n+/', (string) ($refs['content'] ?? '')) ?: [])));
            if (count($refLines) < 3) {
                $issues[] = ['severity' => 'critical', 'rule' => 'references_insufficient', 'message' => 'Bibliografia insuficiente (mínimo 3 referências).'];
            }
            foreach ($refLines as $line) {
                if (preg_match('/\b(refer[eê]ncia incompleta|preencher autor|an[aá]lise documental|como usar fontes)\b/u', mb_strtolower($line)) === 1) {
                    $issues[] = ['severity' => 'critical', 'rule' => 'references_not_real', 'message' => 'Referência inválida/meta detectada.'];
                    break;
                }
            }
        }

        $hasCriticalIssues = false;
        foreach ($issues as $issue) {
            if ((string) ($issue['severity'] ?? '') === 'critical') {
                $hasCriticalIssues = true;
                break;
            }
        }

        return [
            'ok' => !$hasCriticalIssues,
            'issues' => $issues,
            'blocked_patterns' => $blockedPatterns,
        ];
    }


    private function ruleForPattern(string $pattern): string
    {
        return match ($pattern) {
            '/\{\s*"[^\"]+"\s*:/u' => 'json_marker_detected',
            '/\bsection_title\b|\bsection_code\b|\btext\s*:/iu' => 'serialized_fields_detected',
            '/instru[cç](?:[aã]o|[õo]es)\s+do\s+pipeline|nota[s]?\s+de\s+pipeline|com base nas regras de refinamento|marcadores operacionais/u' => 'meta_operational_text_detected',
            '/(?:^|\s)(?:payload|debug)\s*:/iu' => 'meta_operational_text_detected',
            '/\b\-\-\-\b/u' => 'technical_separator_detected',
            '/\[\[todo|placeholder|indice placeholder|lorem ipsum/u' => 'placeholder_detected',
            '/aqui est[aá] a sec[cç][aã]o/u' => 'forbidden_editorial_meta_text_detected',
            '/\brefinada\b/u' => 'forbidden_editorial_meta_text_detected',
            '/coment[aá]rio de edi[cç][aã]o|meta-editorial|linguagem meta-editorial/u' => 'forbidden_editorial_meta_text_detected',
            '/[íi]ndice autom[aá]tico \(actualiz[aá]vel no editor de texto\)/u' => 'toc_placeholder_detected',
            default => 'meta_operational_text_detected',
        };
    }

    private function collectText(array $sections): string
    {
        $chunks = [];
        foreach ($sections as $section) {
            $chunks[] = (string) ($section['title'] ?? '');
            $chunks[] = (string) ($section['content'] ?? '');
        }

        return implode("\n", $chunks);
    }

    private function findSection(array $sections, string $needle): ?array
    {
        foreach ($sections as $section) {
            if ($this->classifySectionKey($section) === $needle) {
                return $section;
            }
        }

        return null;
    }


    private function validateLogicalOrder(array $sections): array
    {
        $issues = [];
        $order = ['introducao' => 1, 'objectivos' => 2, 'metodologia' => 3, 'desenvolvimento' => 4, 'resultados' => 4, 'conclusao' => 5, 'referencias' => 6];
        $last = 0;
        $firstDevelopmentIndex = null;
        $firstConclusionIndex = null;

        foreach ($sections as $index => $section) {
            $k = $this->classifySectionKey($section);
            if (in_array($k, ['desenvolvimento', 'resultados'], true) && $firstDevelopmentIndex === null) {
                $firstDevelopmentIndex = $index;
            }
            if ($k === 'conclusao' && $firstConclusionIndex === null) {
                $firstConclusionIndex = $index;
            }
            if (!isset($order[$k])) {
                continue;
            }
            if ($order[$k] < $last) {
                $issues[] = ['severity' => 'critical', 'rule' => 'logical_order_invalid', 'message' => 'Ordem ilógica de secções detectada.'];
                break;
            }
            $last = $order[$k];
        }

        if ($firstConclusionIndex !== null && $firstDevelopmentIndex !== null && $firstConclusionIndex < $firstDevelopmentIndex) {
            $issues[] = ['severity' => 'critical', 'rule' => 'conclusion_before_development', 'message' => 'Conclusão detectada antes da secção de desenvolvimento no array final pós-processado.'];
        }

        return $issues;
    }

    private function validateContentDensity(array $sections): array
    {
        $issues = [];
        foreach ($sections as $section) {
            $k = $this->classifySectionKey($section);
            $content = trim((string) ($section['content'] ?? ''));
            $words = preg_split('/\s+/u', $content) ?: [];
            $count = count(array_filter($words, static fn (string $w): bool => trim($w) !== ''));
            if ($k === 'metodologia') {
                if ($count < 90) {
                    $issues[] = [
                        'severity' => 'critical',
                        'rule' => 'methodology_too_short',
                        'message' => 'Metodologia insuficiente (<90 palavras). Reforço automático não foi suficiente; rever secção antes de aprovar.',
                    ];
                } elseif ($count < QualityThresholds::MIN_METHODOLOGY_WORDS) {
                    $issues[] = [
                        'severity' => 'major',
                        'rule' => 'methodology_too_short',
                        'message' => 'Metodologia insuficiente (90–119 palavras): reforço aplicado automaticamente; validar coerência final antes de publicar.',
                    ];
                }
            }
            if (in_array($k, ['desenvolvimento', 'resultados'], true) && $count < QualityThresholds::MIN_DEVELOPMENT_WORDS) {
                $issues[] = ['severity' => 'critical', 'rule' => 'analysis_too_short', 'message' => 'Desenvolvimento/Análise com densidade insuficiente.'];
            }
        }
        return $issues;
    }

    private function validateDevelopmentAndCitationConsistency(array $sections): array
    {
        $issues = [];
        $developmentWords = 0;
        $developmentText = '';
        $hasConclusion = false;

        foreach ($sections as $section) {
            $k = $this->classifySectionKey($section);
            $content = trim((string) ($section['content'] ?? ''));
            if (in_array($k, ['desenvolvimento', 'resultados'], true)) {
                $developmentText .= "\n" . $content;
                $developmentWords += count(array_filter(preg_split('/\s+/u', $content) ?: [], static fn (string $w): bool => trim($w) !== ''));
            }
            if ($k === 'conclusao') {
                $hasConclusion = true;
            }
        }

        if ($developmentWords < QualityThresholds::MIN_DEVELOPMENT_WORDS) {
            $issues[] = ['severity' => 'critical', 'rule' => 'development_missing_or_short', 'message' => 'Documento sem desenvolvimento temático substantivo antes da conclusão.'];
        }
        if ($hasConclusion && $developmentWords < QualityThresholds::MIN_DEVELOPMENT_WORDS) {
            $issues[] = ['severity' => 'critical', 'rule' => 'conclusion_without_analysis', 'message' => 'Conclusão detectada sem corpo analítico suficiente.'];
        }
        $analyticSectionsCount = $this->countDevelopmentSubSections($developmentText);
        if ($analyticSectionsCount < QualityThresholds::MIN_ANALYTIC_SUBSECTIONS) {
            $issues[] = ['severity' => 'critical', 'rule' => 'development_subsections_insufficient', 'message' => 'Desenvolvimento sem número mínimo de secções analíticas (mínimo ' . QualityThresholds::MIN_ANALYTIC_SUBSECTIONS . ').'];
        }

        $citationMatches = preg_match_all('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $developmentText, $matches);
        if (($citationMatches ?: 0) < QualityThresholds::MIN_INLINE_CITATIONS) {
            $issues[] = ['severity' => 'critical', 'rule' => 'development_without_citations', 'message' => 'Desenvolvimento sem citações académicas mínimas no corpo do texto.'];
        }

        $refs = $this->findSection($sections, 'referencias');
        $refLines = array_values(array_filter(array_map('trim', preg_split('/\n+/', (string) ($refs['content'] ?? '')) ?: [])));
        if ($refLines !== [] && isset($matches[0])) {
            $hits = 0;
            foreach (array_unique($matches[0]) as $inlineCitation) {
                if (preg_match('/\(([^,]+),\s*((?:19|20)\d{2})/u', $inlineCitation, $parts) !== 1) {
                    continue;
                }
                $author = mb_strtolower(trim((string) $parts[1]));
                $year = (string) $parts[2];
                foreach ($refLines as $line) {
                    $lineNorm = mb_strtolower($line);
                    if (str_contains($lineNorm, $author) && str_contains($lineNorm, $year)) {
                        $hits++;
                        break;
                    }
                }
            }
            if ($hits === 0) {
                $issues[] = ['severity' => 'critical', 'rule' => 'references_not_used_in_body', 'message' => 'Referências finais sem correspondência com citações no desenvolvimento.'];
            }
        }

        return $issues;
    }

    private function classifySectionKey(array $section): string
    {
        return $this->sectionClassifier->classify($section);
    }

    private function countDevelopmentSubSections(string $developmentText): int
    {
        if (trim($developmentText) === '') {
            return 0;
        }
        preg_match_all('/(^|\n)\s*(\d+\.\s+[^\n]+|[A-ZÀ-Ú][^\n]{10,}\:)\s*(\n|$)/u', $developmentText, $matches);
        return is_array($matches[0] ?? null) ? count($matches[0]) : 0;
    }
}

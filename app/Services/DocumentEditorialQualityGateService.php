<?php

declare(strict_types=1);

namespace App\Services;

final class DocumentEditorialQualityGateService
{
    public function validate(array $sections): array
    {
        $issues = [];
        $fullText = mb_strtolower($this->collectText($sections));

        $blockedPatterns = [
            '/\{\s*"[^"]+"\s*:/u' => 'json_marker_detected',
            '/\bsection_title\b|\bsection_code\b|\btext\s*:/u' => 'serialized_fields_detected',
            '/com base nas regras de refinamento|instru[cç][aã]o|pipeline|payload|debug/u' => 'meta_operational_text_detected',
            '/\b\-\-\-\b/u' => 'technical_separator_detected',
            '/\[\[todo|placeholder|indice placeholder|lorem ipsum/u' => 'placeholder_detected',
        ];

        foreach ($blockedPatterns as $pattern => $rule) {
            if (preg_match($pattern, $fullText) === 1) {
                $issues[] = ['severity' => 'critical', 'rule' => $rule, 'message' => 'Conteúdo técnico/meta detectado no corpo final.'];
            }
        }

        $required = ['introducao', 'objectivos', 'metodologia', 'conclusao', 'referencias'];
        foreach ($required as $req) {
            $section = $this->findSection($sections, $req);
            if ($section === null || trim((string) ($section['content'] ?? '')) === '') {
                $issues[] = ['severity' => 'critical', 'rule' => 'required_section_empty', 'message' => 'Secção obrigatória ausente/vazia: ' . $req];
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

        return [
            'ok' => count($issues) === 0,
            'issues' => $issues,
            'blocked_patterns' => array_keys($blockedPatterns),
        ];
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
            $key = $this->norm((string) ($section['code'] ?? $section['title'] ?? ''));
            if (in_array($needle, $this->equivalents($key), true) || in_array($key, $this->equivalents($needle), true)) {
                return $section;
            }
        }

        return null;
    }

    private function equivalents(string $key): array
    {
        return match ($key) {
            'introducao' => ['introducao'],
            'objectivos', 'objetivos' => ['objectivos', 'objetivos'],
            'metodologia' => ['metodologia'],
            'conclusao' => ['conclusao'],
            'referencias', 'references', 'bibliografia' => ['referencias', 'references', 'bibliografia'],
            default => [$key],
        };
    }

    private function norm(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function validateLogicalOrder(array $sections): array
    {
        $issues = [];
        $order = ['introducao' => 1, 'objectivos' => 2, 'metodologia' => 3, 'desenvolvimento' => 4, 'resultados' => 4, 'conclusao' => 5, 'referencias' => 6];
        $last = 0;
        foreach ($sections as $section) {
            $k = $this->classifySectionKey($section);
            if (!isset($order[$k])) {
                continue;
            }
            if ($order[$k] < $last) {
                $issues[] = ['severity' => 'critical', 'rule' => 'logical_order_invalid', 'message' => 'Ordem ilógica de secções detectada.'];
                break;
            }
            $last = $order[$k];
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
            if ($k === 'metodologia' && $count < 120) {
                $issues[] = ['severity' => 'critical', 'rule' => 'methodology_too_short', 'message' => 'Metodologia demasiado curta para padrão académico.'];
            }
            if (in_array($k, ['desenvolvimento', 'resultados'], true) && $count < 220) {
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

        if ($developmentWords < 220) {
            $issues[] = ['severity' => 'critical', 'rule' => 'development_missing_or_short', 'message' => 'Documento sem desenvolvimento temático substantivo antes da conclusão.'];
        }
        if ($hasConclusion && $developmentWords < 220) {
            $issues[] = ['severity' => 'critical', 'rule' => 'conclusion_without_analysis', 'message' => 'Conclusão detectada sem corpo analítico suficiente.'];
        }

        $citationMatches = preg_match_all('/\([^)]+,\s*(19|20)\d{2}[a-z]?\)/u', $developmentText, $matches);
        if (($citationMatches ?: 0) < 2) {
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
        $s = $this->norm((string) ($section['code'] ?? '') . ' ' . (string) ($section['title'] ?? ''));
        if (str_contains($s, 'introdu')) return 'introducao';
        if (str_contains($s, 'objet') || str_contains($s, 'objec')) return 'objectivos';
        if (str_contains($s, 'metod')) return 'metodologia';
        if (str_contains($s, 'result') || str_contains($s, 'discuss')) return 'resultados';
        if (str_contains($s, 'analis') || str_contains($s, 'desenvol')) return 'desenvolvimento';
        if (str_contains($s, 'conclus')) return 'conclusao';
        if (str_contains($s, 'refer') || str_contains($s, 'bibliograf')) return 'referencias';
        return 'other';
    }
}

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
}

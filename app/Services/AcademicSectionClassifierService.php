<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicSectionClassifierService
{
    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^[0-9\.\-\)\s]+/u', '', $value) ?? $value;
        $value = strtr($value, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c']);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    public function equivalents(string $key): array
    {
        $k = $this->normalize($key);

        return match ($k) {
            'conclusao' => ['conclusao', 'consideracoes finais', 'notas conclusivas'],
            'referencias' => ['referencias', 'referencias bibliograficas', 'bibliografia', 'obras consultadas', 'references'],
            'introducao' => ['introducao', 'apresentacao', 'contextualizacao inicial'],
            'metodologia' => ['metodologia', 'procedimentos metodologicos', 'percurso metodologico', 'nota metodologica'],
            'objectivos', 'objetivos' => ['objectivos', 'objetivos'],
            default => [$k],
        };
    }

    public function classify(array $section): string
    {
        $raw = $this->normalize((string) ($section['code'] ?? '') . ' ' . (string) ($section['title'] ?? ''));

        if ($this->containsEquivalent($raw, 'introducao') || str_contains($raw, 'introdu')) return 'introducao';
        if ($this->containsEquivalent($raw, 'objectivos') || str_contains($raw, 'objet') || str_contains($raw, 'objec')) return 'objectivos';
        if ($this->containsEquivalent($raw, 'metodologia')) return 'metodologia';
        if (str_contains($raw, 'result') || str_contains($raw, 'discuss')) return 'resultados';
        if (str_contains($raw, 'analis') || str_contains($raw, 'desenvol')) return 'desenvolvimento';
        if ($this->containsEquivalent($raw, 'conclusao') || str_contains($raw, 'conclus')) return 'conclusao';
        if ($this->containsEquivalent($raw, 'referencias') || str_contains($raw, 'refer') || str_contains($raw, 'bibliograf')) return 'referencias';

        return 'other';
    }

    public function areEquivalent(string $needle, string $candidate): bool
    {
        $n = $this->normalize($needle);
        $c = $this->normalize($candidate);

        return in_array($c, $this->equivalents($n), true) || in_array($n, $this->equivalents($c), true);
    }

    private function containsEquivalent(string $raw, string $canonical): bool
    {
        foreach ($this->equivalents($canonical) as $variant) {
            if (str_contains($raw, $variant)) {
                return true;
            }
        }

        return false;
    }
}

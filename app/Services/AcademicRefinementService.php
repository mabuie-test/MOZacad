<?php

declare(strict_types=1);

namespace App\Services;

final class AcademicRefinementService
{
    private AIProviderInterface $provider;

    public function __construct(?AIProviderInterface $provider = null)
    {
        $this->provider = $provider ?? (new AIProviderResolverService())->resolve();
    }

    public function refine(array $sections, array $context = []): array
    {
        $output = [];

        foreach ($sections as $section) {
            $text = trim((string) ($section['content'] ?? ''));
            if ($text === '') {
                continue;
            }

            $sectionTitle = (string) ($section['title'] ?? 'Secção');
            $sectionCode = (string) ($section['code'] ?? 'section');
            $rules = [
                'goal' => 'coherence_and_methodology',
                'reference_style' => (string) ($context['reference_style'] ?? 'APA'),
                'section_title' => $sectionTitle,
                'section_code' => $sectionCode,
            ];

            $prompt = <<<TXT
Refina a secção académica abaixo para melhorar:
- coesão argumentativa,
- rigor metodológico,
- precisão terminológica.

Mantém o sentido original e o foco da secção "{$sectionTitle}".

Texto base:
{$text}
TXT;

            $section['content'] = trim($this->provider->refine($prompt, $rules));
            $output[] = $section;
        }

        return $output;
    }
}

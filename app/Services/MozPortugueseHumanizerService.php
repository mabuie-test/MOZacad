<?php

declare(strict_types=1);

namespace App\Services;

final class MozPortugueseHumanizerService
{
    private AIProviderInterface $provider;

    public function __construct(?AIProviderInterface $provider = null)
    {
        $this->provider = $provider ?? (new AIProviderResolverService())->resolve();
    }

    private array $replacements = [
        'você' => 'o estudante',
        'ônibus' => 'autocarro',
        'trem' => 'comboio',
        'fato' => 'facto',
        'objetivo' => 'objectivo',
    ];

    public function humanize(array $sections, string $profile = 'academic_humanized_pt_mz', bool $enabled = true): array
    {
        if (!$enabled) {
            return $sections;
        }

        foreach ($sections as $index => &$section) {
            $text = (string) ($section['content'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            $humanizedByAi = trim($this->provider->humanize($text, $profile));
            if ($humanizedByAi !== '') {
                $text = $humanizedByAi;
            }

            $text = str_ireplace(array_keys($this->replacements), array_values($this->replacements), $text);
            $text = preg_replace('/\s+/', ' ', $text) ?? $text;
            $text = preg_replace('/(\.\s+){2,}/', '. ', $text) ?? $text;
            $section['content'] = trim($text);
            $section['language_profile'] = $profile;
            $section['section_index'] = $index + 1;
        }

        unset($section);

        return $sections;
    }
}

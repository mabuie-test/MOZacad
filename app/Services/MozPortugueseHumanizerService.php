<?php

declare(strict_types=1);

namespace App\Services;

final class MozPortugueseHumanizerService
{
    public function __construct(private readonly AIProviderInterface $provider = new OpenAIProvider()) {}

    private array $replacements = [
        'você' => 'o estudante',
        'ônibus' => 'autocarro',
        'trem' => 'comboio',
        'fato' => 'facto',
        'objetivo' => 'objectivo',
    ];

    public function humanize(array $sections, string $profile = 'academic_humanized_pt_mz'): array
    {
        foreach ($sections as $index => &$section) {
            $text = (string) ($section['content'] ?? '');
            if (trim($text) === '') {
                continue;
            }

            $prompt = <<<PROMPT
Reescreve o texto abaixo em português de Moçambique com tom académico humano, natural e sem marcadores artificiais.
- Mantém rigor conceptual.
- Não inventes dados nem fontes.
- Evita repetição mecânica e frases robóticas.
- Preserva o sentido original.

Texto:
{$text}
PROMPT;

            $humanizedByAi = trim($this->provider->refine($prompt, ['locale' => 'pt_MZ', 'profile' => $profile]));
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

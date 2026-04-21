<?php

declare(strict_types=1);

namespace App\Services;

final class ExtraPricingService
{
    public function __construct(private readonly PricingConfig $config = new PricingConfig()) {}

    public function calculate(array $flags): array
    {
        $catalog = [
            'needs_institution_cover' => (float)$this->config->get('PRICING_EXTRA_CAPA_PERSONALIZADA', 200),
            'needs_bilingual_abstract' => (float)$this->config->get('PRICING_EXTRA_ABSTRACT_BILINGUE', 300),
            'premium_references' => (float)$this->config->get('PRICING_EXTRA_REFERENCIAS_PREMIUM', 250),
            'needs_methodology_review' => (float)$this->config->get('PRICING_EXTRA_REVISAO_METODOLOGICA', 500),
            'needs_humanized_revision' => (float)$this->config->get('PRICING_EXTRA_REVISAO_HUMANIZADA', 400),
            'needs_slides' => (float)$this->config->get('PRICING_EXTRA_APRESENTACAO_SLIDES', 800),
            'needs_defense_summary' => (float)$this->config->get('PRICING_EXTRA_RESUMO_DEFESA', 450),
        ];

        $selected = [];
        foreach ($catalog as $code => $price) {
            if (!empty($flags[$code])) {
                $selected[$code] = $price;
            }
        }
        return $selected;
    }
}

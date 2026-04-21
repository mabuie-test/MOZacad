<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Env;

final class PricingConfig
{
    public function basePriceBySlug(string $slug): float
    {
        $map = [
            'trabalho-pesquisa' => 'PRICING_BASE_TRABALHO_PESQUISA',
            'projecto-pesquisa' => 'PRICING_BASE_PROJECTO_PESQUISA',
            'monografia' => 'PRICING_BASE_MONOGRAFIA',
            'relatorio-estagio' => 'PRICING_BASE_RELATORIO_ESTAGIO',
            'artigo-cientifico' => 'PRICING_BASE_ARTIGO_CIENTIFICO',
            'resenha-critica' => 'PRICING_BASE_RESAENHA_CRITICA',
            'ensaio-academico' => 'PRICING_BASE_ENSAIO_ACADEMICO',
            'trabalho-campo' => 'PRICING_BASE_TRABALHO_CAMPO',
            'revisao-literatura' => 'PRICING_BASE_REVISAO_LITERATURA',
            'estudo-caso' => 'PRICING_BASE_ESTUDO_CASO',
            'proposta-tcc' => 'PRICING_BASE_PROPOSTA_TCC',
        ];

        return (float) Env::get($map[$slug] ?? 'PRICING_BASE_TRABALHO_PESQUISA', 800);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

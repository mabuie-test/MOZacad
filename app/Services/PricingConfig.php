<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Database;
use App\Helpers\Env;
use Throwable;

final class PricingConfig
{
    /** @var array<string,string>|null */
    private static ?array $dbRules = null;

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

        return (float) $this->get($map[$slug] ?? 'PRICING_BASE_TRABALHO_PESQUISA', 800);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $envValue = Env::get($key, $default);

        $useOverrides = filter_var((string) Env::get('PRICING_ENABLE_DB_OVERRIDES', true), FILTER_VALIDATE_BOOL);
        if (!$useOverrides) {
            return $envValue;
        }

        $dbRules = $this->loadDbRules();
        if (isset($dbRules[$key]) && $dbRules[$key] !== '') {
            return $dbRules[$key];
        }

        return $envValue;
    }

    /**
     * @return array<string,string>
     */
    private function loadDbRules(): array
    {
        if (self::$dbRules !== null) {
            return self::$dbRules;
        }

        try {
            $rows = Database::connect()->query('SELECT rule_code, rule_value FROM pricing_rules WHERE is_active = 1')->fetchAll();
            $mapped = [];
            foreach ($rows as $row) {
                $mapped[(string) $row['rule_code']] = (string) $row['rule_value'];
            }
            self::$dbRules = $mapped;
        } catch (Throwable) {
            self::$dbRules = [];
        }

        return self::$dbRules;
    }
}

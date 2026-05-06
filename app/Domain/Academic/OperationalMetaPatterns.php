<?php

declare(strict_types=1);

namespace App\Domain\Academic;

final class OperationalMetaPatterns
{
    /** @return list<string> */
    public static function blockedRegexPatterns(): array
    {
        return [
            '/\{\s*"[^\"]+"\s*:/u',
            '/\bsection_title\b|\bsection_code\b|\btext\s*:/iu',
            '/instru[cç](?:[aã]o|[õo]es)\s+do\s+pipeline|nota[s]?\s+de\s+pipeline|com base nas regras de refinamento|marcadores operacionais/u',
            '/(?:^|\s)(?:payload|debug)\s*:/iu',
            '/\b\-\-\-\b/u',
            '/\[\[todo|placeholder|indice placeholder|lorem ipsum/u',
            '/aqui est[aá] a sec[cç][aã]o/u',
            '/\brefinada\b/u',
            '/coment[aá]rio de edi[cç][aã]o|meta-editorial|linguagem meta-editorial/u',
            '/[íi]ndice autom[aá]tico \(actualiz[aá]vel no editor de texto\)/u',
        ];
    }

    /** @return list<string> */
    public static function forbiddenSnippets(): array
    {
        return [
            'aqui está a secção',
            'refinada',
            'comentário de edição',
            'instruções do pipeline',
            'linguagem meta-editorial',
            'índice automático (actualizável no editor de texto).',
        ];
    }
}

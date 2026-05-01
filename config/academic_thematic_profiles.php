<?php

declare(strict_types=1);

return [
    [
        'id' => 'colonial_education_history_mozambique',
        'criteria' => [
            ['historic', ['historia', 'historico', 'historica', 'hist']],
            ['colonial_education', ['educacao colonial', 'ensino colonial', 'escola colonial']],
            ['country', ['mocambique', 'mozambique']],
        ],
        'sections' => [
            'Resumo',
            'Introdução',
            'Enquadramento histórico da educação colonial em Moçambique',
            'Estado colonial, missões religiosas e política assimilacionista',
            'Currículo, língua e formação para o trabalho',
            'Desigualdades de acesso e efeitos sociais',
            'Legados da educação colonial no pós-independência',
            'Conclusão',
            'Referências',
        ],
    ],
    [
        'id' => 'general_pedagogy',
        'criteria' => [
            ['education', ['pedagogia', 'educacao', 'ensino']],
            ['general_focus', ['geral', 'fundamentos', 'teorias']],
        ],
        'sections' => [
            'Resumo',
            'Introdução',
            'Conceitos fundamentais da pedagogia',
            'Principais teorias pedagógicas',
            'Práticas didáticas e avaliação',
            'Desafios contemporâneos da educação',
            'Conclusão',
            'Referências',
        ],
        'word_ranges_by_code' => [
            'introducao' => [220, 420],
            'conclusao' => [180, 340],
        ],
    ],
    [
        'id' => 'public_administration',
        'criteria' => [
            ['domain', ['administracao publica', 'gestao publica', 'setor publico']],
            ['governance', ['politicas publicas', 'governanca', 'servico publico']],
        ],
        'sections' => [
            'Resumo',
            'Introdução',
            'Fundamentos da administração pública',
            'Modelos de gestão e governança no setor público',
            'Planeamento e implementação de políticas públicas',
            'Transparência, accountability e controlo social',
            'Conclusão',
            'Referências',
        ],
    ],
    [
        'id' => 'public_health',
        'criteria' => [
            ['domain', ['saude publica', 'salud publica', 'health public']],
            ['population', ['epidemiologia', 'promocao da saude', 'sistema de saude']],
        ],
        'sections' => [
            'Resumo',
            'Introdução',
            'Conceitos e evolução da saúde pública',
            'Determinantes sociais e perfil epidemiológico',
            'Organização dos sistemas de saúde e atenção primária',
            'Estratégias de prevenção e promoção da saúde',
            'Conclusão',
            'Referências',
        ],
    ],
];

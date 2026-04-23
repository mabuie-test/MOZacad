<div class="page-header"><div><h1 class="section-title h3">Como funciona</h1><p>Pipeline completo desde o pedido até ao documento final.</p></div></div>
<div class="row g-3 mb-3"><?php foreach ([
['1','Configuração do pedido','Escolha instituição, nível, tipo de trabalho, prazo e requisitos.'],
['2','Cálculo e validação','O pricing engine aplica regras, extras e descontos elegíveis.'],
['3','Pagamento seguro','Início pelo M-Pesa com estado pendente/processando/pago.'],
['4','Produção e revisão','Processamento técnico com intervenção humana quando necessário.'],
['5','Entrega e iteração','Download por versão e canal de pedido de revisão.'],
] as $step): ?><div class="col-md-6"><div class="card p-4 h-100"><span class="badge text-bg-primary mb-2"><?= $step[0] ?></span><h2 class="h5"><?= $step[1] ?></h2><p class="text-secondary mb-0"><?= $step[2] ?></p></div></div><?php endforeach; ?></div>
<div class="card p-4"><strong>Nota operacional:</strong> cada etapa tem estado explícito, garantindo previsibilidade para utilizador e equipa administrativa.</div>

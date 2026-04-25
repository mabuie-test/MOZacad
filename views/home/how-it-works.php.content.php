<div class="page-header"><div class="page-intro"><h1 class="section-title h3">Como funciona</h1><p>Pipeline completo para transformar um briefing inicial num documento final pronto para defesa.</p></div></div>
<div class="row g-3 mb-3 public-grid"><?php foreach ([
['1','Onboarding do pedido','Escolha instituição, curso, disciplina, tipo de trabalho, escopo e prazo com validação guiada.'],
['2','Contextualização académica','Defina problema, objectivos, hipótese, palavras-chave e briefing complementar.'],
['3','Pricing e aprovação financeira','Regras oficiais de preço + extras + descontos/cupões aplicáveis.'],
['4','Execução com estados operacionais','Acompanhe pending_payment, queued, under_human_review, ready e revisão.'],
['5','Entrega e iteração final','Faça downloads por versão e, quando necessário, solicite ajustes.'],
] as $step): ?><div class="col-md-6"><div class="card p-4 h-100"><span class="badge text-bg-primary mb-2">Etapa <?= $step[0] ?></span><h2 class="h5"><?= $step[1] ?></h2><p class="text-secondary mb-0"><?= $step[2] ?></p></div></div><?php endforeach; ?></div>
<div class="card p-4 cta-card"><strong>Compromisso operacional:</strong> cada etapa possui estado explícito para reduzir incerteza e acelerar decisão.</div>

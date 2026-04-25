<div class="page-header"><div class="page-intro"><h1 class="section-title h3">Perguntas frequentes</h1><p>Respostas objectivas para as dúvidas mais comuns durante o ciclo do pedido.</p></div></div>
<div class="accordion" id="faqAcc"><?php foreach ([
['Posso acompanhar o estado em tempo real?','Sim. Dashboard e detalhe do pedido mostram estados operacionais e próximo passo recomendado.'],
['Quando devo pedir revisão?','Quando o documento entregue precisar de ajuste técnico, estrutural ou editorial. O pedido de revisão fica registado no ciclo do pedido.'],
['Como confirmar pagamento?','As áreas de pedidos, invoices e pagamentos exibem os estados pendente, processamento, pago ou falhado.'],
['Recebo documento final por versão?','Sim. Cada entrega fica em Downloads com versão e estado para controlo completo.'],
['Preciso de anexar material obrigatório?','Sempre que possível. Anexos melhoram precisão e reduzem retrabalho na execução.'],
] as $i => $faq): ?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= $faq[0] ?></button></h2><div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= $faq[1] ?></div></div></div><?php endforeach; ?></div>

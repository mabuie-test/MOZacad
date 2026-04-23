<div class="page-header"><div><h1 class="section-title h3">Perguntas frequentes</h1><p>Respostas directas para as dúvidas mais comuns.</p></div></div>
<div class="accordion" id="faqAcc"><?php foreach ([
['Posso acompanhar o estado do meu pedido?','Sim. O dashboard e a área de pedidos mostram estados operacionais em tempo real.'],
['Quando posso solicitar revisão?','Após entrega ou quando o estado indicar possibilidade de iteração.'],
['Como sei se o pagamento foi confirmado?','A área de pagamento e invoices reflecte os estados: pendente, processamento, pago ou falhado.'],
['Os documentos ficam disponíveis por versão?','Sim. Downloads exibem versão e estado de cada documento entregue.'],
] as $i => $faq): ?><div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?= $i ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= $faq[0] ?></button></h2><div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i ? '' : 'show' ?>" data-bs-parent="#faqAcc"><div class="accordion-body"><?= $faq[1] ?></div></div></div><?php endforeach; ?></div>

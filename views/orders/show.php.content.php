<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div>
    <h1 class="section-title h3">Pedido #<?= (int) $order['id'] ?></h1>
    <p><?= htmlspecialchars((string) $order['title_or_theme']) ?></p>
  </div>
  <div class="quick-actions">
    <?php if (($order['status'] ?? '') === 'pending_payment'): ?>
      <a href="/orders/<?= (int) $order['id'] ?>/pay" class="btn btn-primary">Pagar pedido</a>
    <?php endif; ?>
    <a href="/orders" class="btn btn-outline-secondary">Voltar à lista</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8 d-grid gap-3">
    <div class="card p-4">
      <h2 class="h5">Resumo do pedido</h2>
      <div class="row g-3 mt-1">
        <div class="col-md-6"><div class="status-card"><small>Status actual</small><div class="mt-1"><?= $badge((string) $order['status']) ?></div><div class="small text-secondary mt-1"><?= htmlspecialchars($statusHint((string) $order['status'])) ?></div></div></div>
        <div class="col-md-6"><div class="status-card"><small>Preço final</small><div class="fw-bold mt-1"><?= $formatMoney($order['final_price'] ?? 0) ?></div><div class="small text-secondary">Prazo: <?= htmlspecialchars((string) ($order['deadline_date'] ?? '-')) ?></div></div></div>
        <div class="col-12"><div class="status-card"><small>Notas operacionais</small><div class="mt-1"><?= htmlspecialchars((string) ($order['notes'] ?? 'Sem notas adicionais.')) ?></div></div></div>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="h5">Timeline de execução</h2>
      <div class="timeline mt-3">
        <div class="timeline-item"><span class="timeline-dot"></span><div><strong>Pedido registado</strong><p class="small text-secondary mb-0">ID #<?= (int) $order['id'] ?> com tema e briefing submetidos.</p></div></div>
        <div class="timeline-item"><span class="timeline-dot"></span><div><strong>Financeiro</strong><p class="small text-secondary mb-0">Invoice: <?= $badge((string) ($invoice['status'] ?? 'pending')) ?> · Pagamento: <?= $badge((string) ($payment['status'] ?? 'pending')) ?></p></div></div>
        <div class="timeline-item"><span class="timeline-dot"></span><div><strong>Entrega</strong><p class="small text-secondary mb-0">Documentos disponíveis: <?= count($documents ?? []) ?>.</p></div></div>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="h5">Documentos e anexos</h2>
      <?php if (empty($documents) && empty($attachments)): ?>
        <?= $emptyState('Sem ficheiros associados', 'Quando a produção avançar, os documentos serão listados aqui.') ?>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($documents as $d): ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
              <span>Documento entregue · versão <?= (int) $d['version'] ?></span>
              <a href="/downloads/<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline-primary">Download</a>
            </li>
          <?php endforeach; ?>
          <?php foreach ($attachments as $a): ?>
            <li class="list-group-item px-0">Anexo enviado: <?= htmlspecialchars((string) $a['file_name']) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="card p-4">
      <h2 class="h5">Histórico de pagamentos do pedido</h2>
      <?php if (empty($paymentHistory)): ?>
        <?= $emptyState('Sem tentativas de pagamento registadas', 'Quando um pagamento for iniciado, o histórico aparece aqui.') ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>ID</th><th>Método</th><th>Status</th><th>Referência</th></tr></thead>
            <tbody>
            <?php foreach ($paymentHistory as $item): ?>
              <tr>
                <td>#<?= (int) $item['id'] ?></td>
                <td><?= htmlspecialchars((string) ($item['method'] ?? '-')) ?></td>
                <td><?= $badge((string) ($item['status'] ?? 'pending')) ?></td>
                <td><code><?= htmlspecialchars((string) ($item['external_reference'] ?? '-')) ?></code></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4 d-grid gap-3">
    <div class="card p-4">
      <h2 class="h6">Próxima acção recomendada</h2>
      <?php if (($order['status'] ?? '') === 'pending_payment'): ?>
        <p class="text-secondary">Para iniciar a execução, conclua o pagamento.</p>
        <a href="/orders/<?= (int) $order['id'] ?>/pay" class="btn btn-primary w-100">Ir para pagamento</a>
      <?php elseif (($order['status'] ?? '') === 'ready'): ?>
        <p class="text-secondary">O pedido está pronto. Verifique os downloads.</p>
        <a href="/downloads" class="btn btn-primary w-100">Abrir downloads</a>
      <?php else: ?>
        <p class="text-secondary">Acompanhe o estado e use revisão quando necessário.</p>
        <a href="/orders" class="btn btn-outline-primary w-100">Ver outros pedidos</a>
      <?php endif; ?>
    </div>

    <div class="card p-4">
      <h2 class="h6">Solicitar revisão</h2>
      <form method="post" action="/orders/<?= (int) $order['id'] ?>/revision-request" data-revision-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <label class="form-label">Motivo da revisão</label>
        <textarea name="reason" class="form-control mb-2" rows="4" placeholder="Descreva os ajustes desejados com objectividade" required></textarea>
        <button type="submit" class="btn btn-outline-primary w-100">Enviar pedido de revisão</button>
        <p class="small mt-2 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div class="page-intro">
    <h1 class="section-title h3">Pedido #<?= (int) $order['id'] ?></h1>
    <p><?= htmlspecialchars((string) $order['title_or_theme']) ?> · acompanhe estado, finanças, documentos e revisão num único centro operacional.</p>
  </div>
  <div class="quick-actions">
    <?php if (($order['status'] ?? '') === 'pending_payment'): ?><a href="/orders/<?= (int) $order['id'] ?>/pay" class="btn btn-primary">Pagar agora</a><?php endif; ?>
    <a href="/orders" class="btn btn-outline-secondary">Voltar à lista</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8 d-grid gap-3">
    <div class="card p-4">
      <h2 class="h5">Timeline do pedido</h2>
      <div class="timeline mt-3">
        <?php foreach ([
          'pending_payment' => 'Pagamento pendente para iniciar execução.',
          'queued' => 'Pedido em fila de produção técnica.',
          'under_human_review' => 'Documento em revisão humana especializada.',
          'ready' => 'Documento final disponível para download.',
          'revision_requested' => 'Revisão solicitada pelo utilizador.',
          'returned_for_revision' => 'Documento devolvido para nova iteração.',
          'approved' => 'Ciclo validado e encerrado com aprovação.'
        ] as $status => $description): ?>
          <div class="timeline-item <?= (($order['status'] ?? '') === $status) ? 'is-active' : '' ?>">
            <span class="timeline-dot"></span>
            <div>
              <strong><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></strong>
              <p class="small text-secondary mb-0"><?= htmlspecialchars($description) ?></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="h5">Bloco financeiro</h2>
      <div class="row g-2">
        <div class="col-md-4"><div class="status-card"><small>Status do pedido</small><div><?= $badge((string) $order['status']) ?></div></div></div>
        <div class="col-md-4"><div class="status-card"><small>Invoice</small><div><?= $badge((string) ($invoice['status'] ?? 'pending')) ?></div></div></div>
        <div class="col-md-4"><div class="status-card"><small>Pagamento</small><div><?= $badge((string) ($payment['status'] ?? 'pending')) ?></div></div></div>
      </div>
      <div class="table-responsive mt-3">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>ID</th><th>Status</th><th>Método</th><th>Referência</th><th>Data</th></tr></thead>
          <tbody>
          <?php if (empty($paymentHistory)): ?>
            <tr><td colspan="5" class="text-secondary">Sem tentativas de pagamento ainda.</td></tr>
          <?php else: foreach ($paymentHistory as $item): ?>
            <tr>
              <td>#<?= (int) $item['id'] ?></td>
              <td><?= $badge((string) ($item['status'] ?? 'pending')) ?></td>
              <td><?= htmlspecialchars((string) ($item['method'] ?? '-')) ?></td>
              <td><code><?= htmlspecialchars((string) ($item['external_reference'] ?? '-')) ?></code></td>
              <td><?= htmlspecialchars((string) ($item['created_at'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card p-4">
      <h2 class="h5">Documentos e revisão</h2>
      <div class="row g-3">
        <div class="col-md-7">
          <h3 class="h6">Documentos gerados</h3>
          <?php if (empty($documents)): ?>
            <?= $emptyState('Sem documentos disponíveis', 'Assim que o pedido estiver ready, os ficheiros surgirão aqui.') ?>
          <?php else: ?>
            <ul class="list-group list-group-flush">
              <?php foreach ($documents as $d): ?>
                <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                  <span>Versão <?= (int) $d['version'] ?> <small class="muted-meta"><?= htmlspecialchars((string) ($d['created_at'] ?? '')) ?></small></span>
                  <a href="/downloads/<?= (int) $d['id'] ?>" class="btn btn-sm btn-outline-primary">Download</a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
        <div class="col-md-5">
          <h3 class="h6">Estado da revisão</h3>
          <?php if (empty($revision)): ?>
            <?= $emptyState('Nenhuma revisão activa', 'Pode solicitar revisão sempre que necessário.') ?>
          <?php else: ?>
            <div class="status-card">
              <div><?= $badge((string) ($revision['status'] ?? 'requested')) ?></div>
              <p class="small my-2"><?= htmlspecialchars((string) ($revision['reason'] ?? '-')) ?></p>
              <small class="text-secondary">Última atualização: <?= htmlspecialchars((string) ($revision['updated_at'] ?? $revision['created_at'] ?? '-')) ?></small>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 d-grid gap-3">
    <div class="card p-4 cta-card">
      <h2 class="h6">Próxima acção recomendada</h2>
      <?php if (($order['status'] ?? '') === 'pending_payment'): ?>
        <p>Conclua o pagamento para desbloquear produção.</p>
        <a href="/orders/<?= (int) $order['id'] ?>/pay" class="btn btn-primary w-100">Ir para pagamento</a>
      <?php elseif (($order['status'] ?? '') === 'ready'): ?>
        <p>Documento pronto. Faça download agora.</p>
        <a href="/downloads" class="btn btn-primary w-100">Abrir downloads</a>
      <?php else: ?>
        <p>Continue a monitorar o estado e use revisão se necessário.</p>
        <a href="/orders" class="btn btn-outline-primary w-100">Ver pedidos</a>
      <?php endif; ?>
    </div>

    <div class="card p-4">
      <h2 class="h6">Solicitar revisão</h2>
      <form method="post" action="/orders/<?= (int) $order['id'] ?>/revision-request" data-revision-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <label class="form-label">Motivo</label>
        <textarea name="reason" class="form-control mb-2" rows="4" required placeholder="Detalhe os ajustes pretendidos"></textarea>
        <button type="submit" class="btn btn-outline-primary w-100">Enviar pedido de revisão</button>
        <p class="small mt-2 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</div>

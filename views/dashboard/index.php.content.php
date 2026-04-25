<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div class="page-intro">
    <h1 class="section-title h3">Dashboard</h1>
    <p>O seu cockpit académico: pendências prioritárias, saúde financeira e documentos prontos em tempo real.</p>
  </div>
  <div class="quick-actions">
    <a href="/orders/create" class="btn btn-primary">Novo pedido</a>
    <a href="/orders" class="btn btn-outline-secondary">Pedidos</a>
  </div>
</div>

<div class="kbd-list mb-4">
  <div class="metric"><small>Total de pedidos</small><div class="value"><?= (int) ($summary['orders_total'] ?? 0) ?></div></div>
  <div class="metric"><small>Pedidos activos</small><div class="value"><?= (int) ($summary['orders_paid_or_queued'] ?? 0) ?></div></div>
  <div class="metric"><small>Pagamentos pendentes</small><div class="value"><?= (int) ($summary['pending_payments'] ?? 0) ?></div></div>
  <div class="metric"><small>Revisões em curso</small><div class="value"><?= (int) ($summary['revision_requests'] ?? 0) ?></div></div>
  <div class="metric"><small>Downloads prontos</small><div class="value"><?= (int) ($summary['ready_to_download'] ?? 0) ?></div></div>
  <div class="metric"><small>Facturas abertas</small><div class="value"><?= (int) ($summary['open_invoices'] ?? 0) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8 d-grid gap-3">
    <div class="card p-3">
      <h2 class="h5">Pendências prioritárias</h2>
      <div class="row g-2">
        <div class="col-md-4"><div class="status-card h-100"><strong>Pagamentos</strong><p class="small mb-2"><?= count($pendingActions['needs_payment'] ?? []) ?> pedido(s) aguardam confirmação.</p><a href="/orders" class="btn btn-sm btn-outline-primary">Regularizar</a></div></div>
        <div class="col-md-4"><div class="status-card h-100"><strong>Revisões</strong><p class="small mb-2"><?= count($pendingActions['under_review'] ?? []) ?> pedido(s) em análise ou reiteração.</p><a href="/orders" class="btn btn-sm btn-outline-primary">Acompanhar</a></div></div>
        <div class="col-md-4"><div class="status-card h-100"><strong>Downloads</strong><p class="small mb-2"><?= count($pendingActions['ready_for_download'] ?? []) ?> documento(s) já disponíveis.</p><a href="/downloads" class="btn btn-sm btn-outline-primary">Descarregar</a></div></div>
      </div>
    </div>

    <div class="card p-3">
      <h2 class="h5">Pedidos recentes</h2>
      <?php if (empty($orders)): ?>
        <?= $emptyState('Sem pedidos ainda', 'Crie o primeiro pedido para iniciar acompanhamento operacional.', '/orders/create', 'Criar pedido') ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>ID</th><th>Tema</th><th>Status</th><th>Acção</th></tr></thead>
            <tbody>
            <?php foreach (($orders ?? []) as $order): ?>
              <tr>
                <td>#<?= (int) $order['id'] ?></td>
                <td><?= htmlspecialchars((string) $order['title_or_theme']) ?><span class="muted-meta">Prazo: <?= htmlspecialchars((string) ($order['deadline_date'] ?? '-')) ?></span></td>
                <td><?= $badge((string) $order['status']) ?></td>
                <td><a href="/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4 d-grid gap-3">
    <div class="card p-3">
      <h2 class="h6">Facturas recentes</h2>
      <?php if (empty($invoices)): ?><?= $emptyState('Sem facturas', 'Facturas emitidas aparecerão aqui.') ?><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($invoices as $invoice): ?><li class="list-group-item px-0 d-flex justify-content-between"><span>#<?= (int) $invoice['order_id'] ?></span><?= $badge((string) ($invoice['status'] ?? 'pending')) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
    <div class="card p-3">
      <h2 class="h6">Pagamentos recentes</h2>
      <?php if (empty($payments)): ?><?= $emptyState('Sem pagamentos', 'Ainda não há movimentos recentes.') ?><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($payments as $payment): ?><li class="list-group-item px-0 d-flex justify-content-between"><span>#<?= (int) $payment['order_id'] ?> · <?= htmlspecialchars((string) ($payment['method'] ?? '-')) ?></span><?= $badge((string) $payment['status']) ?></li><?php endforeach; ?></ul><?php endif; ?>
    </div>
  </div>
</div>

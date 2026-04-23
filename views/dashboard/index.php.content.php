<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div>
    <h1 class="section-title h3">Dashboard</h1>
    <p>Visão consolidada do ciclo académico, financeiro e documental.</p>
  </div>
  <div class="quick-actions">
    <a href="/orders/create" class="btn btn-primary">Novo pedido</a>
    <a href="/orders" class="btn btn-outline-secondary">Ver pedidos</a>
  </div>
</div>

<div class="kbd-list mb-4">
  <div class="metric"><small>Total de pedidos</small><div class="value"><?= (int) ($summary['orders_total'] ?? 0) ?></div></div>
  <div class="metric"><small>Pedidos activos</small><div class="value"><?= (int) ($summary['orders_paid_or_queued'] ?? 0) ?></div></div>
  <div class="metric"><small>Pagamentos pendentes</small><div class="value"><?= (int) ($summary['pending_payments'] ?? 0) ?></div></div>
  <div class="metric"><small>Revisões em aberto</small><div class="value"><?= (int) ($summary['revision_requests'] ?? 0) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card p-3 h-100">
      <h2 class="h5">Pedidos recentes</h2>
      <?php if (empty($orders)): ?>
        <?= $emptyState('Sem pedidos ainda', 'Crie o primeiro pedido para activar o acompanhamento do fluxo.', '/orders/create', 'Criar pedido') ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>ID</th><th>Tema</th><th>Status</th><th>Acção</th></tr></thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr>
                  <td>#<?= (int) $order['id'] ?></td>
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars((string) $order['title_or_theme']) ?></div>
                    <small class="text-secondary"><?= htmlspecialchars((string) ($order['institution_name'] ?? '')) ?></small>
                  </td>
                  <td><?= $badge((string) $order['status']) ?><div class="small text-secondary mt-1"><?= htmlspecialchars($statusHint((string) $order['status'])) ?></div></td>
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
      <h2 class="h6">Pagamentos recentes</h2>
      <?php if (empty($payments)): ?>
        <?= $emptyState('Sem pagamentos recentes', 'Quando iniciar um pagamento, o estado aparecerá aqui.') ?>
      <?php else: ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($payments as $payment): ?>
            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
              <span>#<?= (int) $payment['order_id'] ?> · <?= htmlspecialchars((string) $payment['method']) ?></span>
              <?= $badge((string) $payment['status']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <div class="card p-3">
      <h2 class="h6">Próximos passos</h2>
      <div class="status-card mb-2"><strong>1.</strong> Se houver pedidos em <em>pending_payment</em>, conclua em “Pagar”.</div>
      <div class="status-card mb-2"><strong>2.</strong> Em estado <em>ready</em>, aceda a “Downloads”.</div>
      <div class="status-card"><strong>3.</strong> Se necessário, use “Solicitar revisão” no detalhe do pedido.</div>
    </div>
  </div>
</div>

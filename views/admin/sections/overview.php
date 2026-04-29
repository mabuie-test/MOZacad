<div class="kbd-list mb-3">
  <div class="metric"><small>Pedidos pendentes pagamento</small><div class="value"><?= (int) ($overview['orders_pending_payment'] ?? 0) ?></div></div>
  <div class="metric"><small>Pedidos em revisão</small><div class="value"><?= (int) ($overview['orders_under_review'] ?? 0) ?></div></div>
  <div class="metric"><small>Pagamentos com risco</small><div class="value"><?= (int) ($overview['payments_failed'] ?? 0) ?></div></div>
  <div class="metric"><small>Fila humana sem atribuição</small><div class="value"><?= (int) ($overview['queue_unassigned'] ?? 0) ?></div></div>
  <div class="metric"><small>Exceções ativas</small><div class="value"><?= (int) ($overview['exceptions_active'] ?? 0) ?></div></div>
  <div class="metric"><small>Bloqueios por risco/compliance</small><div class="value"><?= (int) ($overview['exceptions_blocking_delivery'] ?? 0) ?></div></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card p-3 h-100"><h2 class="h6">Ações rápidas</h2><div class="d-grid gap-2"><a class="btn btn-sm btn-outline-primary" href="/admin/human-review">Abrir fila humana</a><a class="btn btn-sm btn-outline-primary" href="/admin/orders?order_status=pending_payment">Ver pedidos pendentes</a><a class="btn btn-sm btn-outline-primary" href="/admin/pricing">Revisar pricing</a></div></div></div>
  <div class="col-lg-8"><div class="card p-3 h-100"><h2 class="h6">Sinais de risco operacional</h2><ul class="text-secondary mb-0"><li>Pagamentos falhados/cancelados exigem acção de suporte.</li><li>Fila de revisão humana sem revisor aumenta tempo de entrega.</li><li>Pedidos em revisão prolongada podem impactar satisfação e SLA.</li></ul></div></div>
</div>

<div class="card p-3">
  <h2 class="h5">Últimos pedidos críticos</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Tema</th><th>Utilizador</th><th>Status</th><th>Preço</th></tr></thead>
      <tbody>
      <?php foreach (array_slice((array) ($orders ?? []), 0, 12) as $order): ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td><?= htmlspecialchars((string) ($order['title_or_theme'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($order['user_email'] ?? '-')) ?></td>
          <td><?= $badge((string) ($order['status'] ?? 'draft')) ?></td>
          <td><?= $formatMoney($order['final_price'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

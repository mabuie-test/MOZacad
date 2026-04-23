<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div>
    <h1 class="section-title h3">Pedidos</h1>
    <p>Gestão completa do lifecycle: briefing, pagamento, revisão e entrega.</p>
  </div>
  <a href="/orders/create" class="btn btn-primary">Novo pedido</a>
</div>

<div class="card p-3">
  <?php if (empty($orders)): ?>
    <?= $emptyState('Ainda não existem pedidos', 'Crie o seu primeiro pedido académico para iniciar o fluxo.', '/orders/create', 'Criar pedido') ?>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>#</th><th>Tema e contexto</th><th>Preço</th><th>Status</th><th>Acções</th></tr></thead>
        <tbody>
          <?php foreach (($orders ?? []) as $order): ?>
            <tr>
              <td>#<?= (int) $order['id'] ?></td>
              <td>
                <div class="fw-semibold"><?= htmlspecialchars((string) $order['title_or_theme']) ?></div>
                <small class="text-secondary"><?= htmlspecialchars((string) ($order['work_type_name'] ?? '-')) ?> · <?= htmlspecialchars((string) ($order['institution_name'] ?? '-')) ?></small>
              </td>
              <td><?= $formatMoney($order['final_price'] ?? 0) ?></td>
              <td><?= $badge((string) $order['status']) ?><div class="small text-secondary mt-1"><?= htmlspecialchars($statusHint((string) $order['status'])) ?></div></td>
              <td class="d-flex gap-2"><a href="/orders/<?= (int) $order['id'] ?>" class="btn btn-sm btn-outline-primary">Detalhe</a><a href="/orders/<?= (int) $order['id'] ?>/pay" class="btn btn-sm btn-outline-success">Pagar</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

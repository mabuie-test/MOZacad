<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header"><div><h1 class="section-title h3">Facturas</h1><p>Controlo financeiro por pedido e estado de cobrança.</p></div></div>

<div class="card p-3">
  <?php if (empty($invoices)): ?>
    <?= $emptyState('Sem facturas emitidas', 'Quando houver pedidos financeiros, a listagem aparecerá aqui.') ?>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>ID</th><th>Número</th><th>Valor</th><th>Status</th><th>Emitida em</th></tr></thead>
        <tbody>
          <?php foreach (($invoices ?? []) as $inv): ?>
            <tr>
              <td>#<?= (int) $inv['id'] ?></td>
              <td><?= htmlspecialchars((string) $inv['invoice_number']) ?></td>
              <td><?= $formatMoney($inv['amount'] ?? 0) ?></td>
              <td><?= $badge((string) $inv['status']) ?></td>
              <td><?= htmlspecialchars((string) ($inv['issued_at'] ?? $inv['created_at'] ?? '-')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

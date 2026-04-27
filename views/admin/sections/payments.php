<div class="card p-3 mb-3">
  <h2 class="h5">Filtro de pagamentos</h2>
  <form method="get" action="/admin/payments" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Status</label><select name="payment_status" class="form-select"><option value="">Todos</option><?php foreach (['pending','processing','pending_confirmation','paid','failed','cancelled','expired'] as $status): ?><option value="<?= $status ?>" <?= (($paymentStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>
<div class="card p-3">
  <h2 class="h5">Pagamentos</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead>
        <tr>
          <th>ID</th>
          <th>Pedido</th>
          <th>Utilizador</th>
          <th>Status</th>
          <th>Provider</th>
          <th>Ref.</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($payments ?? []) as $p): ?>
          <?php $status = (string) ($p['status'] ?? 'pending'); ?>
          <tr>
            <td>#<?= (int) $p['id'] ?></td>
            <td>#<?= (int) $p['order_id'] ?></td>
            <td><?= htmlspecialchars((string) ($p['user_email'] ?? '-')) ?></td>
            <td><?= $badge($status) ?></td>
            <td><?= htmlspecialchars((string) ($p['provider_status'] ?? '-')) ?></td>
            <td><code><?= htmlspecialchars((string) ($p['external_reference'] ?? '-')) ?></code></td>
            <td><?= htmlspecialchars((string) ($p['created_at'] ?? '-')) ?></td>
            <td>
              <?php if ($status !== 'paid'): ?>
                <form method="post" action="/admin/payments/<?= (int) $p['id'] ?>/confirm-manual" class="d-flex gap-1">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                  <input type="hidden" name="provider_status" value="SUCCESSFUL">
                  <button class="btn btn-sm btn-success" onclick="return confirm('Confirmar manualmente este pagamento como pago?');">
                    Confirmar manual
                  </button>
                </form>
              <?php else: ?>
                <span class="text-muted small">Já pago</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

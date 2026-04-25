<div class="card p-3 mb-3">
  <h2 class="h5">Filtro de pedidos</h2>
  <form method="get" action="/admin/orders" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Status</label><select name="order_status" class="form-select"><option value="">Todos</option><?php foreach (['pending_payment','queued','under_human_review','ready','revision_requested','returned_for_revision','approved'] as $status): ?><option value="<?= $status ?>" <?= (($orderStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>
<div class="card p-3"><h2 class="h5">Pedidos</h2><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Utilizador</th><th>Tema</th><th>Status</th><th>Tipo</th><th>Preço</th></tr></thead><tbody><?php foreach (($orders ?? []) as $o): ?><tr><td>#<?= (int) $o['id'] ?></td><td><?= htmlspecialchars((string) ($o['user_email'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($o['title_or_theme'] ?? '-')) ?></td><td><?= $badge((string) ($o['status'] ?? 'draft')) ?></td><td><?= htmlspecialchars((string) ($o['work_type_name'] ?? '-')) ?></td><td><?= $formatMoney($o['final_price'] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></div></div>

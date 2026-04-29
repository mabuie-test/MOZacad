<div class="card p-3 mb-3">
  <h2 class="h5">Filtro de pedidos</h2>
  <form method="get" action="/admin/orders" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">Status</label><select name="order_status" class="form-select"><option value="">Todos</option><?php foreach (['pending_payment','queued','paused_admin','under_human_review','delivery_blocked','ready','revision_requested','returned_for_revision','approved'] as $status): ?><option value="<?= $status ?>" <?= (($orderStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><label class="form-label">Risco</label><select name="risk" class="form-select"><option value="">Todos</option><option value="high" <?= (($riskFilter ?? '')==='high')?'selected':'' ?>>Alta prioridade</option><option value="normal" <?= (($riskFilter ?? '')==='normal')?'selected':'' ?>>Normal</option></select></div>
    <div class="col-md-2"><label class="form-label">SLA</label><select name="delay" class="form-select"><option value="">Todos</option><option value="late" <?= (($delayFilter ?? '')==='late')?'selected':'' ?>>Atrasado</option><option value="at_risk" <?= (($delayFilter ?? '')==='at_risk')?'selected':'' ?>>Em risco</option><option value="on_track" <?= (($delayFilter ?? '')==='on_track')?'selected':'' ?>>No prazo</option></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>

<div class="card p-3 mb-3"><h2 class="h5">Pedidos</h2><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>ID</th><th>Utilizador</th><th>Tema</th><th>Status</th><th>SLA</th><th>Prioridade</th><th>Tipo</th><th>Preço</th><th>Ações</th></tr></thead><tbody><?php foreach (($orders ?? []) as $o): ?><tr><td>#<?= (int) $o['id'] ?></td><td><?= htmlspecialchars((string) ($o['user_email'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($o['title_or_theme'] ?? '-')) ?></td><td><?= $badge((string) ($o['status'] ?? 'draft')) ?></td><td><?= htmlspecialchars((string) ($o['sla_state'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($o['admin_priority'] ?? 'normal')) ?></td><td><?= htmlspecialchars((string) ($o['work_type_name'] ?? '-')) ?></td><td><?= $formatMoney($o['final_price'] ?? 0) ?></td><td>
<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/pause" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><button class="btn btn-sm btn-outline-secondary">Pausar</button></form>
<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/resume" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><button class="btn btn-sm btn-outline-success">Retomar</button></form>
<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/escalate" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><input type="hidden" name="confirm" value="1"><input type="text" name="reason" required placeholder="Motivo" class="form-control form-control-sm d-inline" style="width:120px"><button class="btn btn-sm btn-outline-warning">Escalar</button></form>

<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/payment-dispute" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><input type="hidden" name="confirm" value="1"><input type="text" name="reason" required placeholder="Motivo disputa" class="form-control form-control-sm d-inline" style="width:120px"><button class="btn btn-sm btn-outline-danger">Disputa</button></form>
<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/payment-refund" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><input type="hidden" name="confirm" value="1"><input type="text" name="reason" required placeholder="Motivo reembolso" class="form-control form-control-sm d-inline" style="width:130px"><button class="btn btn-sm btn-outline-info">Reembolso</button></form>
<form method="post" action="/admin/orders/<?= (int)$o['id'] ?>/payment-cancel" class="d-inline">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars((string)($csrfToken ?? '')) ?>"><input type="hidden" name="confirm" value="1"><input type="text" name="reason" required placeholder="Motivo cancel." class="form-control form-control-sm d-inline" style="width:120px"><button class="btn btn-sm btn-outline-dark">Cancelar pós-pag.</button></form>
</td></tr><?php endforeach; ?></tbody></table></div></div>

<div class="card p-3">
  <h2 class="h5">Timeline administrativa auditável</h2>
  <form method="get" action="/admin/orders" class="row g-2 align-items-end mb-2">
    <div class="col-md-3"><label class="form-label">Pedido</label><input class="form-control" type="number" name="order_id" min="1" value="<?= (int)($selectedOrderId ?? 0) ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Carregar</button></div>
  </form>
  <ul class="list-group"><?php foreach (($orderAuditTrail ?? []) as $entry): ?><li class="list-group-item"><strong><?= htmlspecialchars((string)($entry['action'] ?? '')) ?></strong> · pedido #<?= (int)($entry['subject_id'] ?? 0) ?> · <?= htmlspecialchars((string)($entry['created_at'] ?? '')) ?><br><small><?= htmlspecialchars((string)($entry['payload_json'] ?? '{}')) ?></small></li><?php endforeach; ?></ul>
</div>

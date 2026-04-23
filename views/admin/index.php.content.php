<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="admin-grid">
  <aside class="card p-3 sidebar">
    <div class="nav-label">Gestão</div>
    <?php foreach ([
      '/admin' => 'Visão geral',
      '/admin/users' => 'Utilizadores',
      '/admin/orders' => 'Pedidos',
      '/admin/payments' => 'Pagamentos',
      '/admin/human-review' => 'Revisão humana',
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>

    <div class="nav-label">Configuração</div>
    <?php foreach ([
      '/admin/institutions' => 'Instituições',
      '/admin/courses' => 'Cursos',
      '/admin/disciplines' => 'Disciplinas',
      '/admin/work-types' => 'Tipos de trabalho',
      '/admin/pricing' => 'Pricing',
      '/admin/discounts' => 'Descontos',
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </aside>

  <section>
    <div class="page-header">
      <div>
        <h1 class="section-title h3">Administração MOZacad</h1>
        <p>Backoffice de controlo operacional, financeiro e de qualidade.</p>
      </div>
    </div>

    <?php if (!empty($flashMessage)): ?><div class="alert alert-success"><?= htmlspecialchars((string) $flashMessage) ?></div><?php endif; ?>

    <form method="get" action="/admin" class="card p-3 mb-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label">Status revisão</label><select name="review_status" class="form-select"><option value="">Todos</option><?php foreach (['pending', 'assigned', 'approved', 'rejected'] as $status): ?><option value="<?= $status ?>" <?= (($reviewStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Status pedido</label><select name="order_status" class="form-select"><option value="">Todos</option><?php foreach (['pending_payment', 'queued', 'under_human_review', 'ready', 'revision_requested', 'failed', 'cancelled', 'expired'] as $status): ?><option value="<?= $status ?>" <?= (($orderStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="form-label">Status pagamento</label><select name="payment_status" class="form-select"><option value="">Todos</option><?php foreach (['pending', 'processing', 'pending_confirmation', 'paid', 'failed', 'cancelled', 'expired'] as $status): ?><option value="<?= $status ?>" <?= (($paymentStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Itens por página</label><input name="per_page" value="<?= (int) ($perPage ?? 20) ?>" class="form-control"></div>
        <div class="col-md-1"><button class="btn btn-primary w-100">OK</button></div>
      </div>
    </form>

    <div class="kbd-list mb-3">
      <div class="metric"><small>Utilizadores</small><div class="value"><?= count($users ?? []) ?></div></div>
      <div class="metric"><small>Pedidos</small><div class="value"><?= count($orders ?? []) ?></div></div>
      <div class="metric"><small>Pagamentos</small><div class="value"><?= count($payments ?? []) ?></div></div>
      <div class="metric"><small>Fila humana</small><div class="value"><?= count($humanReviewQueue ?? []) ?></div></div>
    </div>

    <div class="card p-3 mb-3">
      <h2 class="h5">Pedidos (auditoria operacional)</h2>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Utilizador</th><th>Tema</th><th>Status</th><th>Preço</th><th>Actualizado</th></tr></thead><tbody><?php foreach (($orders ?? []) as $order): ?><tr><td>#<?= (int) $order['id'] ?></td><td><?= htmlspecialchars((string) ($order['user_email'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($order['title_or_theme'] ?? '-')) ?></td><td><?= $badge((string) ($order['status'] ?? 'draft')) ?></td><td><?= $formatMoney($order['final_price'] ?? 0) ?></td><td><?= htmlspecialchars((string) ($order['updated_at'] ?? '-')) ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>

    <div class="card p-3 mb-3">
      <h2 class="h5">Pagamentos (auditoria financeira)</h2>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Order</th><th>User</th><th>Status</th><th>Provider</th><th>Ref</th></tr></thead><tbody><?php foreach (($payments ?? []) as $payment): ?><tr><td>#<?= (int) $payment['id'] ?></td><td>#<?= (int) $payment['order_id'] ?></td><td><?= htmlspecialchars((string) ($payment['user_email'] ?? '-')) ?></td><td><?= $badge((string) ($payment['status'] ?? 'pending')) ?></td><td><?= htmlspecialchars((string) ($payment['provider_status'] ?? '-')) ?></td><td><code><?= htmlspecialchars((string) ($payment['external_reference'] ?? '-')) ?></code></td></tr><?php endforeach; ?></tbody></table></div>
    </div>

    <div class="card p-3 mb-3">
      <h2 class="h5">Fila de revisão humana</h2>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>ID</th><th>Order</th><th>Status</th><th>Revisor</th><th>Decisão</th><th>Ações</th></tr></thead><tbody><?php foreach (($humanReviewQueue ?? []) as $row): ?><tr><td><?= (int) $row['id'] ?></td><td>#<?= (int) $row['order_id'] ?></td><td><?= $badge((string) ($row['status'] ?? 'pending_human_review')) ?></td><td><?= !empty($row['reviewer_id']) ? '#'.(int)$row['reviewer_id'] : 'Não atribuído' ?></td><td><?= htmlspecialchars((string) ($row['decision'] ?? '-')) ?></td><td><form method="post" action="/admin/human-review/<?= (int) $row['id'] ?>/assign" class="d-flex gap-1 mb-1"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><select name="reviewer_id" class="form-select form-select-sm" required><option value="">Revisor</option><?php foreach (($reviewers ?? []) as $reviewer): ?><option value="<?= (int) $reviewer['id'] ?>"><?= htmlspecialchars((string) $reviewer['name']) ?></option><?php endforeach; ?></select><button type="submit" class="btn btn-sm btn-outline-primary">Atribuir</button></form><form method="post" action="/admin/human-review/<?= (int) $row['id'] ?>/decision" class="d-flex gap-1"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><select class="form-select form-select-sm" name="decision"><option value="approve">Aprovar</option><option value="reject">Rejeitar</option></select><input class="form-control form-control-sm" name="notes" placeholder="Notas"><button type="submit" class="btn btn-sm btn-primary">Guardar</button></form></td></tr><?php endforeach; ?></tbody></table></div>
    </div>

    <div class="row g-3">
      <div class="col-lg-6">
        <div class="card p-3 h-100">
          <h2 class="h5">Pricing</h2>
          <form method="post" action="/admin/pricing/rules" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <div class="row g-2"><div class="col-md-5"><input class="form-control" name="rule_code" placeholder="rule_code" required></div><div class="col-md-4"><input class="form-control" name="rule_value" placeholder="valor" required></div><div class="col-md-3"><button class="btn btn-primary w-100">Guardar</button></div></div>
          </form>
          <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Regra</th><th>Valor</th><th>Ativo</th></tr></thead><tbody><?php foreach (($pricingRules ?? []) as $rule): ?><tr><td><?= htmlspecialchars((string) $rule['rule_code']) ?></td><td><?= htmlspecialchars((string) $rule['rule_value']) ?></td><td><?= (int) $rule['is_active'] ?></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-3 h-100">
          <h2 class="h5">Descontos</h2>
          <form method="post" action="/admin/discounts" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <div class="row g-2"><div class="col-md-3"><input class="form-control" name="user_id" placeholder="user_id" required></div><div class="col-md-4"><select class="form-select" name="discount_type"><option value="percent">percent</option><option value="fixed">fixed</option><option value="extra_waiver">extra_waiver</option></select></div><div class="col-md-3"><input class="form-control" type="number" step="0.01" name="discount_value" placeholder="valor" required></div><div class="col-md-2"><button class="btn btn-primary w-100">Criar</button></div></div>
          </form>
          <div class="table-responsive"><table class="table table-sm"><thead><tr><th>ID</th><th>User</th><th>Tipo</th><th>Valor</th></tr></thead><tbody><?php foreach (($discounts ?? []) as $d): ?><tr><td><?= (int) $d['id'] ?></td><td><?= (int) $d['user_id'] ?></td><td><?= htmlspecialchars((string) $d['discount_type']) ?></td><td><?= htmlspecialchars((string) $d['discount_value']) ?></td></tr><?php endforeach; ?></tbody></table></div>
        </div>
      </div>
    </div>
  </section>
</div>

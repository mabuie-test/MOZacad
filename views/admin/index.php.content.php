<div class="d-flex justify-content-between align-items-center mb-3">
  <h1 class="h4 mb-0">Admin MOZacad</h1>
  <small class="text-muted">MVP operacional</small>
</div>

<?php if (!empty($flashMessage)): ?>
  <div class="alert alert-success py-2"><?= htmlspecialchars((string) $flashMessage) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">Utilizadores</small><strong><?= count($users ?? []) ?></strong></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">Pedidos</small><strong><?= count($orders ?? []) ?></strong></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">Pagamentos</small><strong><?= count($payments ?? []) ?></strong></div></div></div>
  <div class="col-md-3"><div class="card"><div class="card-body"><small class="text-muted d-block">Fila revisão humana</small><strong><?= count($humanReviewQueue ?? []) ?></strong></div></div></div>
</div>

<div class="card mb-3">
  <div class="card-header fw-semibold">Fila de revisão humana</div>
  <div class="card-body">
    <div class="table-responsive mb-3">
      <table class="table table-sm table-striped">
        <thead><tr><th>ID</th><th>Order</th><th>Status</th><th>Reviewer</th><th>Decision</th><th>Atualizado</th></tr></thead>
        <tbody>
        <?php foreach (($humanReviewQueue ?? []) as $row): ?>
          <tr>
            <td><?= (int) $row['id'] ?></td>
            <td>#<?= (int) $row['order_id'] ?></td>
            <td><?= htmlspecialchars((string) $row['status']) ?></td>
            <td><?= htmlspecialchars((string) ($row['reviewer_id'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string) ($row['decision'] ?? '-')) ?></td>
            <td><?= htmlspecialchars((string) ($row['updated_at'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <form method="post" action="/admin/human-review/1/assign" class="border rounded p-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <h2 class="h6">Atribuir revisor (trocar queueId na URL)</h2>
          <div class="mb-2">
            <label class="form-label">Revisor</label>
            <select name="reviewer_id" class="form-select form-select-sm" required>
              <option value="">Selecionar...</option>
              <?php foreach (($reviewers ?? []) as $reviewer): ?>
                <option value="<?= (int) $reviewer['id'] ?>"><?= htmlspecialchars((string) $reviewer['name']) ?> (#<?= (int) $reviewer['id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-sm btn-primary">Atribuir</button>
        </form>
      </div>
      <div class="col-md-6">
        <form method="post" action="/admin/human-review/1/decision" class="border rounded p-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <h2 class="h6">Decidir revisão (trocar queueId na URL)</h2>
          <div class="mb-2">
            <label class="form-label">Decisão</label>
            <select class="form-select form-select-sm" name="decision" required>
              <option value="approve">Aprovar</option>
              <option value="reject">Devolver</option>
            </select>
          </div>
          <div class="mb-2">
            <label class="form-label">Notas</label>
            <textarea class="form-control form-control-sm" name="notes" rows="2"></textarea>
          </div>
          <button class="btn btn-sm btn-warning">Guardar decisão</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header fw-semibold">Pricing e extras</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <form method="post" action="/admin/pricing/rules" class="border rounded p-3 mb-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <h2 class="h6">Criar/Atualizar regra</h2>
          <input class="form-control form-control-sm mb-2" name="rule_code" placeholder="rule_code (ex: PRICING_MIN_ORDER_AMOUNT)" required>
          <input class="form-control form-control-sm mb-2" name="rule_value" placeholder="rule_value" required>
          <input class="form-control form-control-sm mb-2" name="description" placeholder="Descrição">
          <select class="form-select form-select-sm mb-2" name="is_active">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
          </select>
          <button class="btn btn-sm btn-primary">Guardar regra</button>
        </form>
      </div>
      <div class="col-md-6">
        <form method="post" action="/admin/pricing/extras" class="border rounded p-3 mb-3">
          <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
          <h2 class="h6">Criar/Atualizar extra</h2>
          <input class="form-control form-control-sm mb-2" name="extra_code" placeholder="extra_code (ex: needs_slides)" required>
          <input class="form-control form-control-sm mb-2" name="name" placeholder="Nome do extra" required>
          <input class="form-control form-control-sm mb-2" name="amount" placeholder="Valor" type="number" step="0.01" min="0" required>
          <select class="form-select form-select-sm mb-2" name="is_active">
            <option value="1">Ativo</option>
            <option value="0">Inativo</option>
          </select>
          <button class="btn btn-sm btn-primary">Guardar extra</button>
        </form>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-md-6">
        <h3 class="h6">Regras ativas</h3>
        <ul class="list-group list-group-flush small">
          <?php foreach (($pricingRules ?? []) as $rule): ?>
            <li class="list-group-item px-0">
              <strong><?= htmlspecialchars((string) $rule['rule_code']) ?></strong>
              = <?= htmlspecialchars((string) $rule['rule_value']) ?>
              <span class="text-muted">(ativo: <?= (int) $rule['is_active'] ?>)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <div class="col-md-6">
        <h3 class="h6">Extras</h3>
        <ul class="list-group list-group-flush small">
          <?php foreach (($pricingExtras ?? []) as $extra): ?>
            <li class="list-group-item px-0">
              <strong><?= htmlspecialchars((string) $extra['extra_code']) ?></strong>
              - <?= htmlspecialchars((string) $extra['name']) ?>
              (<?= htmlspecialchars((string) $extra['amount']) ?>)
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header fw-semibold">Descontos</div>
  <div class="card-body">
    <form method="post" action="/admin/discounts" class="row g-2 mb-3">
      <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
      <div class="col-md-2"><input class="form-control form-control-sm" name="user_id" placeholder="user_id" required></div>
      <div class="col-md-3"><input class="form-control form-control-sm" name="name" placeholder="Nome do desconto"></div>
      <div class="col-md-2">
        <select class="form-select form-select-sm" name="discount_type">
          <option value="percent">percent</option>
          <option value="fixed">fixed</option>
          <option value="extra_waiver">extra_waiver</option>
        </select>
      </div>
      <div class="col-md-2"><input class="form-control form-control-sm" name="discount_value" type="number" step="0.01" min="0" required></div>
      <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Criar</button></div>
    </form>

    <div class="table-responsive">
      <table class="table table-sm table-striped">
        <thead><tr><th>ID</th><th>User</th><th>Nome</th><th>Tipo</th><th>Valor</th><th>Ativo</th></tr></thead>
        <tbody>
          <?php foreach (($discounts ?? []) as $discount): ?>
            <tr>
              <td><?= (int) $discount['id'] ?></td>
              <td><?= (int) $discount['user_id'] ?></td>
              <td><?= htmlspecialchars((string) $discount['name']) ?></td>
              <td><?= htmlspecialchars((string) $discount['discount_type']) ?></td>
              <td><?= htmlspecialchars((string) $discount['discount_value']) ?></td>
              <td><?= (int) $discount['is_active'] ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-header fw-semibold">Cadastros institucionais e operação</div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <h3 class="h6">Instituições</h3>
        <ul class="small">
          <?php foreach (($institutions ?? []) as $i): ?><li><?= htmlspecialchars((string) $i['name']) ?></li><?php endforeach; ?>
        </ul>
        <h3 class="h6">Cursos</h3>
        <ul class="small">
          <?php foreach (($courses ?? []) as $c): ?><li><?= htmlspecialchars((string) $c['name']) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="col-md-6">
        <h3 class="h6">Disciplinas</h3>
        <ul class="small">
          <?php foreach (($disciplines ?? []) as $d): ?><li><?= htmlspecialchars((string) $d['name']) ?></li><?php endforeach; ?>
        </ul>
        <h3 class="h6">Tipos de trabalho</h3>
        <ul class="small">
          <?php foreach (($workTypes ?? []) as $w): ?><li><?= htmlspecialchars((string) $w['name']) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

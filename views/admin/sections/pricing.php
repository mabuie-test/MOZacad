<div class="kbd-list mb-3">
  <div class="metric"><small>Moeda</small><div class="value"><?= htmlspecialchars((string) ($pricingConfig['currency'] ?? 'MZN')) ?></div></div>
  <div class="metric"><small>Base por página</small><div class="value"><?= htmlspecialchars((string) ($pricingConfig['per_page_default'] ?? '-')) ?></div></div>
  <div class="metric"><small>Páginas incluídas</small><div class="value"><?= htmlspecialchars((string) ($pricingConfig['included_pages'] ?? '-')) ?></div></div>
  <div class="metric"><small>Pedido mínimo</small><div class="value"><?= htmlspecialchars((string) ($pricingConfig['min_order'] ?? '-')) ?></div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Regras de pricing</h2>
      <form method="post" action="/admin/pricing/rules" class="row g-2 mb-3" data-admin-pricing-rule-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-md-4"><input class="form-control text-uppercase" name="rule_code" placeholder="rule_code" required maxlength="100" pattern="[A-Z0-9_.:-]{3,100}" title="Use A-Z, 0-9, _, ., :, - (3-100)"></div>
        <div class="col-md-4"><input class="form-control" name="rule_value" placeholder="rule_value" required maxlength="120"></div>
        <div class="col-md-4"><button class="btn btn-primary w-100">Guardar</button></div>
        <div class="col-12 small mt-1" data-feedback></div>
      </form>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Regra</th><th>Valor</th><th>Ativa</th></tr></thead><tbody><?php foreach (($pricingRules ?? []) as $r): ?><tr><td><?= htmlspecialchars((string) $r['rule_code']) ?></td><td><?= htmlspecialchars((string) $r['rule_value']) ?></td><td><?= !empty($r['is_active']) ? 'Sim' : 'Não' ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Extras de pricing</h2>
      <form method="post" action="/admin/pricing/extras" class="row g-2 mb-3" data-admin-pricing-extra-form>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-md-3"><input class="form-control text-uppercase" name="extra_code" placeholder="extra_code" required maxlength="100" pattern="[A-Z0-9_.:-]{3,100}" title="Use A-Z, 0-9, _, ., :, - (3-100)"></div>
        <div class="col-md-4"><input class="form-control" name="name" placeholder="Nome" required maxlength="150" minlength="3"></div>
        <div class="col-md-3"><input class="form-control" type="number" step="0.01" min="0" max="10000000" name="amount" placeholder="Valor" required></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Guardar</button></div>
        <div class="col-12 small mt-1" data-feedback></div>
      </form>
      <div class="table-responsive"><table class="table table-sm"><thead><tr><th>Extra</th><th>Valor</th><th>Ativo</th></tr></thead><tbody><?php foreach (($pricingExtras ?? []) as $e): ?><tr><td><?= htmlspecialchars((string) $e['name']) ?><div class="muted-meta"><?= htmlspecialchars((string) $e['extra_code']) ?></div></td><td><?= $formatMoney($e['amount'] ?? 0) ?></td><td><?= !empty($e['is_active']) ? 'Sim' : 'Não' ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
  </div>
</div>

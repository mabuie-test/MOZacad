<div class="card p-3 mb-3">
  <h2 class="h5">Gestão de cupões promocionais</h2>
  <p class="text-secondary">Criação, edição, activação e controlo operacional de validade/uso.</p>

  <form method="post" action="/admin/coupons" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-3"><input class="form-control" name="code" placeholder="Código (ex: BEMVINDO10)" required></div>
    <div class="col-md-2">
      <select class="form-select" name="discount_type" required>
        <option value="percent">Percentual (%)</option>
        <option value="fixed">Valor fixo</option>
      </select>
    </div>
    <div class="col-md-2"><input class="form-control" type="number" min="0" step="0.01" name="discount_value" placeholder="Valor" required></div>
    <div class="col-md-2"><input class="form-control" type="number" min="1" step="1" name="usage_limit" placeholder="Limite de uso"></div>
    <div class="col-md-3 d-flex align-items-center gap-2">
      <div class="form-check"><input class="form-check-input" type="checkbox" id="coupon_is_active_new" name="is_active" checked><label class="form-check-label" for="coupon_is_active_new">Activo</label></div>
      <button class="btn btn-primary ms-auto">Criar cupão</button>
    </div>
    <div class="col-md-3"><label class="form-label small mb-1">Início</label><input class="form-control" type="datetime-local" name="starts_at"></div>
    <div class="col-md-3"><label class="form-label small mb-1">Fim</label><input class="form-control" type="datetime-local" name="ends_at"></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Cupões registados</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Código</th><th>Condição comercial</th><th>Uso</th><th>Janela</th><th>Estado</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach (($coupons ?? []) as $c): ?>
        <tr>
          <td><code><?= htmlspecialchars((string) $c['code']) ?></code></td>
          <td><?= htmlspecialchars((string) ($c['discount_type'] ?? '-')) ?> · <?= htmlspecialchars((string) ($c['discount_value'] ?? '-')) ?></td>
          <td><?= (int) ($c['used_count'] ?? 0) ?> / <?= htmlspecialchars((string) ($c['usage_limit'] ?? '∞')) ?><br><small class="text-secondary">logs: <?= (int) ($c['usage_logs_count'] ?? 0) ?></small></td>
          <td><?= htmlspecialchars((string) ($c['starts_at'] ?? '-')) ?> → <?= htmlspecialchars((string) ($c['ends_at'] ?? '-')) ?></td>
          <td><?= !empty($c['is_active']) ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-secondary">Inactivo</span>' ?></td>
          <td style="min-width: 380px;">
            <form method="post" action="/admin/coupons/<?= (int) $c['id'] ?>" class="row g-1 mb-1">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <div class="col-4"><input class="form-control form-control-sm" name="code" value="<?= htmlspecialchars((string) $c['code']) ?>" required></div>
              <div class="col-3"><select class="form-select form-select-sm" name="discount_type"><option value="percent" <?= ($c['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>percent</option><option value="fixed" <?= ($c['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>fixed</option></select></div>
              <div class="col-2"><input class="form-control form-control-sm" type="number" step="0.01" min="0" name="discount_value" value="<?= htmlspecialchars((string) $c['discount_value']) ?>"></div>
              <div class="col-3"><input class="form-control form-control-sm" type="number" min="1" name="usage_limit" value="<?= htmlspecialchars((string) ($c['usage_limit'] ?? '')) ?>" placeholder="limite"></div>
              <div class="col-4"><input class="form-control form-control-sm" type="datetime-local" name="starts_at" value="<?= !empty($c['starts_at']) ? date('Y-m-d\TH:i', strtotime((string) $c['starts_at'])) : '' ?>"></div>
              <div class="col-4"><input class="form-control form-control-sm" type="datetime-local" name="ends_at" value="<?= !empty($c['ends_at']) ? date('Y-m-d\TH:i', strtotime((string) $c['ends_at'])) : '' ?>"></div>
              <div class="col-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" <?= !empty($c['is_active']) ? 'checked' : '' ?>></div></div>
              <div class="col-2"><button class="btn btn-sm btn-outline-primary w-100">Guardar</button></div>
            </form>

            <form method="post" action="/admin/coupons/<?= (int) $c['id'] ?>/toggle" class="d-inline">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <input type="hidden" name="is_active" value="<?= !empty($c['is_active']) ? '0' : '1' ?>">
              <button class="btn btn-sm <?= !empty($c['is_active']) ? 'btn-outline-warning' : 'btn-outline-success' ?>"><?= !empty($c['is_active']) ? 'Inactivar' : 'Activar' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

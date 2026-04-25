<div class="card p-3 mb-3">
  <h2 class="h5">Criar tipo de trabalho</h2>
  <form method="post" action="/admin/work-types" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Nome" required></div>
    <div class="col-md-2"><input class="form-control" name="slug" placeholder="slug" required></div>
    <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="base_price" placeholder="Preço base"></div>
    <div class="col-md-2"><select class="form-select" name="default_complexity"><option>low</option><option selected>medium</option><option>high</option><option>very_high</option></select></div>
    <div class="col-md-2"><input class="form-control" type="number" name="display_order" value="0"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Criar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Tipos de trabalho</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Tipo</th><th>Preço base</th><th>Flags</th><th>Editar</th></tr></thead>
      <tbody>
      <?php foreach (($workTypes ?? []) as $w): ?>
        <tr>
          <td><?= (int) $w['id'] ?></td>
          <td><?= htmlspecialchars((string) $w['name']) ?><div class="muted-meta"><code><?= htmlspecialchars((string) $w['slug']) ?></code></div></td>
          <td><?= $formatMoney($w['base_price'] ?? 0) ?></td>
          <td>
            <?= !empty($w['is_active']) ? 'Activo' : 'Inactivo' ?> ·
            <?= !empty($w['requires_human_review']) ? 'Revisão humana' : 'Auto' ?>
          </td>
          <td>
            <details>
              <summary class="btn btn-sm btn-outline-secondary">Editar</summary>
              <form method="post" action="/admin/work-types/<?= (int) $w['id'] ?>" class="row g-2 mt-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <div class="col-8"><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $w['name']) ?>" required></div>
                <div class="col-4"><input class="form-control form-control-sm" name="slug" value="<?= htmlspecialchars((string) $w['slug']) ?>" required></div>
                <div class="col-4"><input class="form-control form-control-sm" name="base_price" type="number" step="0.01" value="<?= htmlspecialchars((string) ($w['base_price'] ?? 0)) ?>"></div>
                <div class="col-4"><input class="form-control form-control-sm" name="display_order" type="number" value="<?= (int) ($w['display_order'] ?? 0) ?>"></div>
                <div class="col-4"><select class="form-select form-select-sm" name="default_complexity"><option value="low" <?= (($w['default_complexity'] ?? '') === 'low') ? 'selected' : '' ?>>low</option><option value="medium" <?= (($w['default_complexity'] ?? '') === 'medium') ? 'selected' : '' ?>>medium</option><option value="high" <?= (($w['default_complexity'] ?? '') === 'high') ? 'selected' : '' ?>>high</option><option value="very_high" <?= (($w['default_complexity'] ?? '') === 'very_high') ? 'selected' : '' ?>>very_high</option></select></div>
                <div class="col-12 d-flex gap-3 small"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !empty($w['is_active']) ? 'checked' : '' ?>> Activo</label><label class="form-check"><input class="form-check-input" type="checkbox" name="requires_human_review" value="1" <?= !empty($w['requires_human_review']) ? 'checked' : '' ?>> Revisão humana</label><label class="form-check"><input class="form-check-input" type="checkbox" name="is_premium_type" value="1" <?= !empty($w['is_premium_type']) ? 'checked' : '' ?>> Premium</label></div>
                <div class="col-12"><textarea class="form-control form-control-sm" name="description" rows="2" placeholder="Descrição"><?= htmlspecialchars((string) ($w['description'] ?? '')) ?></textarea></div>
                <div class="col-12"><button class="btn btn-sm btn-primary">Guardar alterações</button></div>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

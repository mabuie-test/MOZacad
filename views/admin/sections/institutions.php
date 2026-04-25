<div class="card p-3 mb-3">
  <h2 class="h5">Criar instituição</h2>
  <form method="post" action="/admin/institutions" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-4"><input class="form-control" name="name" placeholder="Nome da instituição" required></div>
    <div class="col-md-3"><input class="form-control" name="short_name" placeholder="Nome curto"></div>
    <div class="col-md-3"><input class="form-control" name="slug" placeholder="slug institucional"></div>
    <div class="col-md-1 d-flex align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" checked> <span class="form-check-label">Activa</span></label></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Criar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Gestão de instituições</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Nome</th><th>Slug</th><th>Status</th><th>Editar</th></tr></thead>
      <tbody>
      <?php foreach (($institutions ?? []) as $i): ?>
        <tr>
          <td><?= (int) $i['id'] ?></td>
          <td><?= htmlspecialchars((string) $i['name']) ?><div class="muted-meta"><?= htmlspecialchars((string) ($i['short_name'] ?? '-')) ?></div></td>
          <td><code><?= htmlspecialchars((string) ($i['slug'] ?? '-')) ?></code></td>
          <td><?= !empty($i['is_active']) ? 'Activa' : 'Inactiva' ?></td>
          <td>
            <details>
              <summary class="btn btn-sm btn-outline-secondary">Editar</summary>
              <form method="post" action="/admin/institutions/<?= (int) $i['id'] ?>" class="row g-2 mt-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <div class="col-12"><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $i['name']) ?>" required></div>
                <div class="col-6"><input class="form-control form-control-sm" name="short_name" value="<?= htmlspecialchars((string) ($i['short_name'] ?? '')) ?>"></div>
                <div class="col-6"><input class="form-control form-control-sm" name="slug" value="<?= htmlspecialchars((string) ($i['slug'] ?? '')) ?>"></div>
                <div class="col-8 d-flex align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !empty($i['is_active']) ? 'checked' : '' ?>> <span class="form-check-label">Activa</span></label></div>
                <div class="col-4"><button class="btn btn-sm btn-primary w-100">Guardar</button></div>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h5">Criar curso</h2>
  <form method="post" action="/admin/courses" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-4"><select class="form-select" name="institution_id" required><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><input class="form-control" name="name" placeholder="Nome do curso" required></div>
    <div class="col-md-2"><input class="form-control" name="code" placeholder="Código"></div>
    <div class="col-md-1 d-flex align-items-center"><input class="form-check-input" type="checkbox" name="is_active" checked></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Criar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Cursos</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Instituição</th><th>Curso</th><th>Estado</th><th>Editar</th></tr></thead>
      <tbody>
      <?php foreach (($courses ?? []) as $c): ?>
        <tr>
          <td><?= (int) $c['id'] ?></td>
          <td><?= htmlspecialchars((string) ($c['institution_name'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) $c['name']) ?><div class="muted-meta">Código: <?= htmlspecialchars((string) ($c['code'] ?? '-')) ?></div></td>
          <td><?= !empty($c['is_active']) ? 'Activo' : 'Inactivo' ?></td>
          <td>
            <details>
              <summary class="btn btn-sm btn-outline-secondary">Editar</summary>
              <form method="post" action="/admin/courses/<?= (int) $c['id'] ?>" class="row g-2 mt-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <div class="col-12"><select class="form-select form-select-sm" name="institution_id" required><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>" <?= ((int) $c['institution_id'] === (int) $i['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-8"><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $c['name']) ?>" required></div>
                <div class="col-4"><input class="form-control form-control-sm" name="code" value="<?= htmlspecialchars((string) ($c['code'] ?? '')) ?>"></div>
                <div class="col-8 d-flex align-items-center"><label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" <?= !empty($c['is_active']) ? 'checked' : '' ?>> <span class="form-check-label">Activo</span></label></div>
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

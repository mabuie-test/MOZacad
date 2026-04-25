<div class="card p-3 mb-3">
  <h2 class="h5">Criar disciplina</h2>
  <form method="post" action="/admin/disciplines" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-3"><select class="form-select" name="institution_id"><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><select class="form-select" name="course_id"><option value="">Curso</option><?php foreach (($courses ?? []) as $c): ?><option value="<?= (int) $c['id'] ?>"><?= htmlspecialchars((string) $c['name']) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Nome da disciplina" required></div>
    <div class="col-md-2"><input class="form-control" name="code" placeholder="Código"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Criar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Disciplinas</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Disciplina</th><th>Curso</th><th>Instituição</th><th>Editar</th></tr></thead>
      <tbody>
      <?php foreach (($disciplines ?? []) as $d): ?>
        <tr>
          <td><?= (int) $d['id'] ?></td>
          <td><?= htmlspecialchars((string) $d['name']) ?><div class="muted-meta">Código: <?= htmlspecialchars((string) ($d['code'] ?? '-')) ?></div></td>
          <td><?= htmlspecialchars((string) ($d['course_name'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($d['institution_name'] ?? '-')) ?></td>
          <td>
            <details>
              <summary class="btn btn-sm btn-outline-secondary">Editar</summary>
              <form method="post" action="/admin/disciplines/<?= (int) $d['id'] ?>" class="row g-2 mt-2">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <div class="col-12"><input class="form-control form-control-sm" name="name" value="<?= htmlspecialchars((string) $d['name']) ?>" required></div>
                <div class="col-6"><select class="form-select form-select-sm" name="institution_id"><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>" <?= ((int) ($d['institution_id'] ?? 0) === (int) $i['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-6"><select class="form-select form-select-sm" name="course_id"><option value="">Curso</option><?php foreach (($courses ?? []) as $c): ?><option value="<?= (int) $c['id'] ?>" <?= ((int) ($d['course_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $c['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-8"><input class="form-control form-control-sm" name="code" value="<?= htmlspecialchars((string) ($d['code'] ?? '')) ?>"></div>
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

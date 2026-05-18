<div class="card p-3 mb-3">
  <h2 class="h5">Criar utilizador</h2>
  <form method="post" action="/admin/users" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Nome" required></div>
    <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
    <div class="col-md-2"><input class="form-control" name="phone" placeholder="Telefone"></div>
    <div class="col-md-2"><input class="form-control" type="password" name="password" placeholder="Senha (mín 8)" required></div>
    <div class="col-md-2">
      <select class="form-select" name="roles[]" multiple>
        <?php foreach (($roles ?? []) as $role): ?>
          <option value="<?= htmlspecialchars((string) ($role['name'] ?? '')) ?>"><?= htmlspecialchars((string) ($role['name'] ?? '-')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12"><button class="btn btn-primary btn-sm">Criar utilizador</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Utilizadores</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Nome</th><th>Email</th><th>Telefone</th><th>Papéis</th><th>Estado</th><th>Criado em</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach (($users ?? []) as $u): ?>
        <?php $currentRoles = array_filter(array_map('trim', explode(',', (string) ($u['role_names'] ?? '')))); ?>
        <tr>
          <td>#<?= (int) $u['id'] ?></td>
          <td><?= htmlspecialchars((string) ($u['name'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($u['email'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($u['phone'] ?? '-')) ?></td>
          <td>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/roles" class="d-flex gap-2">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <select class="form-select form-select-sm" name="roles[]" multiple>
                <?php foreach (($roles ?? []) as $role): ?>
                  <?php $r = (string) ($role['name'] ?? ''); ?>
                  <option value="<?= htmlspecialchars($r) ?>" <?= in_array($r, $currentRoles, true) ? 'selected' : '' ?>><?= htmlspecialchars($r) ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-outline-primary btn-sm">Guardar papéis</button>
            </form>
          </td>
          <td><?= !empty($u['is_active']) ? 'Activo' : 'Inactivo' ?></td>
          <td><?= htmlspecialchars((string) ($u['created_at'] ?? '-')) ?></td>
          <td class="d-flex gap-1">
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/status"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><input type="hidden" name="action" value="<?= !empty($u['is_active']) ? 'suspend' : 'activate' ?>"><button class="btn btn-outline-warning btn-sm"><?= !empty($u['is_active']) ? 'Suspender/Bloquear' : 'Ativar' ?></button></form>
            <form method="post" action="/admin/users/<?= (int) $u['id'] ?>/delete" onsubmit="return confirm('Eliminar utilizador? Esta ação não pode ser desfeita.');"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><button class="btn btn-outline-danger btn-sm">Eliminar</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

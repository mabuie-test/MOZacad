<div class="card p-3 mb-3">
  <h2 class="h5">Matriz de permissões por papel</h2>
  <p class="text-secondary mb-3">Gestão segura de autorizações granulares para endpoints críticos do backoffice.</p>

  <form method="post" action="/admin/permissions/matrix">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead>
        <tr>
          <th>Permissão</th>
          <?php foreach (($roles ?? []) as $role): ?>
            <th><?= htmlspecialchars((string) ($role['name'] ?? '-')) ?></th>
          <?php endforeach; ?>
        </tr>
        </thead>
        <tbody>
        <?php foreach (($permissions ?? []) as $permission): ?>
          <?php $code = (string) ($permission['code'] ?? ''); ?>
          <tr>
            <td>
              <code><?= htmlspecialchars($code) ?></code>
              <div class="muted-meta"><?= htmlspecialchars((string) ($permission['description'] ?? '-')) ?></div>
            </td>
            <?php foreach (($roles ?? []) as $role): ?>
              <?php
                $roleName = (string) ($role['name'] ?? '');
                $allowed = in_array($code, (array) ($rolePermissionMap[$roleName] ?? []), true);
              ?>
              <td>
                <input
                  type="checkbox"
                  name="matrix[<?= htmlspecialchars($roleName) ?>][]"
                  value="<?= htmlspecialchars($code) ?>"
                  <?= $allowed ? 'checked' : '' ?>
                >
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <button class="btn btn-primary">Guardar matriz</button>
  </form>
</div>

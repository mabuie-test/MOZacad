<div class="card p-3 mb-3">
  <h2 class="h5">Criar desconto</h2>
  <form method="post" action="/admin/discounts" class="row g-2">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
    <div class="col-md-2"><input class="form-control" name="user_id" placeholder="user_id" required></div>
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Nome do desconto"></div>
    <div class="col-md-2"><select class="form-select" name="discount_type"><option value="percent">percent</option><option value="fixed">fixed</option><option value="extra_waiver">extra_waiver</option></select></div>
    <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="discount_value" placeholder="Valor" required></div>
    <div class="col-md-2"><input class="form-control" name="extra_code" placeholder="extra_code"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Criar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Descontos existentes</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>User</th><th>Tipo</th><th>Valor</th><th>Período</th><th>Editar</th></tr></thead>
      <tbody>
      <?php foreach (($discounts ?? []) as $d): ?>
        <tr>
          <td>#<?= (int) $d['id'] ?></td>
          <td><?= (int) $d['user_id'] ?><div class="muted-meta"><?= htmlspecialchars((string) ($d['name'] ?? '-')) ?></div></td>
          <td><?= htmlspecialchars((string) $d['discount_type']) ?></td>
          <td><?= htmlspecialchars((string) $d['discount_value']) ?></td>
          <td><?= htmlspecialchars((string) ($d['starts_at'] ?? '-')) ?> → <?= htmlspecialchars((string) ($d['ends_at'] ?? '-')) ?></td>
          <td>
            <form method="post" action="/admin/discounts/<?= (int) $d['id'] ?>" class="d-flex gap-1">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <input type="hidden" name="name" value="<?= htmlspecialchars((string) ($d['name'] ?? 'Desconto personalizado')) ?>">
              <input type="hidden" name="discount_type" value="<?= htmlspecialchars((string) $d['discount_type']) ?>">
              <input type="hidden" name="discount_value" value="<?= htmlspecialchars((string) $d['discount_value']) ?>">
              <input type="hidden" name="is_active" value="<?= empty($d['is_active']) ? '1' : '0' ?>">
              <button class="btn btn-sm btn-outline-secondary"><?= empty($d['is_active']) ? 'Activar' : 'Desactivar' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h5">Filtro da fila humana</h2>
  <form method="get" action="/admin/human-review" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Status</label><select name="review_status" class="form-select"><option value="">Todos</option><?php foreach (['pending','assigned','approved','rejected'] as $status): ?><option value="<?= $status ?>" <?= (($reviewStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Fila de revisão humana</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Pedido</th><th>Documento</th><th>Status</th><th>Revisor</th><th>Decisão</th><th>Ações</th></tr></thead>
      <tbody>
      <?php foreach (($humanReviewQueue ?? []) as $row): ?>
        <tr>
          <td>#<?= (int) $row['id'] ?></td>
          <td>
            #<?= (int) $row['order_id'] ?>
            <div class="muted-meta"><?= htmlspecialchars((string) ($row['title_or_theme'] ?? '-')) ?></div>
            <div class="muted-meta">Utilizador: <?= htmlspecialchars((string) ($row['user_email'] ?? '-')) ?></div>
          </td>
          <td>Doc #<?= (int) ($row['generated_document_id'] ?? 0) ?> · v<?= (int) ($row['generated_document_version'] ?? 0) ?></td>
          <td><?= $badge((string) ($row['status'] ?? 'pending')) ?><div class="muted-meta">Order: <?= htmlspecialchars((string) ($row['order_status'] ?? '-')) ?></div></td>
          <td><?= !empty($row['reviewer_id']) ? '#'.(int)$row['reviewer_id'] : 'Não atribuído' ?></td>
          <td><?= htmlspecialchars((string) ($row['decision'] ?? '-')) ?><div class="muted-meta"><?= htmlspecialchars((string) ($row['comments'] ?? '-')) ?></div></td>
          <td>
            <form method="post" action="/admin/human-review/<?= (int) $row['id'] ?>/assign" class="d-flex gap-1 mb-1">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <select name="reviewer_id" class="form-select form-select-sm" required><option value="">Revisor</option><?php foreach (($reviewers ?? []) as $r): ?><option value="<?= (int) $r['id'] ?>" <?= ((int) ($row['reviewer_id'] ?? 0) === (int) $r['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string) $r['name']) ?></option><?php endforeach; ?></select>
              <button class="btn btn-sm btn-outline-primary">Atribuir</button>
            </form>
            <form method="post" action="/admin/human-review/<?= (int) $row['id'] ?>/decision" class="d-flex gap-1">
              <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
              <select name="decision" class="form-select form-select-sm"><option value="approve">Aprovar</option><option value="reject">Rejeitar</option></select>
              <input class="form-control form-control-sm" name="notes" placeholder="Notas da decisão">
              <button class="btn btn-sm btn-primary">Guardar</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

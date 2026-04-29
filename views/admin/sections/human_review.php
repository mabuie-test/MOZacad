<?php
use App\Domain\StatusCatalog;
?>
<div class="card p-3 mb-3">
  <h2 class="h5">Filtro da fila humana</h2>
  <form method="get" action="/admin/human-review" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label">Status</label><select name="review_status" class="form-select"><option value="">Todos</option><?php foreach (StatusCatalog::humanReviewQueueStatuses() as $status): ?><option value="<?= $status ?>" <?= (($reviewStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= $status ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Fila de revisão humana</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Pedido</th><th>Documento</th><th>Status</th><th>Checklist</th><th>Revisor</th><th>Decisão</th><th>Ações</th></tr></thead>
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
          <td>
            <?php $checked = (int) ($row['checklist_checked_items'] ?? 0); $total = (int) ($row['checklist_total_items'] ?? 0); $blocking = (int) ($row['checklist_blocking_items'] ?? 0); ?>
            <div class="muted-meta">Progresso: <?= $checked ?>/<?= $total ?></div>
            <div class="muted-meta">Aprovados: <?= (int) ($row['checklist_approved_items'] ?? 0) ?></div>
            <div class="muted-meta text-<?= $blocking > 0 ? 'danger' : 'success' ?>">Pendências impeditivas: <?= $blocking ?></div>
            <div class="mt-2">
              <?php foreach (($row['checklist_items'] ?? []) as $checkItem): ?>
                <form method="post" action="/admin/delivery-checklists/<?= (int) ($row['generated_document_id'] ?? 0) ?>/<?= (int) ($row['generated_document_version'] ?? 0) ?>/items" class="border rounded p-2 mb-1">
                  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                  <input type="hidden" name="checklist_item" value="<?= htmlspecialchars((string) ($checkItem['checklist_item'] ?? '')) ?>">
                  <div class="fw-semibold small mb-1"><?= htmlspecialchars((string) ($checkItem['checklist_item'] ?? '-')) ?></div>
                  <div class="d-flex gap-1 mb-1">
                    <select name="status" class="form-select form-select-sm">
                      <?php foreach (['pending','approved','rejected'] as $statusOpt): ?>
                        <option value="<?= $statusOpt ?>" <?= ((string) ($checkItem['status'] ?? '') === $statusOpt) ? 'selected' : '' ?>><?= $statusOpt ?></option>
                      <?php endforeach; ?>
                    </select>
                    <select name="is_checked" class="form-select form-select-sm">
                      <option value="0" <?= ((int) ($checkItem['is_checked'] ?? 0) === 0) ? 'selected' : '' ?>>Não</option>
                      <option value="1" <?= ((int) ($checkItem['is_checked'] ?? 0) === 1) ? 'selected' : '' ?>>Sim</option>
                    </select>
                  </div>
                  <input class="form-control form-control-sm mb-1" name="notes" value="<?= htmlspecialchars((string) ($checkItem['notes'] ?? '')) ?>" placeholder="Notas">
                  <div class="muted-meta">Revisor: <?= !empty($checkItem['reviewer_signed_by']) ? '#'.(int)$checkItem['reviewer_signed_by'] : '-' ?> | Aprovador: <?= !empty($checkItem['approver_signed_by']) ? '#'.(int)$checkItem['approver_signed_by'] : '-' ?></div>
                  <button class="btn btn-sm btn-outline-secondary mt-1">Atualizar item</button>
                </form>
              <?php endforeach; ?>
              <form method="post" action="/admin/delivery-checklists/<?= (int) ($row['generated_document_id'] ?? 0) ?>/<?= (int) ($row['generated_document_version'] ?? 0) ?>/sign-reviewer" class="d-inline-block me-1">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <button class="btn btn-sm btn-outline-primary">Assinar revisor</button>
              </form>
              <form method="post" action="/admin/delivery-checklists/<?= (int) ($row['generated_document_id'] ?? 0) ?>/<?= (int) ($row['generated_document_version'] ?? 0) ?>/sign-approver" class="d-inline-block">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <button class="btn btn-sm btn-primary">Assinar aprovador</button>
              </form>
            </div>
          </td>
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

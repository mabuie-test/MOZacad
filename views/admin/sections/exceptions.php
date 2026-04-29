<div class="card p-3 mb-3">
  <h2 class="h5">Filtro de exceções pós-pagamento</h2>
  <form method="get" action="/admin/exceptions" class="row g-2 align-items-end">
    <div class="col-md-2"><label class="form-label">Estado</label><input name="exception_state" class="form-control" value="<?= htmlspecialchars((string)($exceptionStateFilter ?? '')) ?>" placeholder="open/in_review"></div>
    <div class="col-md-2"><label class="form-label">SLA</label><select name="exception_sla" class="form-select"><option value="">Todos</option><option value="overdue" <?= (($exceptionSlaFilter ?? '')==='overdue')?'selected':'' ?>>Atrasado</option><option value="due_24h" <?= (($exceptionSlaFilter ?? '')==='due_24h')?'selected':'' ?>>Vence em 24h</option><option value="on_track" <?= (($exceptionSlaFilter ?? '')==='on_track')?'selected':'' ?>>No prazo</option></select></div>
    <div class="col-md-2"><label class="form-label">Owner (ID)</label><input type="number" min="1" name="exception_owner" class="form-control" value="<?= (int)($exceptionOwnerFilter ?? 0) ?>"></div>
    <div class="col-md-2"><label class="form-label">Escalonamento</label><select name="exception_escalated" class="form-select"><option value="">Todos</option><option value="yes" <?= (($exceptionEscalatedFilter ?? '')==='yes')?'selected':'' ?>>Escalado</option><option value="no" <?= (($exceptionEscalatedFilter ?? '')==='no')?'selected':'' ?>>Não escalado</option></select></div>
    <div class="col-md-2"><button class="btn btn-outline-primary w-100">Aplicar</button></div>
  </form>
</div>

<div class="card p-3">
  <h2 class="h5">Exceções registadas</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th>ID</th><th>Pedido</th><th>Categoria</th><th>Estado</th><th>Owner</th><th>SLA</th><th>Esc.</th><th>Bloqueio</th></tr></thead>
      <tbody>
      <?php foreach (($exceptions ?? []) as $e): ?>
        <tr>
          <td>#<?= (int)$e['id'] ?></td>
          <td>#<?= (int)$e['order_id'] ?> · <?= htmlspecialchars((string)($e['title_or_theme'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($e['category'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($e['state'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($e['owner_email'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string)($e['sla_due_at'] ?? '-')) ?></td>
          <td><?= (int)($e['escalation_level'] ?? 0) ?></td>
          <td><?= ((int)($e['blocked_delivery'] ?? 0) === 1) ? 'Sim' : 'Não' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

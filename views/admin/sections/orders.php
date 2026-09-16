<?php
use App\Domain\StatusCatalog;

$manualUploadOrders = array_values(array_filter((array) ($orders ?? []), static function (array $order): bool {
    $payment = $order['latest_payment'] ?? null;
    return is_array($payment)
        && (string) ($payment['status'] ?? '') === 'paid'
        && in_array((string) ($order['status'] ?? ''), ['awaiting_manual_upload', 'returned_for_revision', 'revision_requested'], true);
}));
?>

<section class="manual-workspace mb-4" aria-labelledby="manual-workspace-title">
  <div class="manual-workspace__intro">
    <span class="eyebrow">Entrega académica assistida</span>
    <h2 id="manual-workspace-title">Mesa de produção manual</h2>
    <p>Priorize trabalhos pagos, carregue a versão preparada pela equipa e encaminhe-a para uma revisão editorial independente.</p>
  </div>
  <div class="manual-workspace__metric">
    <span>Prontos para upload</span>
    <strong><?= count($manualUploadOrders) ?></strong>
    <a href="/admin/orders?order_status=awaiting_manual_upload">Ver pendentes</a>
  </div>
  <ol class="delivery-steps" aria-label="Etapas de entrega manual">
    <li><span>1</span><div><strong>Confirmar pagamento</strong><small>O pedido entra na mesa de produção.</small></div></li>
    <li><span>2</span><div><strong>Carregar versão</strong><small>DOCX ou PDF, até 25 MB por predefinição.</small></div></li>
    <li><span>3</span><div><strong>Revisar e aprovar</strong><small>Um revisor independente valida a entrega.</small></div></li>
  </ol>
</section>

<div class="card p-3 mb-3">
  <h2 class="h5 mb-3">Encontrar pedido</h2>
  <form method="get" action="/admin/orders" class="row g-2 align-items-end">
    <div class="col-md-3"><label class="form-label" for="order-status">Estado</label><select id="order-status" name="order_status" class="form-select"><option value="">Todos</option><?php foreach (StatusCatalog::orderStatuses() as $status): ?><option value="<?= $status ?>" <?= (($orderStatusFilter ?? '') === $status) ? 'selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label" for="order-risk">Prioridade</label><select id="order-risk" name="risk" class="form-select"><option value="">Todas</option><option value="high" <?= (($riskFilter ?? '') === 'high') ? 'selected' : '' ?>>Alta prioridade</option><option value="normal" <?= (($riskFilter ?? '') === 'normal') ? 'selected' : '' ?>>Normal</option></select></div>
    <div class="col-md-3"><label class="form-label" for="order-delay">SLA</label><select id="order-delay" name="delay" class="form-select"><option value="">Todos</option><option value="late" <?= (($delayFilter ?? '') === 'late') ? 'selected' : '' ?>>Atrasado</option><option value="at_risk" <?= (($delayFilter ?? '') === 'at_risk') ? 'selected' : '' ?>>Em risco</option><option value="on_track" <?= (($delayFilter ?? '') === 'on_track') ? 'selected' : '' ?>>No prazo</option></select></div>
    <div class="col-md-3 d-flex gap-2"><button class="btn btn-primary flex-grow-1">Aplicar filtros</button><a class="btn btn-outline-secondary" href="/admin/orders">Limpar</a></div>
  </form>
</div>

<div class="card p-3 mb-3">
  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-end mb-3"><div><h2 class="h5 mb-1">Pedidos</h2><p class="text-secondary small mb-0"><?= count((array) ($orders ?? [])) ?> resultado(s). O upload abre automaticamente uma etapa de revisão humana.</p></div><a class="btn btn-sm btn-outline-primary" href="/admin/human-review">Abrir revisões</a></div>
  <div class="table-responsive"><table class="table table-sm align-middle delivery-table"><thead><tr><th>Pedido</th><th>Contexto</th><th>Estado</th><th>SLA</th><th>Entrega</th><th>Ações operacionais</th></tr></thead><tbody>
  <?php foreach (($orders ?? []) as $o): ?>
    <?php $latestPayment = $o['latest_payment'] ?? null; $canUpload = is_array($latestPayment) && (string) ($latestPayment['status'] ?? '') === 'paid' && in_array((string) ($o['status'] ?? ''), ['awaiting_manual_upload', 'returned_for_revision', 'revision_requested'], true); ?>
    <tr>
      <td><strong>#<?= (int) $o['id'] ?></strong><div class="muted-meta"><?= htmlspecialchars((string) ($o['user_email'] ?? '-')) ?></div></td>
      <td><strong><?= htmlspecialchars((string) ($o['title_or_theme'] ?? '-')) ?></strong><div class="muted-meta"><?= htmlspecialchars((string) ($o['work_type_name'] ?? '-')) ?> · <?= $formatMoney($o['final_price'] ?? 0) ?></div></td>
      <td><?= $badge((string) ($o['status'] ?? 'draft')) ?><div class="muted-meta mt-1">Prioridade: <?= htmlspecialchars((string) ($o['admin_priority'] ?? 'normal')) ?></div></td>
      <td><?= htmlspecialchars((string) ($o['sla_state'] ?? '-')) ?></td>
      <td>
        <?php if ($canUpload): ?>
          <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/upload-document" enctype="multipart/form-data" class="manual-upload-form">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <label class="form-label small mb-1" for="document-<?= (int) $o['id'] ?>">Versão pronta</label>
            <input id="document-<?= (int) $o['id'] ?>" type="file" name="document" accept=".docx,.pdf" required class="form-control form-control-sm">
            <p class="form-hint">DOCX ou PDF · máximo 25 MB</p>
            <button class="btn btn-sm btn-primary w-100">Enviar para revisão</button>
          </form>
        <?php elseif (is_array($latestPayment) && (string) ($latestPayment['status'] ?? '') !== 'paid'): ?>
          <span class="text-secondary small">Aguardar confirmação do pagamento.</span>
        <?php else: ?>
          <span class="text-secondary small">Nenhum upload necessário nesta etapa.</span>
        <?php endif; ?>
      </td>
      <td><div class="operation-actions">
        <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/pause"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><button class="btn btn-sm btn-outline-secondary">Pausar</button></form>
        <form method="post" action="/admin/orders/<?= (int) $o['id'] ?>/resume"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><button class="btn btn-sm btn-outline-success">Retomar</button></form>
        <?php if (is_array($latestPayment) && in_array((string) ($latestPayment['status'] ?? ''), ['pending','processing','pending_confirmation'], true)): ?><form method="post" action="/admin/payments/<?= (int) ($latestPayment['id'] ?? 0) ?>/confirm-manual"><input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>"><input type="hidden" name="provider_status" value="SUCCESSFUL"><button class="btn btn-sm btn-outline-primary">Confirmar pagamento</button></form><?php endif; ?>
        <?php $latestInvoice = $o['latest_invoice'] ?? null; if (is_array($latestInvoice)): ?><a class="btn btn-sm btn-link" href="/admin/invoices/<?= (int) ($latestInvoice['id'] ?? 0) ?>">Factura</a><?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  <?php if (($orders ?? []) === []): ?><tr><td colspan="6" class="text-center text-secondary py-4">Nenhum pedido corresponde aos filtros seleccionados.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>

<div class="card p-3">
  <h2 class="h5">Linha temporal auditável</h2>
  <p class="text-secondary small">Consulte as decisões e entregas de um pedido específico.</p>
  <form method="get" action="/admin/orders" class="row g-2 align-items-end mb-2"><div class="col-md-4"><label class="form-label" for="audit-order">Pedido</label><input id="audit-order" class="form-control" type="number" name="order_id" min="1" value="<?= (int) ($selectedOrderId ?? 0) ?>"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Carregar histórico</button></div></form>
  <?php if (($orderAuditTrail ?? []) === []): ?><p class="text-secondary mb-0">Seleccione um pedido para consultar o histórico.</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($orderAuditTrail as $entry): ?><li class="list-group-item px-0"><strong><?= htmlspecialchars((string) ($entry['action'] ?? '')) ?></strong><span class="text-secondary"> · <?= htmlspecialchars((string) ($entry['created_at'] ?? '')) ?></span><div class="small text-secondary mt-1"><?= htmlspecialchars((string) ($entry['payload_json'] ?? '{}')) ?></div></li><?php endforeach; ?></ul><?php endif; ?>
</div>

<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div class="page-intro">
    <h1 class="section-title h3">Factura <?= htmlspecialchars((string) ($invoice['invoice_number'] ?? '')) ?></h1>
    <p>Detalhes completos do pedido e estado financeiro associado.</p>
  </div>
</div>

<div class="card p-3 mb-3">
  <div class="row g-3">
    <div class="col-md-4"><strong>ID:</strong> #<?= (int) ($invoice['id'] ?? 0) ?></div>
    <div class="col-md-4"><strong>Pedido:</strong> #<?= (int) ($invoice['order_id'] ?? 0) ?></div>
    <div class="col-md-4"><strong>Status factura:</strong> <?= $badge((string) ($invoice['status'] ?? 'pending')) ?></div>
    <div class="col-md-6"><strong>Tema:</strong> <?= htmlspecialchars((string) ($invoice['title_or_theme'] ?? '-')) ?></div>
    <div class="col-md-6"><strong>Tipo de trabalho:</strong> <?= htmlspecialchars((string) ($invoice['work_type_name'] ?? '-')) ?></div>
    <div class="col-md-4"><strong>Valor:</strong> <?= $formatMoney($invoice['amount'] ?? 0) ?></div>
    <div class="col-md-4"><strong>Moeda:</strong> <?= htmlspecialchars((string) ($invoice['currency'] ?? 'MZN')) ?></div>
    <div class="col-md-4"><strong>Status pagamento:</strong> <?= $badge((string) ($invoice['payment_status'] ?? 'pending')) ?></div>
    <div class="col-md-4"><strong>Método:</strong> <?= htmlspecialchars((string) ($invoice['payment_method'] ?? '-')) ?></div>
    <div class="col-md-4"><strong>Provedor:</strong> <?= htmlspecialchars((string) ($invoice['provider'] ?? '-')) ?></div>
    <div class="col-md-4"><strong>Referência:</strong> <?= htmlspecialchars((string) ($invoice['internal_reference'] ?? '-')) ?></div>
    <div class="col-md-6"><strong>Emitida em:</strong> <?= htmlspecialchars((string) ($invoice['issued_at'] ?? $invoice['created_at'] ?? '-')) ?></div>
    <div class="col-md-6"><strong>Pago em:</strong> <?= htmlspecialchars((string) ($invoice['paid_at'] ?? '-')) ?></div>
  </div>
</div>

<div class="d-flex gap-2">
  <?php if (!empty($isAdminInvoiceView)): ?>
    <a class="btn btn-outline-secondary" href="/admin/payments">Voltar pagamentos</a>
  <?php else: ?>
    <a class="btn btn-outline-secondary" href="/invoices">Voltar facturas</a>
    <a class="btn btn-primary" href="/invoices/<?= (int) ($invoice['id'] ?? 0) ?>/pdf">Baixar PDF</a>
  <?php endif; ?>
</div>

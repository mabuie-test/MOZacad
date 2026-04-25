<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div class="page-intro">
    <h1 class="section-title h3">Pagamento do Pedido #<?= (int) $order['id'] ?></h1>
    <p>Conclua o pagamento para avançar do estado <em>pending_payment</em> para execução activa.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Resumo financeiro</h2>
      <div class="status-card mb-2"><small>Valor total</small><div class="fw-semibold"><?= $formatMoney($order['final_price'] ?? 0) ?></div></div>
      <div class="status-card mb-2"><small>Status do pedido</small><div><?= $badge((string) $order['status']) ?></div></div>
      <div class="status-card"><small>Status do pagamento aberto</small><div><?= $badge((string) ($openPayment['status'] ?? 'pending')) ?></div></div>
      <div class="form-hint mt-2">Se já iniciou pagamento e está em processamento, aguarde alguns instantes e volte ao detalhe do pedido.</div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-4 h-100 cta-card">
      <h2 class="h5">Iniciar pagamento M-Pesa</h2>
      <form method="post" action="/orders/<?= (int) $order['id'] ?>/pay" data-order-pay>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <label class="form-label">Número M-Pesa (MSISDN)</label>
        <input class="form-control mb-2" name="msisdn" placeholder="84xxxxxxx" required>
        <div class="form-hint mb-3">Confirme que o número está activo para receber o pedido de confirmação no telemóvel.</div>
        <button type="submit" class="btn btn-primary w-100">Iniciar pagamento</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header">
  <div>
    <h1 class="section-title h3">Pagamento do Pedido #<?= (int) $order['id'] ?></h1>
    <p>Conclua o pagamento para desbloquear a execução académica.</p>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Resumo financeiro</h2>
      <div class="status-card mb-2"><strong>Valor:</strong> <?= $formatMoney($order['final_price'] ?? 0) ?></div>
      <div class="status-card mb-2"><strong>Status do pedido:</strong> <?= $badge((string) $order['status']) ?></div>
      <div class="status-card"><strong>Status do pagamento:</strong> <?= $badge((string) ($openPayment['status'] ?? 'pending')) ?></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-4 h-100">
      <h2 class="h5">Iniciar M-Pesa</h2>
      <form method="post" action="/orders/<?= (int) $order['id'] ?>/pay" data-order-pay>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <label class="form-label">Número M-Pesa (MSISDN)</label>
        <input class="form-control mb-2" name="msisdn" placeholder="84xxxxxxx" required>
        <div class="form-hint mb-3">Após submissão, acompanhe os estados em Pedidos e Facturas.</div>
        <button type="submit" class="btn btn-primary w-100">Iniciar pagamento</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</div>

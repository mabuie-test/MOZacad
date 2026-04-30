<div class="kbd-list mb-3">
  <div class="metric"><small>Pedidos pendentes pagamento</small><div class="value"><?= (int) ($overview['orders_pending_payment'] ?? 0) ?></div></div>
  <div class="metric"><small>Pedidos em revisão</small><div class="value"><?= (int) ($overview['orders_under_review'] ?? 0) ?></div></div>
  <div class="metric"><small>Pagamentos com risco</small><div class="value"><?= (int) ($overview['payments_failed'] ?? 0) ?></div></div>
  <div class="metric"><small>Fila humana sem atribuição</small><div class="value"><?= (int) ($overview['queue_unassigned'] ?? 0) ?></div></div>
  <div class="metric"><small>Exceções ativas</small><div class="value"><?= (int) ($overview['exceptions_active'] ?? 0) ?></div></div>
  <div class="metric"><small>Bloqueios por risco/compliance</small><div class="value"><?= (int) ($overview['exceptions_blocking_delivery'] ?? 0) ?></div></div>
</div>


<?php $workerHealth = (array) ($overview['worker_health'] ?? []); ?>
<?php $aiFallbackRates = (array) ($overview['ai_provider_fallback_rates'] ?? []); ?>
<?php $aiPreflight = (array) ($overview['ai_preflight'] ?? []); ?>
<div class="card p-3 mb-3">
  <h2 class="h6">Saúde da fila assíncrona</h2>
  <div class="row g-3">
    <div class="col-md-3"><small>Último heartbeat</small><div><?= htmlspecialchars((string) ($workerHealth['last_heartbeat_at'] ?? 'n/d')) ?></div></div>
    <div class="col-md-3"><small>Minutos sem heartbeat</small><div><?= (int) ($workerHealth['minutes_since_last_heartbeat'] ?? 0) ?></div></div>
    <div class="col-md-2"><small>Jobs queued</small><div><?= (int) ($workerHealth['queued_jobs'] ?? 0) ?></div></div>
    <div class="col-md-2"><small>Atraso da fila (min)</small><div><?= (int) ($workerHealth['queue_lag_minutes'] ?? 0) ?></div></div>
    <div class="col-md-2"><small>Alerta</small><div><?= !empty($workerHealth['stale_alert']) ? '<span class="badge bg-danger">STALE</span>' : '<span class="badge bg-success">OK</span>' ?></div></div>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h6">Taxa de fallback por provider (IA)</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Provider</th><th>Usos totais</th><th>Usos via fallback</th><th>Taxa fallback</th></tr></thead>
      <tbody>
      <?php if ($aiFallbackRates === []): ?>
        <tr><td colspan="4" class="text-secondary">Sem dados de fallback registados.</td></tr>
      <?php else: ?>
        <?php foreach ($aiFallbackRates as $metric): ?>
          <tr>
            <td><?= htmlspecialchars((string) ($metric['provider'] ?? 'unknown')) ?></td>
            <td><?= (int) ($metric['total_used'] ?? 0) ?></td>
            <td><?= (int) ($metric['fallback_used'] ?? 0) ?></td>
            <td><?= number_format((float) ($metric['fallback_rate_pct'] ?? 0), 2) ?>%</td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h6">Preflight IA (produção)</h2>
  <div class="mb-2">
    <small>Status geral</small>
    <div>
      <?php $pStatus = (string) ($aiPreflight['status'] ?? 'critical'); ?>
      <?= $pStatus === 'ok' ? '<span class="badge bg-success">OK</span>' : ($pStatus === 'degraded' ? '<span class="badge bg-warning text-dark">DEGRADED</span>' : '<span class="badge bg-danger">CRITICAL</span>') ?>
      <small class="text-secondary ms-2">Último check: <?= htmlspecialchars((string) ($aiPreflight['last_check_at'] ?? 'n/d')) ?></small>
    </div>
    <div class="text-secondary small mt-1"><?= htmlspecialchars((string) ($aiPreflight['message'] ?? '')) ?></div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Alvo</th><th>Status</th><th>Detalhe</th></tr></thead>
      <tbody>
      <?php foreach ((array) ($aiPreflight['providers'] ?? []) as $name => $result): ?>
        <tr><td>Provider: <?= htmlspecialchars((string) $name) ?></td><td><?= !empty($result['ok']) ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FAIL</span>' ?></td><td><?= htmlspecialchars((string) ($result['error_type'] ?? $result['result'] ?? '-')) ?></td></tr>
      <?php endforeach; ?>
      <?php foreach ((array) ($aiPreflight['models'] ?? []) as $name => $result): ?>
        <tr><td>Modelo: <?= htmlspecialchars((string) $name) ?></td><td><?= !empty($result['ok']) ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FAIL</span>' ?></td><td><?= htmlspecialchars((string) ($result['error_type'] ?? $result['result'] ?? '-')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-4"><div class="card p-3 h-100"><h2 class="h6">Ações rápidas</h2><div class="d-grid gap-2"><a class="btn btn-sm btn-outline-primary" href="/admin/human-review">Abrir fila humana</a><a class="btn btn-sm btn-outline-primary" href="/admin/orders?order_status=pending_payment">Ver pedidos pendentes</a><a class="btn btn-sm btn-outline-primary" href="/admin/pricing">Revisar pricing</a></div></div></div>
  <div class="col-lg-8"><div class="card p-3 h-100"><h2 class="h6">Sinais de risco operacional</h2><ul class="text-secondary mb-0"><li>Pagamentos falhados/cancelados exigem acção de suporte.</li><li>Fila de revisão humana sem revisor aumenta tempo de entrega.</li><li>Pedidos em revisão prolongada podem impactar satisfação e SLA.</li></ul></div></div>
</div>

<div class="card p-3">
  <h2 class="h5">Últimos pedidos críticos</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Tema</th><th>Utilizador</th><th>Status</th><th>Preço</th></tr></thead>
      <tbody>
      <?php foreach (array_slice((array) ($orders ?? []), 0, 12) as $order): ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td><?= htmlspecialchars((string) ($order['title_or_theme'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($order['user_email'] ?? '-')) ?></td>
          <td><?= $badge((string) ($order['status'] ?? 'draft')) ?></td>
          <td><?= $formatMoney($order['final_price'] ?? 0) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

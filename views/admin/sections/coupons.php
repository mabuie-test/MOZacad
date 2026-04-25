<div class="card p-3">
  <h2 class="h5">Cupões promocionais (inspecção)</h2>
  <p class="text-secondary">Leitura operacional de cupões activos/inactivos, uso e janela de validade.</p>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Código</th><th>Tipo</th><th>Valor</th><th>Uso</th><th>Janela</th><th>Estado</th></tr></thead>
      <tbody>
      <?php foreach (($coupons ?? []) as $c): ?>
        <tr>
          <td><code><?= htmlspecialchars((string) $c['code']) ?></code></td>
          <td><?= htmlspecialchars((string) ($c['discount_type'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($c['discount_value'] ?? '-')) ?></td>
          <td><?= (int) ($c['used_count'] ?? 0) ?> / <?= htmlspecialchars((string) ($c['usage_limit'] ?? '∞')) ?></td>
          <td><?= htmlspecialchars((string) ($c['starts_at'] ?? '-')) ?> → <?= htmlspecialchars((string) ($c['ends_at'] ?? '-')) ?></td>
          <td><?= !empty($c['is_active']) ? 'Activo' : 'Inactivo' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

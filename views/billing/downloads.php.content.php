<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="page-header"><div class="page-intro"><h1 class="section-title h3">Downloads</h1><p>Aceda aos documentos finais por versão com histórico e estado de entrega.</p></div></div>

<div class="card p-3">
  <?php if (empty($documents)): ?>
    <?= $emptyState('Sem documentos disponíveis', 'Quando um pedido estiver em estado ready, os ficheiros ficarão disponíveis aqui.') ?>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Pedido</th><th>Título</th><th>Versão</th><th>Status</th><th>Acção</th></tr></thead>
        <tbody>
          <?php foreach (($documents ?? []) as $doc): ?>
            <tr>
              <td>#<?= (int) $doc['order_id'] ?></td>
              <td><?= htmlspecialchars((string) $doc['title_or_theme']) ?><span class="muted-meta">Gerado em <?= htmlspecialchars((string) ($doc['created_at'] ?? '-')) ?></span></td>
              <td>v<?= (int) $doc['version'] ?></td>
              <td><?= $badge((string) $doc['status']) ?></td>
              <td><a href="/downloads/<?= (int) $doc['id'] ?>" class="btn btn-sm btn-outline-primary">Descarregar</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

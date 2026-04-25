<div class="card p-3 mb-3">
  <h2 class="h5">Normas e templates institucionais</h2>
  <p class="text-secondary mb-0">Módulo de inspecção operacional da camada institucional: norma.txt, norma.pdf, metadata.json, overrides e templates por tipo de trabalho.</p>
</div>

<?php foreach (($normMatrix ?? []) as $row): $inst = $row['institution']; $norm = $row['norm']; $metadata = $norm['metadata'] ?? []; ?>
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h3 class="h6 mb-1"><?= htmlspecialchars((string) $inst['name']) ?></h3>
        <small class="text-secondary">Fonte activa: <?= htmlspecialchars((string) ($norm['source'] ?? 'none')) ?> · Referência: <?= htmlspecialchars((string) ($norm['reference_style'] ?? '-')) ?></small>
      </div>
      <div class="d-flex gap-2">
        <span class="badge text-bg-<?= $norm['txt_path'] ? 'success' : 'secondary' ?>">norma.txt</span>
        <span class="badge text-bg-<?= $norm['pdf_path'] ? 'success' : 'secondary' ?>">norma.pdf</span>
        <span class="badge text-bg-<?= $norm['metadata_path'] ? 'success' : 'secondary' ?>">metadata.json</span>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-4"><div class="status-card"><small>Faculty</small><div><?= htmlspecialchars((string) ($metadata['faculty'] ?? '-')) ?></div></div></div>
      <div class="col-md-4"><div class="status-card"><small>Department</small><div><?= htmlspecialchars((string) ($metadata['department'] ?? '-')) ?></div></div></div>
      <div class="col-md-4"><div class="status-card"><small>Notes</small><div><?= is_array($norm['notes'] ?? null) ? count($norm['notes']) : 0 ?> item(ns)</div></div></div>
    </div>

    <details class="mt-2">
      <summary class="small text-secondary">Ver metadata e overrides</summary>
      <pre class="small bg-light border rounded p-2 mt-2 mb-0"><?= htmlspecialchars(json_encode([
        'visual_overrides' => $norm['visual_overrides'] ?? [],
        'front_page_overrides' => $norm['front_page_overrides'] ?? [],
        'structure_overrides' => $norm['structure_overrides'] ?? [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Tipo de trabalho</th><th>Template activo</th><th>Modo</th><th>Observação</th></tr></thead>
        <tbody>
        <?php foreach (($row['templates'] ?? []) as $tpl): ?>
          <tr>
            <td><?= htmlspecialchars((string) $tpl['work_type']['name']) ?></td>
            <td><?= htmlspecialchars((string) ($tpl['state']['selected_template'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string) ($tpl['state']['mode'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string) ($tpl['state']['reason'] ?? '-')) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>

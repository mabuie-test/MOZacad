<div class="card p-3 mb-3">
  <h2 class="h5">Normas e templates institucionais</h2>
  <p class="text-secondary mb-2">Escopo actual: <strong>inspecção operacional + diagnóstico de prontidão</strong>. Esta secção não altera ficheiros fonte (<code>norma.txt</code>, <code>norma.pdf</code>, <code>metadata.json</code>) nem cria templates novos.</p>
  <p class="small text-secondary mb-2">Modo do módulo: <code><?= htmlspecialchars((string) ($templatesOperationalMode ?? 'read_only_diagnostic')) ?></code>.</p>
  <ul class="small mb-0 text-secondary">
    <li><strong>Gestão disponível aqui:</strong> validação de disponibilidade, origem efectiva e modo de resolução por instituição/tipo.</li>
    <li><strong>Gestão fora desta secção:</strong> publicação física de normas/templates no storage/repositório documental.</li>
  </ul>
</div>

<?php foreach (($normMatrix ?? []) as $row): $inst = $row['institution']; $norm = $row['norm']; $metadata = $norm['metadata'] ?? []; ?>
  <?php
    $templateRows = $row['templates'] ?? [];
    $total = count($templateRows);
    $resolved = count(array_filter($templateRows, static fn(array $tpl): bool => !empty($tpl['state']['selected_template'])));
    $fallback = count(array_filter($templateRows, static fn(array $tpl): bool => (string) ($tpl['state']['mode'] ?? '') === 'programmatic_fallback'));
    $readiness = $total > 0 ? round(($resolved / $total) * 100) : 0;
  ?>
  <div class="card p-3 mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h3 class="h6 mb-1"><?= htmlspecialchars((string) $inst['name']) ?></h3>
        <small class="text-secondary">Origem activa: <?= htmlspecialchars((string) ($norm['source'] ?? 'none')) ?> · Referência: <?= htmlspecialchars((string) ($norm['reference_style'] ?? '-')) ?></small>
      </div>
      <div class="d-flex gap-2">
        <span class="badge text-bg-<?= $norm['txt_path'] ? 'success' : 'secondary' ?>">norma.txt</span>
        <span class="badge text-bg-<?= $norm['pdf_path'] ? 'success' : 'secondary' ?>">norma.pdf</span>
        <span class="badge text-bg-<?= $norm['metadata_path'] ? 'success' : 'secondary' ?>">metadata.json</span>
      </div>
    </div>

    <div class="row g-2 mt-2">
      <div class="col-md-3"><div class="status-card"><small>Prontidão templates</small><div><?= $readiness ?>%</div></div></div>
      <div class="col-md-3"><div class="status-card"><small>Tipos resolvidos</small><div><?= $resolved ?>/<?= $total ?></div></div></div>
      <div class="col-md-3"><div class="status-card"><small>Em fallback</small><div><?= $fallback ?></div></div></div>
      <div class="col-md-3"><div class="status-card"><small>Notas normativas</small><div><?= is_array($norm['notes'] ?? null) ? count($norm['notes']) : 0 ?></div></div></div>
    </div>

    <details class="mt-2">
      <summary class="small text-secondary">Ver metadata e overrides efectivos (somente leitura)</summary>
      <pre class="small bg-light border rounded p-2 mt-2 mb-0"><?= htmlspecialchars(json_encode([
        'metadata' => [
          'faculty' => $metadata['faculty'] ?? null,
          'department' => $metadata['department'] ?? null,
          'reference_style' => $metadata['reference_style'] ?? null,
        ],
        'visual_overrides' => $norm['visual_overrides'] ?? [],
        'front_page_overrides' => $norm['front_page_overrides'] ?? [],
        'structure_overrides' => $norm['structure_overrides'] ?? [],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Tipo de trabalho</th><th>Template activo</th><th>Modo</th><th>Diagnóstico operacional</th></tr></thead>
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

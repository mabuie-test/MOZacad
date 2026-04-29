<div class="card p-3 mb-3">
  <h2 class="h5">Normas e templates institucionais</h2>
  <p class="text-secondary mb-2">Escopo actual: <strong>inspecção operacional + publicação auditável</strong> de normas e templates institucionais.</p>
  <p class="small text-secondary mb-2">Modo do módulo: <code><?= htmlspecialchars((string) ($templatesOperationalMode ?? 'publishable')) ?></code>.</p>
  <ul class="small mb-0 text-secondary">
    <li><strong>Gestão disponível aqui:</strong> validação de disponibilidade, origem efectiva, upload e publicação auditável.</li>
    <li><strong>Validações:</strong> MIME, tamanho e naming seguro para artefactos institucionais.</li>
  </ul>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h3 class="h6">Publicar normas institucionais</h3>
      <form method="post" action="/admin/templates/norms" enctype="multipart/form-data" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-12">
          <select class="form-select" name="institution_id" required>
            <option value="">Instituição</option>
            <?php foreach (($institutions ?? []) as $i): ?>
              <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12"><label class="form-label small mb-1">norma.txt</label><input type="file" class="form-control" name="norm_txt" accept=".txt,text/plain"></div>
        <div class="col-12"><label class="form-label small mb-1">norma.pdf</label><input type="file" class="form-control" name="norm_pdf" accept=".pdf,application/pdf"></div>
        <div class="col-12"><label class="form-label small mb-1">metadata.json</label><input type="file" class="form-control" name="norm_metadata" accept=".json,application/json,text/plain"></div>
        <div class="col-12"><button class="btn btn-primary">Publicar normas</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h3 class="h6">Publicar template por tipo</h3>
      <form method="post" action="/admin/templates/work-type" enctype="multipart/form-data" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-md-6">
          <select class="form-select" name="institution_id" required>
            <option value="">Instituição</option>
            <?php foreach (($institutions ?? []) as $i): ?>
              <option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <select class="form-select" name="work_type_id" required>
            <option value="">Tipo de trabalho</option>
            <?php foreach (($workTypes ?? []) as $w): ?>
              <option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars((string) $w['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12"><label class="form-label small mb-1">Template DOCX</label><input type="file" class="form-control" name="template_docx" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></div>
        <div class="col-12"><button class="btn btn-outline-primary">Publicar template</button></div>
      </form>
    </div>
  </div>
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
        <?php if ((string) ($norm['source'] ?? '') === 'pdf_unparsed' || trim((string) ($norm['content'] ?? '')) === ''): ?>
          <div class="alert alert-warning py-1 px-2 mt-2 mb-0 small">⚠️ norma ativa sem texto parseado</div>
        <?php endif; ?>
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

<div class="card p-3">
  <h3 class="h6">Lifecycle de artefactos (publicação/rollback)</h3>
  <p class="small text-secondary">Active uma versão anterior para rollback operacional sem reupload.</p>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>ID</th><th>Escopo</th><th>Tipo</th><th>Ficheiro</th><th>Estado</th><th>Publicado por</th><th>Ação</th></tr></thead>
      <tbody>
      <?php foreach (($templateArtifacts ?? []) as $artifact): ?>
        <tr>
          <td>#<?= (int) $artifact['id'] ?></td>
          <td>
            <?= htmlspecialchars((string) ($artifact['institution_name'] ?? '-')) ?>
            <div class="muted-meta"><?= htmlspecialchars((string) ($artifact['work_type_name'] ?? 'global')) ?></div>
          </td>
          <td><code><?= htmlspecialchars((string) ($artifact['artifact_type'] ?? '-')) ?></code></td>
          <td><small><?= htmlspecialchars((string) basename((string) ($artifact['file_path'] ?? ''))) ?></small></td>
          <td><?= !empty($artifact['is_active']) ? '<span class="badge text-bg-success">published</span>' : '<span class="badge text-bg-secondary">archived</span>' ?></td>
          <td><?= htmlspecialchars((string) ($artifact['actor_name'] ?? '-')) ?><div class="muted-meta"><?= htmlspecialchars((string) ($artifact['created_at'] ?? '-')) ?></div></td>
          <td>
            <?php if (empty($artifact['is_active'])): ?>
              <form method="post" action="/admin/templates/artifacts/<?= (int) $artifact['id'] ?>/activate">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
                <button class="btn btn-sm btn-outline-primary">Tornar ativo</button>
              </form>
            <?php else: ?>
              <span class="text-secondary small">Atual</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

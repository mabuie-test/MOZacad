<?php
$decode = static function (?string $raw): array {
  if ($raw === null || trim($raw) === '') return [];
  $decoded = json_decode($raw, true);
  return is_array($decoded) ? $decoded : [];
};
?>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Regra global por instituição</h2>
      <p class="text-secondary small">Define referência, directrizes de front page e notas institucionais gerais.</p>
      <form method="post" action="/admin/institution-rules" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-12"><select class="form-select" name="institution_id" required><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><input class="form-control" name="references_style" value="APA" placeholder="APA/ABNT"></div>
        <div class="col-md-8"><input class="form-control" name="notes" placeholder="Notas globais"></div>
        <div class="col-12"><label class="form-label small mb-1">Front page (um item por linha)</label><textarea class="form-control" rows="3" name="front_page_overrides" placeholder="Ex: incluir supervisor\nEx: ordem institucional de elementos"></textarea></div>
        <div class="col-12"><label class="form-label small mb-1">Overrides visuais globais</label><textarea class="form-control" rows="3" name="visual_overrides" placeholder="Ex: corpo em Times New Roman 12\nEx: espaçamento 1.5"></textarea></div>
        <div class="col-12"><label class="form-label small mb-1">Overrides estruturais globais</label><textarea class="form-control" rows="3" name="structure_overrides" placeholder="Ex: capítulo de dedicatória obrigatório"></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Guardar regra institucional</button></div>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Regra por instituição + tipo de trabalho</h2>
      <p class="text-secondary small">Use campos orientados; o sistema gera JSON internamente.</p>
      <form method="post" action="/admin/institution-work-type-rules" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-md-6"><select class="form-select" name="institution_id" required><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><select class="form-select" name="work_type_id" required><option value="">Tipo de trabalho</option><?php foreach (($workTypes ?? []) as $w): ?><option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars((string) $w['name']) ?></option><?php endforeach; ?></select></div>

        <div class="col-12"><label class="form-label small mb-1">Estrutura (secções em linhas)</label><textarea class="form-control" rows="3" name="structure_sections" placeholder="Introdução\nEnquadramento teórico\nMetodologia"></textarea></div>
        <div class="col-12"><label class="form-label small mb-1">Estrutura (elementos obrigatórios)</label><textarea class="form-control" rows="2" name="structure_required_elements" placeholder="Resumo bilingue\nLista de abreviaturas"></textarea></div>

        <div class="col-md-4"><input class="form-control" name="visual_font_family" placeholder="Fonte (ex: Times New Roman)"></div>
        <div class="col-md-4"><input class="form-control" name="visual_font_size" placeholder="Tamanho (ex: 12)"></div>
        <div class="col-md-4"><input class="form-control" name="visual_line_spacing" placeholder="Espaçamento (ex: 1.5)"></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="visual_rules" placeholder="Regras visuais adicionais (uma por linha)"></textarea></div>

        <div class="col-md-4"><input class="form-control" name="reference_style" placeholder="Estilo de referência"></div>
        <div class="col-md-4"><input class="form-control" name="reference_sources_min" placeholder="Mínimo de fontes"></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="reference_rules" placeholder="Regras de referências (uma por linha)"></textarea></div>

        <div class="col-12"><textarea class="form-control" rows="2" name="notes" placeholder="Notas da regra específica"></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Guardar regra por tipo</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h5">Regras institucionais existentes (globais)</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Instituição</th><th>Referências</th><th>Front page / Visual / Estrutura</th><th>Notas</th><th>Atualizado</th></tr></thead>
      <tbody>
      <?php foreach (($institutionRules ?? []) as $r): $fp = $decode($r['front_page_rules_json'] ?? null); ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r['institution_name'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($r['references_style'] ?? '-')) ?></td>
          <td>
            <small class="d-block"><strong>Front:</strong> <?= htmlspecialchars((string) implode(' • ', $fp['front_page_overrides'] ?? [])) ?: '-' ?></small>
            <small class="d-block"><strong>Visual:</strong> <?= htmlspecialchars((string) implode(' • ', $fp['visual_overrides'] ?? [])) ?: '-' ?></small>
            <small class="d-block"><strong>Estrutura:</strong> <?= htmlspecialchars((string) implode(' • ', $fp['structure_overrides'] ?? [])) ?: '-' ?></small>
          </td>
          <td><?= htmlspecialchars((string) ($r['notes'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($r['updated_at'] ?? '-')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card p-3">
  <h2 class="h5">Regras por instituição/tipo de trabalho</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Instituição</th><th>Tipo de trabalho</th><th>Estrutura</th><th>Visual</th><th>Referências</th><th>Notas</th></tr></thead>
      <tbody>
      <?php foreach (($institutionWorkTypeRules ?? []) as $r): $structure = $decode($r['custom_structure_json'] ?? null); $visual = $decode($r['custom_visual_rules_json'] ?? null); $reference = $decode($r['custom_reference_rules_json'] ?? null); ?>
        <tr>
          <td><?= htmlspecialchars((string) ($r['institution_name'] ?? '-')) ?></td>
          <td><?= htmlspecialchars((string) ($r['work_type_name'] ?? '-')) ?></td>
          <td><small><?= htmlspecialchars((string) implode(' • ', $structure['sections'] ?? [])) ?: '-' ?></small></td>
          <td><small><?= htmlspecialchars((string) implode(' • ', array_filter([
            !empty($visual['font_family']) ? ('Fonte: ' . $visual['font_family']) : null,
            !empty($visual['font_size']) ? ('Tam: ' . $visual['font_size']) : null,
            !empty($visual['line_spacing']) ? ('Espaço: ' . $visual['line_spacing']) : null,
            !empty($visual['extra_rules']) ? implode(' • ', (array) $visual['extra_rules']) : null,
          ]))) ?: '-' ?></small></td>
          <td><small><?= htmlspecialchars((string) implode(' • ', array_filter([
            !empty($reference['style']) ? ('Estilo: ' . $reference['style']) : null,
            !empty($reference['sources_min']) ? ('Mín fontes: ' . $reference['sources_min']) : null,
            !empty($reference['rules']) ? implode(' • ', (array) $reference['rules']) : null,
          ]))) ?: '-' ?></small></td>
          <td><?= htmlspecialchars((string) ($r['notes'] ?? '-')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

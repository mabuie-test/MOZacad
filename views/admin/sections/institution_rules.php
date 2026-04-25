<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Institution rules (globais)</h2>
      <form method="post" action="/admin/institution-rules" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-12"><select class="form-select" name="institution_id" required><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><input class="form-control" name="references_style" value="APA" placeholder="APA/ABNT"></div>
        <div class="col-md-8"><input class="form-control" name="front_page_overrides" placeholder="Front-page overrides"></div>
        <div class="col-12"><input class="form-control" name="visual_overrides" placeholder="Visual overrides"></div>
        <div class="col-12"><textarea class="form-control" rows="3" name="notes" placeholder="Notas institucionais e regras textuais"></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Guardar regra institucional</button></div>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card p-3 h-100">
      <h2 class="h5">Institution + Work type rules</h2>
      <form method="post" action="/admin/institution-work-type-rules" class="row g-2">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="col-md-6"><select class="form-select" name="institution_id" required><option value="">Instituição</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><select class="form-select" name="work_type_id" required><option value="">Tipo de trabalho</option><?php foreach (($workTypes ?? []) as $w): ?><option value="<?= (int) $w['id'] ?>"><?= htmlspecialchars((string) $w['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="custom_structure_json" placeholder="custom_structure_json"></textarea></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="custom_visual_rules_json" placeholder="custom_visual_rules_json"></textarea></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="custom_reference_rules_json" placeholder="custom_reference_rules_json"></textarea></div>
        <div class="col-12"><textarea class="form-control" rows="2" name="notes" placeholder="Notas"></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Guardar regra por tipo</button></div>
      </form>
    </div>
  </div>
</div>

<div class="card p-3 mb-3">
  <h2 class="h5">Regras institucionais existentes</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Instituição</th><th>Referências</th><th>Notas</th><th>Atualizado</th></tr></thead>
      <tbody><?php foreach (($institutionRules ?? []) as $r): ?><tr><td><?= htmlspecialchars((string) ($r['institution_name'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($r['references_style'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($r['notes'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($r['updated_at'] ?? '-')) ?></td></tr><?php endforeach; ?></tbody>
    </table>
  </div>
</div>

<div class="card p-3">
  <h2 class="h5">Regras por instituição/tipo de trabalho</h2>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th>Instituição</th><th>Tipo de trabalho</th><th>Estrutura</th><th>Visual</th><th>Referências</th></tr></thead>
      <tbody><?php foreach (($institutionWorkTypeRules ?? []) as $r): ?><tr><td><?= htmlspecialchars((string) ($r['institution_name'] ?? '-')) ?></td><td><?= htmlspecialchars((string) ($r['work_type_name'] ?? '-')) ?></td><td><code><?= htmlspecialchars((string) ($r['custom_structure_json'] ?? '-')) ?></code></td><td><code><?= htmlspecialchars((string) ($r['custom_visual_rules_json'] ?? '-')) ?></code></td><td><code><?= htmlspecialchars((string) ($r['custom_reference_rules_json'] ?? '-')) ?></code></td></tr><?php endforeach; ?></tbody>
    </table>
  </div>
</div>

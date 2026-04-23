<div class="page-header">
  <div>
    <h1 class="section-title h3">Novo pedido académico</h1>
    <p>Formulário estruturado para pricing exacto e execução robusta.</p>
  </div>
</div>

<form method="post" action="/orders" enctype="multipart/form-data" data-order-create>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">

  <div class="row g-3">
    <div class="col-xl-8 d-grid gap-3">
      <div class="card p-4">
        <h2 class="h5 mb-3">Contexto académico</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Instituição</label><select class="form-select" name="institution_id" required><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Curso</label><select class="form-select" name="course_id" required><?php foreach (($courses ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Disciplina</label><select class="form-select" name="discipline_id" required><?php foreach (($disciplines ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Nível académico</label><select class="form-select" name="academic_level_id" required><?php foreach (($academic_levels ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>

      <div class="card p-4">
        <h2 class="h5 mb-3">Conteúdo do trabalho</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Tipo de trabalho</label><select class="form-select" name="work_type_id" required><?php foreach (($work_types ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Volume (páginas)</label><input type="number" min="1" name="target_pages" class="form-control" required><div class="form-hint">Influência directa no preço final.</div></div>
          <div class="col-12"><label class="form-label">Tema</label><input name="title_or_theme" class="form-control" placeholder="Ex.: Impacto da literacia financeira..." required></div>
          <div class="col-12"><label class="form-label">Objectivo geral</label><textarea name="general_objective" class="form-control" rows="3" placeholder="Descreva claramente o objectivo principal"></textarea></div>
          <div class="col-md-6"><label class="form-label">Prazo</label><input type="datetime-local" name="deadline_date" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Complexidade</label><select class="form-select" name="complexity_level"><option value="low">Baixa</option><option value="medium" selected>Média</option><option value="high">Alta</option><option value="very_high">Muito alta</option></select></div>
          <div class="col-12"><label class="form-label">Anexos de apoio</label><input type="file" name="attachments[]" class="form-control" multiple><div class="form-hint">Inclua regulamentos, referências, templates ou documentos base.</div></div>
        </div>
      </div>
    </div>

    <div class="col-xl-4 d-grid gap-3">
      <div class="card p-4">
        <h2 class="h5">Extras do serviço</h2>
        <?php foreach (['needs_institution_cover' => 'Capa institucional', 'needs_bilingual_abstract' => 'Abstract bilingue', 'needs_methodology_review' => 'Revisão metodológica', 'needs_humanized_revision' => 'Revisão humanizada', 'needs_slides' => 'Slides', 'needs_defense_summary' => 'Resumo para defesa'] as $name => $label): ?>
          <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="<?= $name ?>" value="1"><span class="form-check-label"><?= $label ?></span></label>
        <?php endforeach; ?>
      </div>

      <div class="card p-4">
        <h2 class="h5">Cupão e submissão</h2>
        <label class="form-label">Cupão promocional</label>
        <input name="coupon_code" class="form-control mb-3" placeholder="Ex: MZ-ALUNO10">
        <div class="status-card mb-3">
          <strong>Nota:</strong> O preço final será calculado no backend com base nas regras activas de pricing.
        </div>
        <button type="submit" class="btn btn-primary w-100">Criar pedido</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </div>
    </div>
  </div>
</form>

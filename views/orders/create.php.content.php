<div class="page-header">
  <div class="page-intro">
    <h1 class="section-title h3">Novo pedido académico</h1>
    <p>Preencha este onboarding em etapas para gerar um briefing completo, pricing consistente e execução sem retrabalho.</p>
  </div>
</div>

<div class="order-stepper">
  <span class="step-pill">1. Contexto académico</span>
  <span class="step-pill">2. Escopo e briefing</span>
  <span class="step-pill">3. Anexos e extras</span>
  <span class="step-pill">4. Revisão final</span>
</div>

<form method="post" action="/orders" enctype="multipart/form-data" data-order-create>
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">

  <div class="row g-3">
    <div class="col-xl-8 d-grid gap-3">
      <div class="card p-4">
        <div class="form-section-title">Contexto institucional</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Instituição</label><select class="form-select" name="institution_id" data-institution-select required><option value="">Selecione...</option><?php foreach (($institutions ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Curso</label><select class="form-select" name="course_id" data-course-select required><option value="">Selecione a instituição</option></select></div>
          <div class="col-md-6"><label class="form-label">Disciplina</label><select class="form-select" name="discipline_id" data-discipline-select required><option value="">Selecione o curso</option></select></div>
          <div class="col-md-6"><label class="form-label">Nível académico</label><select class="form-select" name="academic_level_id" required><?php foreach (($academic_levels ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>

      <div class="card p-4">
        <div class="form-section-title">Escopo, objectivos e narrativa</div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Tipo de trabalho</label><select class="form-select" name="work_type_id" required><?php foreach (($work_types ?? []) as $i): ?><option value="<?= (int) $i['id'] ?>"><?= htmlspecialchars((string) $i['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-3"><label class="form-label">Páginas alvo</label><input type="number" min="1" name="target_pages" class="form-control" data-pages-input required></div>
          <div class="col-md-3"><label class="form-label">Complexidade</label><select class="form-select" name="complexity_level" data-complexity-input><option value="low">Baixa</option><option value="medium" selected>Média</option><option value="high">Alta</option><option value="very_high">Muito alta</option></select></div>
          <div class="col-12"><label class="form-label">Tema / título</label><input name="title_or_theme" class="form-control" placeholder="Ex.: Impacto da literacia financeira" required></div>
          <div class="col-md-6"><label class="form-label">Prazo</label><input type="datetime-local" name="deadline_date" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">Subtítulo</label><input name="subtitle" class="form-control" placeholder="Opcional"></div>
          <div class="col-12"><label class="form-label">Problema de investigação</label><textarea name="problem_statement" class="form-control" rows="3" placeholder="Qual problema o trabalho pretende resolver?"></textarea></div>
          <div class="col-12"><label class="form-label">Objectivo geral</label><textarea name="general_objective" class="form-control" rows="2" placeholder="Resultado principal esperado"></textarea></div>
          <div class="col-12"><label class="form-label">Objectivos específicos</label><textarea name="specific_objectives" class="form-control" rows="3" placeholder="Escreva um objectivo por linha"></textarea></div>
          <div class="col-md-6"><label class="form-label">Hipótese</label><textarea name="hypothesis" class="form-control" rows="2"></textarea></div>
          <div class="col-md-6"><label class="form-label">Palavras-chave</label><input name="keywords" class="form-control" data-keywords-input placeholder="Ex.: inclusão financeira, estudantes"><div class="mt-2" data-keywords-preview></div></div>
          <div class="col-12"><label class="form-label">Briefing complementar</label><textarea name="notes" class="form-control" rows="3" placeholder="Notas metodológicas, requisitos do docente, critérios de avaliação"></textarea></div>
        </div>
      </div>

      <div class="card p-4">
        <div class="form-section-title">Anexos e materiais de referência</div>
        <input type="file" name="attachments[]" class="form-control" multiple data-attachments-input>
        <div class="form-hint">Anexe guias, modelos, rubricas, PDFs de normas e fontes de referência já aprovadas.</div>
        <ul class="small text-secondary mt-2 mb-0" data-attachments-list></ul>
      </div>
    </div>

    <div class="col-xl-4 d-grid gap-3">
      <div class="card p-4 sticky-summary summary-stack">
        <h2 class="h6">Resumo operacional</h2>
        <div class="status-card"><small>Instituição</small><div data-summary-institution>—</div></div>
        <div class="status-card"><small>Curso / disciplina</small><div data-summary-course>—</div></div>
        <div class="status-card"><small>Escopo</small><div data-summary-scope>Defina páginas e complexidade</div></div>
        <div class="status-card"><small>Extras</small><div data-summary-extras>Sem extras seleccionados</div></div>
        <div class="status-card"><small>Pricing</small><div>Estimado pelo motor oficial após submissão</div></div>
      </div>

      <div class="card p-4 cta-card">
        <h2 class="h6">Extras e submissão</h2>
        <?php foreach (['needs_institution_cover' => 'Capa institucional', 'needs_bilingual_abstract' => 'Abstract bilingue', 'needs_methodology_review' => 'Revisão metodológica', 'needs_humanized_revision' => 'Revisão humanizada', 'needs_slides' => 'Slides de defesa', 'needs_defense_summary' => 'Resumo para defesa'] as $name => $label): ?>
          <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="<?= $name ?>" value="1" data-extra-toggle><span class="form-check-label"><?= $label ?></span></label>
        <?php endforeach; ?>
        <label class="form-label mt-2">Cupão</label>
        <input name="coupon_code" class="form-control" placeholder="Ex.: MZ-ALUNO10">
        <button type="submit" class="btn btn-primary w-100 mt-3">Criar pedido</button>
        <p class="small mt-2 mb-0" data-feedback></p>
      </div>
    </div>
  </div>
</form>

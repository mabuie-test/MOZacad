(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const toggleLoading = (form, loading) => {
    const submit = form.querySelector('button[type="submit"], .btn[type="submit"], button:not([type])');
    if (!submit) return;
    submit.disabled = loading;
    submit.classList.toggle('is-loading', loading);
    if (!submit.dataset.originalText) submit.dataset.originalText = submit.textContent;
    submit.textContent = loading ? 'A processar...' : submit.dataset.originalText;
  };

  const setFeedback = (form, message, type = 'danger') => {
    const node = form.querySelector('[data-feedback]');
    if (!node) return;
    node.classList.remove('text-danger', 'text-success', 'text-warning');
    node.classList.add(`text-${type}`);
    node.textContent = message || '';
  };

  const markFieldState = (form) => {
    form.querySelectorAll('input, select, textarea').forEach((field) => {
      if (!field.required) return;
      field.classList.toggle('is-invalid', !field.value.trim());
      field.classList.toggle('is-valid', !!field.value.trim());
    });
  };

  const bindAjaxForm = (selector, onSuccess) => {
    document.querySelectorAll(selector).forEach((form) => {
      let busy = false;
      form.querySelectorAll('input, select, textarea').forEach((field) => {
        field.addEventListener('input', () => markFieldState(form));
      });

      form.addEventListener('submit', async (e) => {
        if (busy) return;
        e.preventDefault();
        markFieldState(form);

        const invalid = form.querySelector('.is-invalid');
        if (invalid) {
          setFeedback(form, 'Preencha os campos obrigatórios antes de continuar.', 'warning');
          invalid.focus();
          return;
        }

        busy = true;
        toggleLoading(form, true);
        setFeedback(form, 'A validar dados...', 'warning');

        try {
          const data = new FormData(form);
          if (!data.get('_csrf')) data.set('_csrf', csrf);
          const res = await fetch(form.action, {
            method: form.method || 'POST',
            body: data,
            headers: {
              Accept: 'application/json, text/html',
              'X-CSRF-Token': csrf,
              'X-MOZACAD-CLIENT': 'first-party-web'
            }
          });
          const type = res.headers.get('content-type') || '';

          if (type.includes('application/json')) {
            const payload = await res.json().catch(() => ({}));
            if (!res.ok) {
              setFeedback(form, payload.message || 'Não foi possível concluir a operação.', 'danger');
              return;
            }
            setFeedback(form, payload.message || 'Operação concluída.', 'success');
            onSuccess?.(payload, form);
            return;
          }

          if (res.redirected) {
            window.location.href = res.url;
            return;
          }

          if (res.ok) {
            window.location.reload();
            return;
          }

          setFeedback(form, 'Operação falhou. Tente novamente.', 'danger');
        } catch {
          setFeedback(form, 'Falha de conexão. Tente novamente.', 'danger');
        } finally {
          busy = false;
          toggleLoading(form, false);
        }
      });
    });
  };

  bindAjaxForm('[data-auth-form]', () => (window.location.href = '/dashboard'));
  bindAjaxForm('[data-order-create]', (payload) => payload.order_id && (window.location.href = `/orders/${payload.order_id}`));
  bindAjaxForm('[data-order-pay]', (payload) => payload.order_id && (window.location.href = `/orders/${payload.order_id}`));
  bindAjaxForm('[data-revision-form]', () => window.location.reload());
  bindAjaxForm('[data-admin-pricing-rule-form]');
  bindAjaxForm('[data-admin-pricing-extra-form]');

  const bindSemanticPricingValidation = () => {
    document.querySelectorAll('[data-admin-pricing-rule-form], [data-admin-pricing-extra-form]').forEach((form) => {
      form.addEventListener('submit', (event) => {
        const ruleCode = form.querySelector('input[name="rule_code"]');
        const extraCode = form.querySelector('input[name="extra_code"]');
        const amount = form.querySelector('input[name="amount"]');

        if (ruleCode && !/^[A-Z0-9_.:-]{3,100}$/.test((ruleCode.value || '').trim().toUpperCase())) {
          event.preventDefault();
          setFeedback(form, 'rule_code inválido. Use A-Z, 0-9, _, ., :, - (3-100).', 'warning');
          ruleCode.focus();
          return;
        }

        if (extraCode && !/^[A-Z0-9_.:-]{3,100}$/.test((extraCode.value || '').trim().toUpperCase())) {
          event.preventDefault();
          setFeedback(form, 'extra_code inválido. Use A-Z, 0-9, _, ., :, - (3-100).', 'warning');
          extraCode.focus();
          return;
        }

        if (amount) {
          const value = Number.parseFloat(amount.value || '');
          if (!Number.isFinite(value) || value < 0 || value > 10000000) {
            event.preventDefault();
            setFeedback(form, 'amount deve estar entre 0 e 10000000.', 'warning');
            amount.focus();
          }
        }
      });
    });
  };
  bindSemanticPricingValidation();

  const createForm = document.querySelector('[data-order-create]');
  if (!createForm) return;

  const institutionSelect = createForm.querySelector('[data-institution-select]');
  const courseSelect = createForm.querySelector('[data-course-select]');
  const disciplineSelect = createForm.querySelector('[data-discipline-select]');
  const attachmentInput = createForm.querySelector('[data-attachments-input]');
  const attachmentsList = createForm.querySelector('[data-attachments-list]');
  const pagesInput = createForm.querySelector('[data-pages-input]');
  const complexityInput = createForm.querySelector('[data-complexity-input]');
  const keywordsInput = createForm.querySelector('[data-keywords-input]');
  const keywordsPreview = createForm.querySelector('[data-keywords-preview]');

  const summary = {
    institution: createForm.querySelector('[data-summary-institution]'),
    course: createForm.querySelector('[data-summary-course]'),
    scope: createForm.querySelector('[data-summary-scope]'),
    extras: createForm.querySelector('[data-summary-extras]')
  };

  const fillSelect = (el, rows, label) => {
    if (!el) return;
    el.innerHTML = `<option value="">${label}</option>`;
    rows.forEach((row) => {
      const option = document.createElement('option');
      option.value = row.id;
      option.textContent = row.name;
      el.appendChild(option);
    });
  };

  institutionSelect?.addEventListener('change', async () => {
    summary.institution && (summary.institution.textContent = institutionSelect.options[institutionSelect.selectedIndex]?.text || '—');
    const institutionId = institutionSelect.value;
    fillSelect(courseSelect, [], 'A carregar cursos...');
    fillSelect(disciplineSelect, [], 'Selecione o curso');
    if (!institutionId) return;

    const res = await fetch(`/orders/meta/courses?institution_id=${encodeURIComponent(institutionId)}`, {
      headers: {
        Accept: 'application/json',
        'X-MOZACAD-CLIENT': 'first-party-web'
      }
    });
    const payload = await res.json().catch(() => ({ courses: [] }));
    fillSelect(courseSelect, payload.courses || [], 'Selecione...');
  });

  courseSelect?.addEventListener('change', async () => {
    const courseName = courseSelect.options[courseSelect.selectedIndex]?.text || '—';
    summary.course && (summary.course.textContent = courseName);
    const courseId = courseSelect.value;
    fillSelect(disciplineSelect, [], 'A carregar disciplinas...');
    if (!courseId) return;

    const res = await fetch(`/orders/meta/disciplines?course_id=${encodeURIComponent(courseId)}`, {
      headers: {
        Accept: 'application/json',
        'X-MOZACAD-CLIENT': 'first-party-web'
      }
    });
    const payload = await res.json().catch(() => ({ disciplines: [] }));
    fillSelect(disciplineSelect, payload.disciplines || [], 'Selecione...');
  });

  disciplineSelect?.addEventListener('change', () => {
    const courseName = courseSelect.options[courseSelect.selectedIndex]?.text || '—';
    const disciplineName = disciplineSelect.options[disciplineSelect.selectedIndex]?.text || '—';
    summary.course && (summary.course.textContent = `${courseName} / ${disciplineName}`);
  });

  const updateScope = () => {
    if (!summary.scope) return;
    const pages = pagesInput?.value || '0';
    const complexity = complexityInput?.value || 'medium';
    summary.scope.textContent = `${pages} páginas • complexidade ${complexity}`;
  };

  const updateExtras = () => {
    if (!summary.extras) return;
    const active = [...createForm.querySelectorAll('[data-extra-toggle]:checked')]
      .map((i) => i.closest('label')?.textContent?.trim())
      .filter(Boolean);
    summary.extras.textContent = active.length ? active.join(', ') : 'Sem extras seleccionados';
  };

  const updateKeywordsPreview = () => {
    if (!keywordsPreview) return;
    const values = (keywordsInput?.value || '').split(/[;,]/).map((v) => v.trim()).filter(Boolean).slice(0, 8);
    keywordsPreview.innerHTML = values.map((v) => `<span class="keyword-chip">${v}</span>`).join('');
  };

  pagesInput?.addEventListener('input', updateScope);
  complexityInput?.addEventListener('change', updateScope);
  createForm.querySelectorAll('[data-extra-toggle]').forEach((item) => item.addEventListener('change', updateExtras));
  keywordsInput?.addEventListener('input', updateKeywordsPreview);

  attachmentInput?.addEventListener('change', () => {
    if (!attachmentsList) return;
    attachmentsList.innerHTML = '';
    Array.from(attachmentInput.files || []).forEach((file) => {
      const li = document.createElement('li');
      li.textContent = `${file.name} (${Math.ceil(file.size / 1024)} KB)`;
      attachmentsList.appendChild(li);
    });
  });

  updateScope();
  updateExtras();
  updateKeywordsPreview();
})();

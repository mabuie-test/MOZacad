(() => {
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  const toggleLoading = (form, loading) => {
    const submit = form.querySelector('button[type="submit"], .btn[type="submit"], button:not([type])');
    if (!submit) return;
    submit.disabled = loading;
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

  const setDebug = (form, payload = null, meta = {}) => {
    const node = form.querySelector('[data-debug]');
    if (!node) return;

    const hasPayload = payload && typeof payload === 'object' && Object.keys(payload).length > 0;
    const hasMeta = meta && typeof meta === 'object' && Object.keys(meta).length > 0;
    if (!hasPayload && !hasMeta) {
      node.textContent = '';
      node.classList.add('d-none');
      return;
    }

    const debugData = {
      timestamp: new Date().toISOString(),
      endpoint: form.action,
      method: (form.method || 'POST').toUpperCase(),
      ...meta,
      payload
    };

    node.textContent = JSON.stringify(debugData, null, 2);
    node.classList.remove('d-none');
  };

  const bindAjaxForm = (selector, onSuccess) => {
    document.querySelectorAll(selector).forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        toggleLoading(form, true);
        setFeedback(form, 'A validar dados...', 'warning');
        setDebug(form);

        try {
          const data = new FormData(form);
          if (!data.get('_csrf')) data.set('_csrf', csrf);
          const res = await fetch(form.action, {
            method: form.method || 'POST',
            body: data,
            headers: { Accept: 'application/json' }
          });

          const payload = await res.json().catch(() => ({}));
          if (!res.ok) {
            setFeedback(form, payload.message || 'Não foi possível concluir esta operação.', 'danger');
            setDebug(form, payload, { http_status: res.status, ok: false });
            return;
          }

          setFeedback(form, payload.message || 'Operação concluída com sucesso.', 'success');
          setDebug(form, payload, { http_status: res.status, ok: true });
          onSuccess?.(payload, form);
        } catch (error) {
          setFeedback(form, 'Falha de conexão. Tente novamente em instantes.', 'danger');
          setDebug(form, { error: error?.message || 'network_error' }, { ok: false, transport: 'fetch_exception' });
        } finally {
          toggleLoading(form, false);
        }
      });
    });
  };

  bindAjaxForm('[data-auth-form]', () => (window.location.href = '/dashboard'));
  bindAjaxForm('[data-order-create]', (payload) => {
    if (payload.order_id) window.location.href = `/orders/${payload.order_id}`;
  });
  bindAjaxForm('[data-order-pay]', (payload) => {
    if (payload.order_id) window.location.href = `/orders/${payload.order_id}`;
  });
  bindAjaxForm('[data-revision-form]', () => window.location.reload());
})();

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

  const bindAjaxForm = (selector, onSuccess) => {
    document.querySelectorAll(selector).forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        toggleLoading(form, true);
        setFeedback(form, 'A validar dados...', 'warning');

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
            return;
          }

          setFeedback(form, payload.message || 'Operação concluída com sucesso.', 'success');
          onSuccess?.(payload, form);
        } catch (error) {
          setFeedback(form, 'Falha de conexão. Tente novamente em instantes.', 'danger');
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

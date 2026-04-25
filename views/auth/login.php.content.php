<section class="auth-shell row g-3 align-items-stretch">
  <div class="col-lg-5">
    <div class="auth-side p-4 h-100">
      <h1 class="h4 mb-2">Bem-vindo de volta</h1>
      <p class="text-secondary">Retome o controlo dos seus pedidos, pagamentos, revisões e downloads com visibilidade total de estado.</p>
      <div class="status-card mb-2"><strong>1.</strong> Acompanhe pendências no dashboard</div>
      <div class="status-card mb-2"><strong>2.</strong> Resolva pagamentos e revisões rapidamente</div>
      <div class="status-card"><strong>3.</strong> Faça download de versões finais num clique</div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-4 h-100">
      <h2 class="h4 mb-1">Entrar na plataforma</h2>
      <p class="text-secondary mb-3">Use as credenciais da sua conta MOZacad.</p>

      <form method="post" action="/login" data-auth-form novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="mb-3">
          <label class="form-label" for="login_email">Email</label>
          <input id="login_email" name="email" type="email" class="form-control" placeholder="nome@dominio.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="login_password">Senha</label>
          <input id="login_password" name="password" type="password" class="form-control" placeholder="••••••••" required>
          <div class="form-hint">Se tiver dificuldades, contacte o suporte para recuperação de acesso.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>

      <hr>
      <p class="small mb-0">Ainda não tem conta? <a href="/register">Criar conta agora</a>.</p>
    </div>
  </div>
</section>

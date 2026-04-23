<section class="row justify-content-center">
  <div class="col-lg-5 col-md-8">
    <div class="card p-4">
      <h1 class="h3 mb-1">Entrar na plataforma</h1>
      <p class="text-secondary">Aceda ao seu cockpit académico para gerir pedidos, pagamentos e downloads.</p>

      <form method="post" action="/login" data-auth-form novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="mb-3">
          <label class="form-label" for="login_email">Email institucional</label>
          <input id="login_email" name="email" type="email" class="form-control" placeholder="nome@dominio.com" required>
        </div>
        <div class="mb-3">
          <label class="form-label" for="login_password">Senha</label>
          <input id="login_password" name="password" type="password" class="form-control" placeholder="••••••••" required>
          <div class="form-hint">Use a mesma credencial definida no registo.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>

      <hr>
      <p class="small mb-0">Ainda não tem conta? <a href="/register">Criar conta agora</a>.</p>
    </div>
  </div>
</section>

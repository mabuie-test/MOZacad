<section class="row justify-content-center">
  <div class="col-lg-8">
    <div class="card p-4">
      <h1 class="h3 mb-1">Criar conta MOZacad</h1>
      <p class="text-secondary">Registo rápido para activar o fluxo completo de pedidos académicos.</p>

      <form method="post" action="/register" data-auth-form novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="reg_name">Nome completo</label>
            <input id="reg_name" name="name" class="form-control" placeholder="Nome e apelido" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_phone">Telefone</label>
            <input id="reg_phone" name="phone" class="form-control" placeholder="84xxxxxxx">
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_email">Email</label>
            <input id="reg_email" name="email" type="email" class="form-control" placeholder="nome@dominio.com" required>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="reg_password">Senha</label>
            <input id="reg_password" name="password" type="password" class="form-control" minlength="8" required>
            <div class="form-hint">Mínimo 8 caracteres.</div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Criar conta</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</section>

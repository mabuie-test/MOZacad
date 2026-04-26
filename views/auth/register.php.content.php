<section class="auth-shell row g-3 align-items-stretch">
  <div class="col-lg-5">
    <div class="auth-side p-4 h-100">
      <h1 class="h4 mb-2">Criar conta MOZacad</h1>
      <p class="text-secondary">Em menos de 2 minutos, ativa um cockpit académico completo com rastreamento ponta-a-ponta.</p>
      <ul class="small text-secondary mb-0">
        <li>Gestão de pedidos e estados complexos</li>
        <li>Fluxo financeiro com invoices e M-Pesa</li>
        <li>Revisões e downloads versionados</li>
      </ul>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="card p-4 h-100">
      <h2 class="h4 mb-1">Registo rápido</h2>
      <p class="text-secondary mb-3">Preencha os dados para começar a usar a plataforma.</p>

      <form method="post" action="/register" data-auth-form novalidate>
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
        <?php if (!empty($returnTo)): ?><input type="hidden" name="return_to" value="<?= htmlspecialchars((string) $returnTo) ?>"><?php endif; ?>
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
            <div class="form-hint">Mínimo 8 caracteres, evite sequências simples.</div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3 w-100">Criar conta</button>
        <p class="small mt-3 mb-0" data-feedback></p>
      </form>
    </div>
  </div>
</section>

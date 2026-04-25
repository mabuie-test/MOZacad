<!doctype html>
<html lang="pt-MZ">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
  <title><?= htmlspecialchars($title ?? 'MOZacad') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php
$isAdminArea = str_starts_with((string) ($currentPath ?? ''), '/admin');
$pathLabel = match ($currentPath ?? '/') {
    '/' => 'Início',
    '/dashboard' => 'Dashboard',
    '/orders' => 'Pedidos',
    '/orders/create' => 'Novo pedido',
    '/invoices' => 'Facturas',
    '/downloads' => 'Downloads',
    '/admin' => 'Admin',
    '/about' => 'Sobre',
    '/how-it-works' => 'Como funciona',
    '/pricing' => 'Preços',
    default => 'MOZacad',
};
?>
<nav class="navbar navbar-expand-lg app-navbar sticky-top">
  <div class="container-fluid container-xl">
    <a class="navbar-brand fw-bold" href="/">MOZacad</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php if (!($isAuthenticated ?? false)): ?>
          <?php foreach ([['/how-it-works', 'Como funciona'], ['/pricing', 'Preços'], ['/institutions', 'Instituições'], ['/faq', 'FAQ'], ['/contact', 'Contacto']] as [$href, $label]): ?>
            <li class="nav-item"><a class="nav-link <?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a></li>
          <?php endforeach; ?>
        <?php else: ?>
          <?php foreach ([['/dashboard', 'Dashboard'], ['/orders', 'Pedidos'], ['/invoices', 'Facturas'], ['/downloads', 'Downloads']] as [$href, $label]): ?>
            <li class="nav-item"><a class="nav-link <?= str_starts_with((string) ($currentPath ?? ''), $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a></li>
          <?php endforeach; ?>
          <?php if ($isAdmin ?? false): ?><li class="nav-item"><a class="nav-link <?= $isAdminArea ? 'active' : '' ?>" href="/admin">Admin</a></li><?php endif; ?>
        <?php endif; ?>
      </ul>
      <div class="d-flex gap-2 align-items-center">
        <?php if (!($isAuthenticated ?? false)): ?>
          <a class="btn btn-sm btn-outline-light" href="/login">Entrar</a>
          <a class="btn btn-sm btn-primary" href="/register">Criar conta</a>
        <?php else: ?>
          <form method="post" action="/logout" class="m-0">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars((string) ($csrfToken ?? '')) ?>">
            <button class="btn btn-sm btn-outline-light">Sair</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>

<div class="border-bottom bg-white py-2">
  <div class="container-xl small text-secondary d-flex justify-content-between">
    <span><?= htmlspecialchars($pathLabel) ?></span>
    <span>Suporte premium • Segunda a Sábado • 08:00–19:00</span>
  </div>
</div>


<main class="<?= $isAdminArea ? 'py-4' : 'py-5' ?>">
  <div class="container-xl">
    <?php if (!empty($flash['message'])): ?>
      <div class="alert alert-<?= htmlspecialchars((string) (($flash['type'] ?? 'info') === 'error' ? 'danger' : (($flash['type'] ?? 'info') === 'warning' ? 'warning' : 'success'))) ?>">
        <?= htmlspecialchars((string) $flash['message']) ?>
      </div>
    <?php endif; ?>
    <?php include $contentView; ?>
  </div>
</main>


<footer class="app-footer border-top">
  <div class="container-xl d-flex flex-column flex-md-row justify-content-between py-4 small text-secondary">
    <span>© <?= date('Y') ?> MOZacad · Redação académica e revisão científica premium.</span>
    <span>Maputo · Nampula · Beira · suporte@mozacad.local</span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>

<?php require __DIR__ . '/../partials/ui.php'; ?>
<div class="admin-grid">
  <aside class="card p-3 sidebar">
    <div class="nav-label">Overview</div>
    <a class="<?= (($currentPath ?? '') === '/admin') ? 'active' : '' ?>" href="/admin">Centro operacional</a>

    <div class="nav-label">Operação</div>
    <?php foreach ([
      '/admin/users' => 'Utilizadores',
      '/admin/orders' => 'Pedidos',
      '/admin/payments' => 'Pagamentos',
      '/admin/human-review' => 'Revisão humana'
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>

    <div class="nav-label">Configuração académica</div>
    <?php foreach ([
      '/admin/institutions' => 'Instituições',
      '/admin/courses' => 'Cursos',
      '/admin/disciplines' => 'Disciplinas',
      '/admin/work-types' => 'Tipos de trabalho'
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>

    <div class="nav-label">Governança institucional</div>
    <?php foreach ([
      '/admin/institution-rules' => 'Regras institucionais',
      '/admin/templates' => 'Normas & templates'
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>

    <div class="nav-label">Comercial</div>
    <?php foreach ([
      '/admin/pricing' => 'Pricing e extras',
      '/admin/discounts' => 'Descontos',
      '/admin/coupons' => 'Cupões'
    ] as $href => $label): ?>
      <a class="<?= (($currentPath ?? '') === $href) ? 'active' : '' ?>" href="<?= $href ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <div class="nav-label">Segurança</div>
    <a class="<?= (($currentPath ?? '') === '/admin/permissions') ? 'active' : '' ?>" href="/admin/permissions">Permissões</a>

  </aside>

  <section>
    <div class="page-header">
      <div>
        <h1 class="section-title h3">Backoffice MOZacad</h1>
        <p>Gestão administrativa modular para operação diária, governança institucional e controlo comercial.</p>
      </div>
    </div>

    <?php $section = str_replace('-', '_', (string) ($activeSection ?? 'overview')); $sectionFile = __DIR__ . '/sections/' . $section . '.php'; ?>
    <?php if (is_file($sectionFile)) { include $sectionFile; } else { include __DIR__ . '/sections/overview.php'; } ?>
  </section>
</div>

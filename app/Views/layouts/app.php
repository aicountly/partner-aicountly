<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($title ?? 'Partner Portal') ?> · AICOUNTLY Partner Portal</title>
  <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body>
  <header class="topbar">
    <div class="topbar-inner">
      <a class="brand" href="<?= base_url('dashboard') ?>">
        <span class="brand-mark">PA</span>
        <span class="brand-text">
          <strong>AICOUNTLY</strong>
          <small>Partner Portal</small>
        </span>
      </a>
      <nav class="topbar-nav">
        <a href="<?= base_url('dashboard') ?>" class="<?= url_is('dashboard') ? 'is-active' : '' ?>">Dashboard</a>
        <a href="<?= base_url('profile') ?>" class="<?= url_is('profile') ? 'is-active' : '' ?>">Profile</a>
        <?= form_open('logout', ['class' => 'logout-form']) ?>
          <button type="submit" class="btn btn-secondary">Logout</button>
        <?= form_close() ?>
      </nav>
    </div>
  </header>

  <main class="page">
    <?php if (session()->getFlashdata('message')): ?>
      <div class="alert alert-info"><?= esc(session()->getFlashdata('message')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
      <div class="alert alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>
  </main>

  <footer class="page-footer">
    &copy; <?= date('Y') ?> AICOUNTLY · Partner details are maintained by your AICOUNTLY representative.
  </footer>
</body>
</html>

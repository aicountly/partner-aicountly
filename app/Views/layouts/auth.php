<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= esc($title ?? 'Partner Portal') ?> · AICOUNTLY Partner Portal</title>
  <link rel="stylesheet" href="<?= base_url('assets/app.css') ?>">
</head>
<body class="auth-body">
  <main class="auth-shell">
    <div class="auth-card">
      <div class="brand">
        <span class="brand-mark">PA</span>
        <span class="brand-text">
          <strong>AICOUNTLY</strong>
          <small>Partner Portal</small>
        </span>
      </div>
      <?= $this->renderSection('content') ?>
    </div>
    <p class="auth-footnote">
      Partner accounts are issued by AICOUNTLY. Contact your AICOUNTLY representative if you need access.
    </p>
  </main>
</body>
</html>

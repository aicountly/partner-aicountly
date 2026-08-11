<?= $this->extend('layouts/auth') ?>

<?= $this->section('content') ?>

  <h1 class="auth-title">Sign in</h1>
  <p class="auth-subtitle">Use the credentials issued to you by AICOUNTLY.</p>

  <?php if (session()->getFlashdata('message')): ?>
    <div class="alert alert-info"><?= esc(session()->getFlashdata('message')) ?></div>
  <?php endif; ?>

  <?php if (session()->getFlashdata('error')): ?>
    <div class="alert alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
  <?php endif; ?>

  <?php $errors = session()->getFlashdata('errors') ?? []; ?>
  <?php if ($errors): ?>
    <div class="alert alert-error">
      <ul>
        <?php foreach ($errors as $message): ?>
          <li><?= esc($message) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?= form_open('login', ['class' => 'form', 'autocomplete' => 'on']) ?>
    <div class="field">
      <label for="email">Email</label>
      <input
        type="email"
        id="email"
        name="email"
        value="<?= esc(old('email', $email ?? '')) ?>"
        autocomplete="username"
        required
        autofocus
      >
    </div>

    <div class="field">
      <label for="password">Password</label>
      <input
        type="password"
        id="password"
        name="password"
        autocomplete="current-password"
        required
      >
    </div>

    <button type="submit" class="btn btn-primary btn-block">Login</button>
  <?= form_close() ?>

<?= $this->endSection() ?>

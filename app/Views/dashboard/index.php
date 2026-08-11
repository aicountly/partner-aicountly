<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>

  <h1 class="page-title">Welcome, <?= esc($partner['name'] ?? 'Partner') ?></h1>
  <p class="page-subtitle">You are signed in to the AICOUNTLY Partner Portal.</p>

  <div class="card-grid">
    <section class="card">
      <h2 class="card-title">Your partner account</h2>
      <dl class="detail-list">
        <div>
          <dt>Partner ID</dt>
          <dd class="mono"><?= esc($partner['partner_uid'] ?? '—') ?></dd>
        </div>
        <div>
          <dt>Partner name</dt>
          <dd><?= esc($partner['name'] ?? '—') ?></dd>
        </div>
        <div>
          <dt>Email</dt>
          <dd><?= esc($partner['email'] ?? '—') ?></dd>
        </div>
        <div>
          <dt>Status</dt>
          <dd><span class="pill pill-active"><?= esc(ucfirst((string) ($partner['status'] ?? 'active'))) ?></span></dd>
        </div>
      </dl>
      <a class="btn btn-secondary" href="<?= base_url('profile') ?>">View profile</a>
    </section>

    <section class="card">
      <h2 class="card-title">What&rsquo;s next</h2>
      <p class="muted">
        This is the first release of the Partner Portal. Partner programme features will appear here as they are
        released. Your partner details and portal access are maintained by your AICOUNTLY representative — contact
        them if anything needs to change.
      </p>
    </section>
  </div>

<?= $this->endSection() ?>

<?= $this->extend('layouts/app') ?>

<?= $this->section('content') ?>

  <h1 class="page-title">Profile</h1>
  <p class="page-subtitle">Your partner record as held by AICOUNTLY.</p>

  <section class="card">
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
        <dt>Contact person</dt>
        <dd><?= esc($partner['contact_name'] ?? '') ?: '—' ?></dd>
      </div>
      <div>
        <dt>Email</dt>
        <dd><?= esc($partner['email'] ?? '—') ?></dd>
      </div>
      <div>
        <dt>Phone</dt>
        <dd><?= esc($partner['phone'] ?? '') ?: '—' ?></dd>
      </div>
      <div>
        <dt>Partner type</dt>
        <dd><?= esc(ucfirst((string) ($partner['partner_type'] ?? ''))) ?: '—' ?></dd>
      </div>
      <div>
        <dt>Website</dt>
        <dd>
          <?php if (! empty($partner['website'])): ?>
            <a href="<?= esc($partner['website'], 'attr') ?>" target="_blank" rel="noreferrer noopener"><?= esc($partner['website']) ?></a>
          <?php else: ?>
            —
          <?php endif; ?>
        </dd>
      </div>
      <div>
        <dt>Location</dt>
        <dd><?= esc(implode(', ', array_filter([$partner['city'] ?? null, $partner['country'] ?? null]))) ?: '—' ?></dd>
      </div>
      <div>
        <dt>Status</dt>
        <dd><span class="pill pill-active"><?= esc(ucfirst((string) ($partner['status'] ?? 'active'))) ?></span></dd>
      </div>
      <div>
        <dt>Last sign-in</dt>
        <dd><?= esc($partner['last_login_at'] ?? '') ?: 'This is your first sign-in' ?></dd>
      </div>
    </dl>

    <p class="muted">
      Partner details and portal passwords are maintained by AICOUNTLY. To update anything on this page, or to have
      your password reset, contact your AICOUNTLY representative.
    </p>
  </section>

<?= $this->endSection() ?>

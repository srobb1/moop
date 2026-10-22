<?php
/**
 * Who can see what — the ONE home for access levels as a user meets them.
 *
 * Moved here from about.php (2026-10-22), which now links to this page instead of
 * keeping its own copy. The "you are" card reads get_access_level(), so it is right for
 * the viewer — including an admin previewing as PUBLIC, where the accessors report PUBLIC.
 *
 * Available: $config, $siteTitle
 */

$site  = $config->getString('site');
$email = $config->getString('admin_email', '');
$level = get_access_level();

$you = [
    'PUBLIC'       => ['fa-globe', 'You are not logged in, so you see the data that has been made public.'],
    'IP_IN_RANGE'  => ['fa-building', 'You are on the institute network, so you see all the data without logging in.'],
    'COLLABORATOR' => ['fa-user', 'You are logged in with access to the organisms the administrator has granted you, plus everything public.'],
    'ADMIN'        => ['fa-user-shield', 'You are an administrator, so you see everything.'],
][$level] ?? ['fa-question-circle', 'Your access level could not be determined.'];

$levels = [
    ['Not logged in', 'Browse and search the data that has been made public. No account needed.'],
    ['On the institute network', 'Recognised by network address — all the data, without logging in.'],
    ['Collaborator', 'A login granted access to particular organisms or assemblies by the administrator.'],
    ['Administrator', 'Everything, plus the admin pages.'],
];
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-lock me-2"></i>Who can see what
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Not every organism in <?= htmlspecialchars($siteTitle) ?> is public, so the lists you see may be
        shorter than everything the site holds.
      </p>
    </div>
  </div>

  <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
    <i class="fa <?= $you[0] ?> fa-lg" aria-hidden="true"></i>
    <div><?= htmlspecialchars($you[1]) ?></div>
  </div>

  <h2 class="h5 fw-semibold mb-3">The levels</h2>
  <div class="row g-3 mb-4">
    <?php foreach ($levels as [$label, $text]): ?>
      <div class="col-md-6 col-lg-3">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h3 class="h6 fw-semibold mb-1"><?= htmlspecialchars($label) ?></h3>
            <p class="text-muted small mb-0"><?= htmlspecialchars($text) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="h5 fw-semibold mb-3">If something is missing</h2>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1">A page says “Not found”</h3>
          <p class="text-muted small mb-0">
            Data you cannot open is treated exactly as if it did not exist — the site never says
            what is restricted. So a link someone sent you can say “Not found” for you and work
            for them. Log in, or connect from the institute network, and try again.
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1">Getting access</h3>
          <p class="text-muted small mb-0">
            Collaborator accounts are set up by the administrator.
            <?php if ($email !== '' && $email !== 'admin@example.com'): ?>
              <a href="mailto:<?= htmlspecialchars($email) ?>">Email the administrator</a>
            <?php else: ?>
              Contact the administrator
            <?php endif; ?>
            with the organism you need.
            <?php if (!is_logged_in()): ?>
              Already have an account? <a href="/<?= htmlspecialchars($site) ?>/login.php">Log in</a>.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

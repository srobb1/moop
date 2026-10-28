<?php
/**
 * HUMAN CHECK - Content File (Cloudflare Turnstile widget). Controller: verify-human.php.
 *
 * Available variables: $siteTitle, $site_key, $return, $error, $admin_email
 */
?>

<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-5">
      <div class="card adm-card shadow-sm">
        <div class="card-body p-4 text-center">
          <h1 class="h4 fw-bold mb-2"><?= htmlspecialchars($siteTitle) ?></h1>
          <p class="text-muted mb-4">One moment while we check that you are a person, not an automated crawler.
            This happens once per visit.</p>

          <?php if ($error): ?>
            <div class="alert alert-warning py-2 small" role="alert"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <?php /* After a rejection, wait for a click: auto-submitting again would loop
                   (the widget passes in the browser, the server says no, repeat). */ ?>
          <form method="post" id="human-check-form" data-autosubmit="<?= $error ? '0' : '1' ?>">
            <?= csrf_input_field() ?>
            <input type="hidden" name="return" value="<?= htmlspecialchars($return) ?>">
            <div id="human-check" class="d-flex justify-content-center mb-3"
                 data-sitekey="<?= htmlspecialchars($site_key) ?>"></div>
            <?php if ($error): ?>
              <button type="submit" class="btn btn-primary" id="human-check-retry" disabled>Try again</button>
            <?php endif; ?>
          </form>

          <div id="human-check-status" class="alert alert-warning py-2 small d-none" role="status"></div>

          <noscript>
            <div class="alert alert-warning py-2 small">This check needs JavaScript. Please enable it, or log in.</div>
          </noscript>

          <p class="small text-muted mb-0 mt-3">
            Have an account? <a href="login.php?return=<?= urlencode($return) ?>">Log in</a> to skip this.
            <?php if ($admin_email): ?>
              <br>Need bulk data or scripted access? Use Downloads, or contact
              <a href="mailto:<?= htmlspecialchars($admin_email) ?>"><?= htmlspecialchars($admin_email) ?></a>.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>

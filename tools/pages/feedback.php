<?php
/**
 * SEND US A MESSAGE - Content File (controller: tools/feedback.php)
 *
 * Variables: $state (form|sent|error|cap|limit), $errors (list of field keys), $values (kind, message,
 * name, email, from), $admin_email, $siteTitle.
 */
$v = fn($k) => htmlspecialchars((string) ($values[$k] ?? ''), ENT_QUOTES);
$has = fn($k) => in_array($k, $errors, true);
$back = ($values['from'] ?? '') !== '' ? $values['from'] : moop_url_base() . '/';
$mail = '<a href="mailto:' . htmlspecialchars($admin_email) . '">' . htmlspecialchars($admin_email) . '</a>';
$kinds = [
    'question' => t('A question about the site or the data'),
    'data'     => t('A request: data, an organism, or a feature'),
    'problem'  => t('Something is broken or wrong'),
    'other'    => t('Something else'),
];
?>
<div class="container my-4" style="max-width: 720px;">
  <h1 class="h3 mb-2"><i class="fa fa-comment-dots me-2 text-muted"></i><?= t('Send us a message') ?></h1>

<?php if ($state === 'sent'): ?>
  <div class="card shadow-sm mt-3">
    <div class="card-body p-4">
      <p class="h5 mb-2"><i class="fa fa-check-circle text-success me-2"></i><?= t('Thank you — your message has been sent.') ?></p>
      <p class="mb-3"><?= t('A person reads every message. If you left an email address, we will reply there.') ?></p>
      <a class="btn btn-moop-teal text-white" href="<?= htmlspecialchars($back) ?>"><i class="fa fa-arrow-left me-1"></i><?= t('Back to where you were') ?></a>
    </div>
  </div>
<?php else: ?>
  <p class="text-muted mb-3"><?= t('Ask a question, request data, or tell us about a problem. A person reads every message.') ?></p>

  <?php if ($state === 'error'): ?>
    <div class="alert alert-danger" role="alert"><?= t_markup('Sorry — your message could not be saved. Please try again later, or email us at %s.', $mail) ?></div>
  <?php elseif ($state === 'cap'): ?>
    <div class="alert alert-warning" role="alert"><?= t_markup('We have received a very large number of messages today. Please email us instead at %s.', $mail) ?></div>
  <?php elseif ($state === 'limit'): ?>
    <div class="alert alert-warning" role="alert"><?= t('You have sent several messages in the last hour. Please wait a while before sending another.') ?></div>
  <?php elseif ($has('token')): ?>
    <div class="alert alert-warning" role="alert"><?= t('Your session timed out while you were writing. Your message is still below — please press Send again.') ?></div>
  <?php endif; ?>

  <form method="post" action="" class="card shadow-sm" novalidate>
    <div class="card-body p-4">
      <?= csrf_input_field() ?>
      <input type="hidden" name="from" value="<?= $v('from') ?>">
      <!-- Left empty by people; bots fill it in. Hidden from screen readers and the tab order. -->
      <div style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
        <label><?= t('Website') ?> <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
      </div>

      <fieldset class="mb-3">
        <legend class="form-label fw-semibold fs-6"><?= t('What is it about?') ?></legend>
        <?php foreach ($kinds as $k => $label): ?>
        <div class="form-check">
          <input class="form-check-input" type="radio" name="kind" id="fb-kind-<?= $k ?>" value="<?= $k ?>"<?= ($values['kind'] ?? '') === $k ? ' checked' : '' ?>>
          <label class="form-check-label" for="fb-kind-<?= $k ?>"><?= $label ?></label>
        </div>
        <?php endforeach; ?>
      </fieldset>

      <div class="mb-3">
        <label class="form-label fw-semibold" for="fb-message"><?= t('Your message') ?></label>
        <textarea class="form-control<?= $has('message') ? ' is-invalid' : '' ?>" id="fb-message" name="message" rows="7" maxlength="5000" required
                  placeholder="<?= t('For a gene or organism, the name or ID helps us find it.') ?>"><?= $v('message') ?></textarea>
        <div class="invalid-feedback"><?= t('Please write a message.') ?></div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-sm-6">
          <label class="form-label fw-semibold" for="fb-name"><?= t('Your name') ?> <span class="fw-normal text-muted">(<?= t('optional') ?>)</span></label>
          <input class="form-control" type="text" id="fb-name" name="name" maxlength="120" autocomplete="name" value="<?= $v('name') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label fw-semibold" for="fb-email"><?= t('Your email') ?> <span class="fw-normal text-muted">(<?= t('optional') ?>)</span></label>
          <input class="form-control<?= $has('email') ? ' is-invalid' : '' ?>" type="email" id="fb-email" name="email" maxlength="200" autocomplete="email" value="<?= $v('email') ?>">
          <div class="invalid-feedback"><?= t('This does not look like an email address.') ?></div>
          <div class="form-text"><?= t('Only if you would like a reply. We use it for nothing else.') ?></div>
        </div>
      </div>

      <?php if (($values['from'] ?? '') !== ''): ?>
      <p class="small text-muted mb-3"><?= t('We will also see the page you came from:') ?> <code><?= $v('from') ?></code></p>
      <?php endif; ?>

      <button type="submit" class="btn btn-moop-teal text-white"><i class="fa fa-paper-plane me-1"></i><?= t('Send') ?></button>
    </div>
  </form>
  <p class="small text-muted mt-3"><?= t_markup('Prefer email? Write to %s.', $mail) ?></p>
<?php endif; ?>
</div>

<?php
/**
 * MESSAGES FROM VISITORS - Content File (controller: admin/manage_feedback.php)
 * Variables: $notice, $messages, $unreadable, $counts, $total, $show, $file, $siteTitle.
 */
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$kind_label = ['question' => ['Question', 'info'], 'data' => ['Request', 'primary'], 'problem' => ['Problem', 'danger'], 'other' => ['Other', 'secondary']];
$status_label = ['new' => ['New', 'warning'], 'answered' => ['Answered', 'success'], 'done' => ['Done', 'secondary']];
?>
<div class="container-fluid py-3" style="max-width: 1100px;">
  <h1 class="h3 mb-1"><i class="fa fa-comment-dots me-2"></i>Messages from visitors</h1>
  <p class="text-muted mb-3">What people sent with <strong>Send us a message</strong> (footer of every page).
    <?= (int) ($counts['new'] ?? 0) ?> new · <?= (int) ($counts['answered'] ?? 0) ?> answered · <?= (int) ($counts['done'] ?? 0) ?> done.
    Mark a message <em>Done</em> to file it away; nothing is deleted unless you delete it.</p>

  <?php if ($notice): ?><div class="alert alert-<?= $h($notice[0]) ?> py-2"><?= $h($notice[1]) ?></div><?php endif; ?>
  <?php if ($unreadable): ?>
    <div class="alert alert-danger">The messages file could not be read: <code><?= $h($file) ?></code>. Nothing was changed. Check Filesystem Permissions.</div>
  <?php endif; ?>

  <div class="btn-group btn-group-sm mb-3" role="group">
    <a class="btn <?= $show === 'open' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?show=open">New and answered</a>
    <a class="btn <?= $show === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>" href="?show=all">All (<?= (int) $total ?>)</a>
  </div>

  <?php if (!$messages): ?>
    <div class="card adm-card"><div class="card-body text-muted">
      <?= $total ? 'Nothing open — everything is marked done.' : 'No messages yet. They will appear here when a visitor uses the form.' ?>
      <a href="<?= $h(moop_url('tools/feedback.php')) ?>" target="_blank" rel="noopener">See the form</a>.
    </div></div>
  <?php endif; ?>

  <?php foreach ($messages as $m): [$kl, $kc] = $kind_label[$m['kind'] ?? 'other'] ?? $kind_label['other']; [$sl, $sc] = $status_label[$m['status'] ?? 'new'] ?? $status_label['new']; ?>
  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex flex-wrap align-items-center gap-2">
      <span class="badge bg-<?= $kc ?>"><?= $kl ?></span>
      <span class="badge bg-<?= $sc ?><?= $sc === 'warning' ? ' text-dark' : '' ?>"><?= $sl ?></span>
      <strong><?= $h(($m['name'] ?? '') !== '' ? $m['name'] : (($m['user'] ?? '') !== '' ? $m['user'] : 'Someone')) ?></strong>
      <span class="text-muted small">
        <?= ($m['user'] ?? '') !== '' ? 'signed in as ' . $h($m['user']) . ' · ' : '' ?><?= $h(strtolower($m['level'] ?? 'public')) === 'ip_in_range' ? 'institute network' : $h(strtolower($m['level'] ?? 'public')) ?>
        · <?= $h(date('D j M Y, H:i', (int) ($m['ts'] ?? 0))) ?>
      </span>
    </div>
    <div class="card-body">
      <p class="mb-2" style="white-space: pre-wrap;"><?= $h($m['message'] ?? '') ?></p>
      <p class="small text-muted mb-2">
        <?php if (($m['page'] ?? '') !== ''): ?>From page: <a href="<?= $h($m['page']) ?>" target="_blank" rel="noopener"><code><?= $h($m['page']) ?></code></a><?php else: ?>No page recorded<?php endif; ?>
        · <?= ($m['email'] ?? '') !== '' ? 'Email: ' . $h($m['email']) : 'No email left — no reply possible' ?>
      </p>
      <div class="d-flex flex-wrap gap-2">
        <?php if (($m['email'] ?? '') !== ''): ?>
          <a class="btn btn-sm btn-outline-primary" href="mailto:<?= $h($m['email']) ?>?subject=<?= rawurlencode('Re: your message to ' . $siteTitle) ?>&amp;body=<?= rawurlencode("\n\n> " . str_replace("\n", "\n> ", mb_substr($m['message'] ?? '', 0, 1500))) ?>"><i class="fa fa-reply me-1"></i>Reply by email</a>
        <?php endif; ?>
        <?php foreach (['new' => 'Mark new', 'answered' => 'Mark answered', 'done' => 'Mark done'] as $st => $lbl): if (($m['status'] ?? 'new') === $st) continue; ?>
          <form method="post" class="d-inline"><?= csrf_input_field() ?>
            <input type="hidden" name="id" value="<?= $h($m['id'] ?? '') ?>"><input type="hidden" name="status" value="<?= $st ?>">
            <button class="btn btn-sm btn-outline-secondary"><?= $lbl ?></button></form>
        <?php endforeach; ?>
        <form method="post" class="d-inline ms-auto" onsubmit="return confirm('Delete this message for good?');"><?= csrf_input_field() ?>
          <input type="hidden" name="id" value="<?= $h($m['id'] ?? '') ?>"><input type="hidden" name="status" value="delete">
          <button class="btn btn-sm btn-outline-danger"><i class="fa fa-trash me-1"></i>Delete</button></form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

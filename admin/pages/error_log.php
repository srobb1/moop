<?php
/**
 * ERROR LOG — content file (controller: admin/manage_error_log.php).
 * Variables: $notice, $groups, $shown, $entry_count, $log_bytes, $filter_kind, $filter_search, $site
 *
 * One row per PROBLEM (moop_error_log_groups()), newest first. The guide below says exactly
 * what writes to this log and what does not — keep it in step with every logError() call and
 * includes/fatal_log.php. The old About card listed BLAST failures, login problems and
 * permission issues, none of which were ever logged here.
 */
$h = fn($s) => htmlspecialchars((string)$s);
$kind_badge = [
    'crash'    => '<span class="badge bg-danger">PHP crash</span>',
    'reported' => '<span class="badge bg-secondary">Reported by MOOP</span>',
];
$filtered = $filter_kind !== '' || $filter_search !== '';
?>
<div class="container mt-4">

  <div class="d-flex flex-wrap align-items-center gap-3 mb-2">
    <h1 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Error Log</h1>
    <?= help_modal_trigger('errlog-guide', 'How this page works') ?>
  </div>
  <p class="text-muted">
    <?php if (!$groups): ?>
      No errors in the last <?= MOOP_ERROR_LOG_RETENTION_DAYS ?> days.
    <?php else: ?>
      <?= count($groups) ?> problem<?= count($groups) === 1 ? '' : 's' ?>
      (<?= number_format($entry_count) ?> entr<?= $entry_count === 1 ? 'y' : 'ies' ?>, <?= number_format($log_bytes / 1024, 0) ?> KB).
      Entries older than <?= MOOP_ERROR_LOG_RETENTION_DAYS ?> days are removed automatically.
    <?php endif; ?>
  </p>

  <?php if ($notice): ?>
    <div class="alert alert-<?= $h($notice[0]) ?> alert-dismissible fade show" role="alert">
      <?= $h($notice[1]) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if ($groups): ?>
  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-sm-3">
      <label for="kind" class="form-label small mb-1">Kind</label>
      <select class="form-select form-select-sm" id="kind" name="kind">
        <option value="">All</option>
        <option value="crash" <?= $filter_kind === 'crash' ? 'selected' : '' ?>>PHP crashes</option>
        <option value="reported" <?= $filter_kind === 'reported' ? 'selected' : '' ?>>Reported by MOOP</option>
      </select>
    </div>
    <div class="col-sm-5">
      <label for="q" class="form-label small mb-1">Search</label>
      <input type="text" class="form-control form-control-sm" id="q" name="q" value="<?= $h($filter_search) ?>"
             placeholder="Message, file, page, user, address…">
    </div>
    <div class="col-sm-4 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
      <?php if ($filtered): ?><a href="?" class="btn btn-outline-secondary btn-sm">Show all</a><?php endif; ?>
    </div>
  </form>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Problems, newest first</h5>
      <?php if ($filtered): ?><span class="ms-auto small text-muted"><?= count($shown) ?> of <?= count($groups) ?></span><?php endif; ?>
    </div>
    <div class="card-body p-0">
      <?php if (!$shown): ?>
        <p class="text-muted m-3">Nothing matches the filter.</p>
      <?php else: ?>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
          <tr><th>Problem</th><th class="text-end">Times</th><th>Last seen</th><th>First seen</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($shown as $g): $e = $g['latest']; ?>
          <tr>
            <td>
              <div class="fw-semibold text-break"><?= $h(mb_strimwidth($g['error'], 0, 300, '…')) ?></div>
              <div class="small mt-1">
                <?= $kind_badge[$g['kind']] ?>
                <?php if ($g['where'] !== ''): ?><code class="ms-1"><?= $h($g['where']) ?></code><?php endif; ?>
                <?php if (($e['context'] ?? '') !== '' && $g['kind'] === 'reported'): ?>
                  <span class="text-muted ms-1"><?= $h($e['context']) ?></span>
                <?php endif; ?>
              </div>
              <details class="small mt-1">
                <summary class="text-muted">Latest occurrence<?= $g['pages'] > 1 ? ' · on ' . $g['pages'] . ' different pages' : '' ?></summary>
                <dl class="row mb-0 mt-2">
                  <dt class="col-sm-2">Page</dt><dd class="col-sm-10"><code class="text-break"><?= $h($e['page'] ?? '') ?: '—' ?></code></dd>
                  <dt class="col-sm-2">User</dt><dd class="col-sm-10"><?= $h($e['user'] ?? '') ?: '—' ?></dd>
                  <dt class="col-sm-2">Address</dt><dd class="col-sm-10"><?= $h($e['ip'] ?? '') ?: '—' ?></dd>
                  <?php if (!empty($e['details'])):
                      $json = json_encode($e['details'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
                    <dt class="col-sm-2">Details</dt>
                    <dd class="col-sm-10"><pre class="bg-light p-2 rounded small mb-0" style="max-height:20rem; overflow:auto;"><?= $h(strlen($json) > 8000 ? substr($json, 0, 8000) . "\n… (" . number_format(strlen($json)) . ' characters in all)' : $json) ?></pre></dd>
                  <?php endif; ?>
                </dl>
              </details>
            </td>
            <td class="text-end"><?= number_format($g['count']) ?></td>
            <td class="text-nowrap small"><?= $h($g['last']) ?></td>
            <td class="text-nowrap small text-muted"><?= $h($g['first']) ?></td>
            <td class="text-end">
              <form method="post" action="manage_error_log.php" class="d-inline"
                    data-confirm="Remove all <?= (int)$g['count'] ?> entries of this problem? Do this once it is fixed — if it happens again it will come back.">
                <?= csrf_input_field() ?>
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="key" value="<?= $h($g['key']) ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap" title="Remove every entry of this problem">
                  <i class="fa fa-check"></i> Fixed — remove
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <form method="post" action="manage_error_log.php" class="mb-4"
        data-confirm="Clear the entire error log? Every problem listed here will be removed.">
    <?= csrf_input_field() ?>
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-trash"></i> Clear the whole log</button>
  </form>
  <?php endif; ?>

</div>

<?= help_modal(
    'errlog-guide',
    'How to read the Error Log',
    [
        ['heading' => 'What this page is for', 'cards' => [
            ['label' => 'One row per problem', 'text' => 'The same error from the same place is one row, however often it happened: how many times, when it was first and last seen, and the details of the latest one.'],
            ['label' => 'Nothing listed is normal', 'text' => 'An empty log means nothing went wrong in the last ' . MOOP_ERROR_LOG_RETENTION_DAYS . ' days. Check it now and then, and after any change to the site.'],
            ['label' => 'Working through it', 'html' => true, 'text' => 'Open <strong>Latest occurrence</strong>, try the page it names to see the problem yourself, fix it, then press <strong>Fixed — remove</strong>. If it is not really fixed, it comes back the next time it happens.'],
            ['label' => 'Kept 30 days', 'text' => 'Entries name the user and address, so they are removed after ' . MOOP_ERROR_LOG_RETENTION_DAYS . ' days by the hourly maintenance run — the same rule as the System Monitor request log.'],
        ]],
        ['heading' => 'What is logged here', 'cards' => [
            ['label' => 'PHP crash', 'color' => 'danger', 'text' => 'A page stopped with a PHP error and the visitor saw an error page. Shown with the file and line. These are bugs — always worth fixing.'],
            ['label' => 'Search', 'text' => 'An organism\'s database is missing or cannot be read, so searches skip that organism.'],
            ['label' => 'Sequences', 'text' => 'Sequence Retrieval or a FASTA download could not read a sequence index: the BLAST index is missing, or the extraction tool failed. Rebuild the index from Organism Checklist.'],
            ['label' => 'Organism images', 'text' => 'A Wikipedia image could not be downloaded or saved, or an organism\'s details were missing when its image was looked up.'],
        ]],
        ['heading' => 'What is not logged here', 'cards' => [
            ['label' => 'Visitors\' typing mistakes', 'text' => 'IDs that do not exist, a bad range, an empty box. The visitor is told on the page; nothing here is wrong.'],
            ['label' => 'Server errors in general', 'text' => 'Admin → System Monitor counts every failed page (5xx) and names the pages, including failures this log never sees.'],
            ['label' => 'Security warnings and PHP warnings', 'text' => 'CSRF mismatches, proxy-header warnings and PHP warnings go to /var/log/php-fpm/www-error.log, which only root can read.'],
            ['label' => 'Maintenance failures', 'text' => 'A failed housekeeping task is shown on the Admin Dashboard, with its error.'],
        ]],
    ],
    ['intro' => 'Problems the site hit while serving pages, grouped so each one appears once.']
) ?>

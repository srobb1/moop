<?php
/**
 * NEWS - Content File (controller: admin/manage_news.php)
 * Variables: $notice, $news, $unreadable, $form, $groups, $panel_on, $file.
 */
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$cutoff = date('Y-m-d', time() - MOOP_NEWS_HOME_DAYS * 86400);
?>
<div class="container-fluid py-3" style="max-width: 1000px;">
  <h1 class="h3 mb-1"><i class="fa fa-bullhorn me-2"></i>News</h1>
  <p class="text-muted mb-3">The <strong>What's new</strong> box on the home page shows the newest <?= MOOP_NEWS_HOME_MAX ?> items from the
    last <?= MOOP_NEWS_HOME_DAYS ?> days; older ones leave the home page by themselves and stay on
    <a href="<?= $h(moop_url('tools/news.php')) ?>" target="_blank" rel="noopener">All news</a>. With nothing recent, the box is not shown.</p>

  <?php if (!$panel_on): ?>
    <div class="alert alert-warning py-2">This site's home page does not show news. To show it, choose
      “MOOP's home, with Getting started and What's new” in <a href="manage_pages.php">Manage Pages → Home page</a>.</div>
  <?php endif; ?>
  <?php if ($notice): ?><div class="alert alert-<?= $h($notice[0]) ?> py-2"><?= $h($notice[1]) ?></div><?php endif; ?>
  <?php if ($unreadable): ?>
    <div class="alert alert-danger">The news file could not be read: <code><?= $h($file) ?></code>. Nothing was changed. Check Filesystem Permissions.</div>
  <?php endif; ?>

  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0"><?= $form['id'] ? 'Edit item' : 'Add an item' ?></h5></div>
    <div class="card-body">
      <form method="post" action="manage_news.php">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="save">
        <input type="hidden" name="id" value="<?= $h($form['id']) ?>">
        <div class="row g-3">
          <div class="col-sm-3">
            <label class="form-label fw-semibold" for="news-date">Date</label>
            <input type="date" class="form-control" id="news-date" name="date" value="<?= $h($form['date']) ?>" required>
          </div>
          <div class="col-sm-9">
            <label class="form-label fw-semibold" for="news-text">What is new</label>
            <textarea class="form-control" id="news-text" name="text" rows="2" maxlength="<?= MOOP_NEWS_MAX_TEXT ?>" required
                      placeholder="[GO terms](tools/ontology_browser.php) in the Ontology Browser, with the genes for each term."><?= $h($form['text']) ?></textarea>
            <div class="form-text">One or two sentences. Markdown: <code>[link text](tools/blast.php)</code>, <code>**bold**</code>. Put details on a page and link to it.</div>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold" for="news-groups">Who sees it</label>
            <select class="form-select" id="news-groups" name="groups[]" multiple size="5" style="max-width: 24rem;">
              <?php foreach ($groups as $g): ?>
              <option value="<?= $h($g) ?>"<?= in_array($g, $form['groups'] ?? [], true) ? ' selected' : '' ?>><?= $h($g) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">Nothing selected = <strong>everyone</strong>. Select groups (Ctrl/Cmd-click for several) to show it only to
              visitors who can see something in one of them — use this for news about data that is not public yet.</div>
          </div>
        </div>
        <div class="mt-3 d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= $form['id'] ? 'Save' : 'Add' ?></button>
          <?php if ($form['id']): ?><a class="btn btn-outline-secondary" href="manage_news.php">Cancel</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <div class="card adm-card">
    <div class="card-header adm-head"><h5 class="mb-0">All items (<?= count($news) ?>)</h5></div>
    <?php if (!$news): ?>
      <div class="card-body text-muted">No news yet.</div>
    <?php else: ?>
    <ul class="list-group list-group-flush">
      <?php foreach ($news as $n): $old = $n['date'] < $cutoff; ?>
      <li class="list-group-item d-flex gap-3 align-items-start<?= $old ? ' text-muted' : '' ?>">
        <span class="text-nowrap small pt-1" style="min-width: 6.5rem;"><?= $h($n['date']) ?></span>
        <div class="flex-grow-1">
          <?= moop_news_html($n['text']) ?>
          <div class="small mt-1">
            <?= !empty($n['groups']) ? '<i class="fa fa-lock me-1"></i>Only visitors who can see: ' . $h(implode(', ', $n['groups'])) : 'Everyone' ?>
            <?= $old ? ' · older than ' . MOOP_NEWS_HOME_DAYS . ' days: on All news only' : '' ?>
          </div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $h($n['id']) ?>">Edit</a>
        <form method="post" action="manage_news.php">
          <?= csrf_input_field() ?>
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= $h($n['id']) ?>">
          <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
        </form>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
  <p class="small text-muted mt-2">Stored in <code><?= $h($file) ?></code> — site data, copied into the site-data backup with the rest of the metadata.</p>
</div>

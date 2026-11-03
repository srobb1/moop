<?php
/**
 * MANAGE PAGES — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $mode ('list'|'new'|'edit'), $page, $pages, $pages_dir, $flash.
 * Live preview: js/modules/manage-pages.js -> admin/api/preview_page.php (the publishing converter).
 */
$lang_names = ['en' => 'English', 'es' => 'Español'];
?>
<div class="container-fluid my-4" style="max-width: 1400px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-file-alt me-2" style="color:#0891b2;"></i>Manage Pages</h3>
    <?php if ($mode === 'list'): ?>
      <a href="manage_pages.php?new=1" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>New page</a>
    <?php else: ?>
      <a href="manage_pages.php" class="btn btn-outline-secondary btn-sm"><i class="fa fa-arrow-left me-1"></i>All pages</a>
    <?php endif; ?>
    <a href="admin.php" class="btn btn-outline-secondary btn-sm ms-auto"><i class="fa fa-arrow-left me-1"></i>Back to Dashboard</a>
  </div>

  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= $flash['msg'] ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

<?php if ($mode === 'list'): ?>
  <p class="text-muted">
    Pages you write yourself — About, protocols, FAQ — in <strong>Markdown</strong>, in English and
    Spanish. Each has its own address. A page is not in any menu until a menu links it.
  </p>
  <div class="card adm-card mb-3">
    <div class="card-header adm-head d-flex align-items-center">
      <h5 class="mb-0">Pages</h5><span class="badge bg-secondary ms-2"><?= count($pages) ?></span>
    </div>
    <div class="card-body p-0">
      <?php if (!$pages): ?>
        <p class="text-muted small p-3 mb-0">No pages yet. <a href="manage_pages.php?new=1">Write the first one.</a></p>
      <?php else: ?>
      <table class="table table-sm align-middle mb-0">
        <thead class="table-light"><tr><th class="ps-3">Title</th><th>Address</th><th>Spanish</th><th>Last saved</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $slug => $p): ?>
          <tr>
            <td class="ps-3"><a href="manage_pages.php?edit=<?= rawurlencode($slug) ?>"><strong><?= htmlspecialchars($p['title']['en']) ?></strong></a></td>
            <td class="small"><a href="<?= htmlspecialchars(moop_page_url($slug)) ?>" target="_blank"><code><?= htmlspecialchars(moop_page_url($slug)) ?></code></a></td>
            <td class="small"><?= trim($p['body']['es']) !== '' ? '<span class="badge bg-success">written</span>' : '<span class="badge bg-light text-dark">not yet</span>' ?></td>
            <td class="small text-muted"><?= htmlspecialchars($p['updated_at'] !== '' ? substr($p['updated_at'], 0, 16) : '—') ?><?= $p['updated_by'] !== '' ? ' · ' . htmlspecialchars($p['updated_by']) : '' ?></td>
            <td class="text-end pe-3">
              <form method="post" class="d-inline" onsubmit="return confirm('Delete this page? Links to it will stop working.');">
                <?= csrf_input_field() ?>
                <input type="hidden" name="_action" value="delete">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
  <p class="small text-muted">Stored in <code><?= htmlspecialchars($pages_dir) ?></code>, one file per page — site data,
    copied into the site-data backup with the rest of the metadata.</p>

<?php else: ?>
  <form method="post" id="page-form">
    <?= csrf_input_field() ?>
    <input type="hidden" name="_action" value="save">
    <input type="hidden" name="original_slug" value="<?= $mode === 'edit' ? htmlspecialchars($page['slug']) : '' ?>">

    <div class="row g-2 mb-3 align-items-end">
      <div class="col-md-6">
        <label class="form-label mb-1 small fw-semibold" for="slug">Address</label>
        <div class="input-group input-group-sm">
          <span class="input-group-text">/<?= htmlspecialchars($site) ?>/page.php?p=</span>
          <input type="text" class="form-control" id="slug" name="slug" required pattern="[a-z0-9][a-z0-9-]{0,59}"
                 value="<?= htmlspecialchars($page['slug']) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?>
                 title="Lower-case letters, digits and hyphens">
        </div>
        <div class="form-text"><?= $mode === 'edit' ? 'The address stays fixed, so links to the page keep working.'
                                                    : 'Filled in from the English title; change it if you like. It cannot change later.' ?></div>
      </div>
      <div class="col-md-6 text-md-end">
        <?php if ($mode === 'edit'): ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(moop_page_url($page['slug'])) ?>" target="_blank">
            <i class="fa fa-external-link-alt me-1"></i>Open the page</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save me-1"></i>Save</button>
      </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
      <?php foreach ($lang_names as $lang => $name): ?>
        <li class="nav-item" role="presentation">
          <button class="nav-link <?= $lang === 'en' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#lang-<?= $lang ?>"
                  type="button" role="tab"><?= $name ?></button>
        </li>
      <?php endforeach; ?>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3 mb-3">
      <?php foreach ($lang_names as $lang => $name): ?>
      <div class="tab-pane fade <?= $lang === 'en' ? 'show active' : '' ?>" id="lang-<?= $lang ?>" role="tabpanel">
        <div class="row g-3">
          <div class="col-lg-6">
            <label class="form-label mb-1 small fw-semibold" for="title_<?= $lang ?>">Title (<?= $name ?>)</label>
            <input type="text" class="form-control mb-2" id="title_<?= $lang ?>" name="title_<?= $lang ?>"
                   value="<?= htmlspecialchars($page['title'][$lang]) ?>" <?= $lang === 'en' ? 'required' : '' ?>>
            <label class="form-label mb-1 small fw-semibold" for="body_<?= $lang ?>">Text (Markdown)</label>
            <textarea class="form-control font-monospace mp-body" id="body_<?= $lang ?>" name="body_<?= $lang ?>" rows="22"
                      data-preview="preview_<?= $lang ?>" spellcheck="true"><?= htmlspecialchars($page['body'][$lang]) ?></textarea>
            <?php if ($lang === 'es'): ?>
              <div class="form-text">Shown to Spanish-language visitors once the site offers Spanish. Leave empty until then.</div>
            <?php endif; ?>
          </div>
          <div class="col-lg-6">
            <div class="small fw-semibold mb-1">Preview <span class="text-muted fw-normal">— exactly as the page will show it</span></div>
            <div class="border rounded p-3 bg-white content-page-body" id="preview_<?= $lang ?>" style="min-height: 30rem; overflow:auto;"
                 aria-live="polite"><span class="text-muted small">Start typing to see the page.</span></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </form>

  <details class="small text-muted mb-4">
    <summary>Markdown in brief</summary>
    <table class="table table-sm mt-2" style="max-width: 640px;">
      <tr><td><code># Heading</code>, <code>## Smaller heading</code></td><td>headings</td></tr>
      <tr><td><code>**bold**</code>, <code>*italic*</code></td><td>emphasis</td></tr>
      <tr><td><code>- item</code> or <code>1. item</code></td><td>lists</td></tr>
      <tr><td><code>[link text](https://example.org)</code></td><td>a link</td></tr>
      <tr><td><code>![description](/<?= htmlspecialchars($site) ?>/images/picture.jpg)</code></td><td>an image</td></tr>
      <tr><td><code>| A | B |</code> then <code>|---|---|</code></td><td>a table</td></tr>
      <tr><td>A blank line</td><td>starts a new paragraph</td></tr>
    </table>
    HTML typed into a page is shown as text, not run — pages cannot carry scripts.
  </details>
<?php endif; ?>
</div>

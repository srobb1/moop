<?php
/**
 * MANAGE PAGES — content (no <html>/<body>; rendered via layout.php).
 * Variables from $data: $site, $mode ('list'|'new'|'edit'), $page, $pages, $pages_dir, $flash,
 * $menu_place ('none'|'top'|'dd:<key>'), $menu_dropdowns [key => label], $home (homeSetting()).
 * Live preview: js/modules/manage-pages.js -> admin/api/preview_page.php (the publishing converter).
 */
// The site's active languages (Admin -> Manage Languages), English first. Retired languages are
// not shown, and their text is kept (lib/content_pages.php).
$lang_names = array_column(ConfigManager::getInstance()->languages(), 'name', 'code');
$other_langs = array_diff_key($lang_names, ['en' => true]);
?>
<div class="container-fluid my-4" style="max-width: 1400px;">

  <div class="d-flex align-items-center gap-3 mb-3">
    <h3 class="mb-0"><i class="fa fa-file-alt me-2" style="color:var(--moop-tool-header);"></i>Manage Pages</h3>
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

<?php if (!$access_ok): ?>
    <div class="alert alert-danger"><strong><?= htmlspecialchars(basename(moop_access_rules_file())) ?> cannot be read.</strong>
      Until it is fixed, every page is shown to admins only, and access cannot be changed here. File: <code><?= htmlspecialchars(moop_access_rules_file()) ?></code></div>
  <?php endif; ?>
<?php
// The access button for one page: opens the shared dialog (js/modules/access-rule-dialog.js).
$access_button = function (array $p, string $extra = '') {
    $rule = moop_page_rule($p['slug']);
    $open = ($rule['visible_to'] ?? '') === 'everyone';
    return '<button type="button" class="btn btn-sm ' . ($open ? 'btn-outline-secondary' : 'btn-outline-warning') . ' js-access-edit ' . $extra . '"'
         . ' data-kind="page" data-key="' . htmlspecialchars($p['slug']) . '" data-title="' . htmlspecialchars($p['title']['en']) . '"'
         . ' data-rule="' . htmlspecialchars(json_encode($rule)) . '" title="Change who can see this page">'
         . '<i class="fa ' . ($open ? 'fa-lock-open' : 'fa-lock') . ' me-1"></i>' . htmlspecialchars(moop_access_rule_label($rule)) . '</button>';
};
?>
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
      <table class="table table-sm align-middle mb-0" id="pages-table">
        <thead class="table-light"><tr><th class="ps-3">Title</th><th>Address</th><?php if ($other_langs): ?><th>Translations</th><?php endif; ?><th>Visible to</th><th>Last saved</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pages as $slug => $p): ?>
          <tr>
            <td class="ps-3"><a href="manage_pages.php?edit=<?= rawurlencode($slug) ?>"><strong><?= htmlspecialchars($p['title']['en']) ?></strong></a>
              <?php if ($home['page'] === $slug): ?><span class="badge bg-info text-dark ms-1"><?= $home['mode'] === 'page' ? 'home page' : 'on the home page' ?></span><?php endif; ?></td>
            <td class="small"><a href="<?= htmlspecialchars(moop_page_url($slug)) ?>" target="_blank"><code><?= htmlspecialchars(moop_page_url($slug)) ?></code></a></td>
            <?php if ($other_langs): ?><td class="small"><?php foreach ($other_langs as $code => $name): ?>
              <span class="badge <?= trim($p['body'][$code] ?? '') !== '' ? 'bg-success' : 'bg-light text-dark' ?>" title="<?= htmlspecialchars($name) ?>: <?= trim($p['body'][$code] ?? '') !== '' ? 'written' : 'not yet' ?>"><?= htmlspecialchars($code) ?></span>
            <?php endforeach; ?></td><?php endif; ?>
            <td><?= $access_button($p) ?></td>
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
  <div class="card adm-card mb-3">
    <div class="card-header adm-head"><h5 class="mb-0">Home page</h5></div>
    <div class="card-body">
      <form method="post">
        <?= csrf_input_field() ?>
        <input type="hidden" name="_action" value="save_home">
        <p class="small text-muted mb-2">What visitors see at <code><?= htmlspecialchars(moop_url_base()) ?>/</code>. The address stays the same
          whichever you choose; if the page you pick is ever deleted, MOOP's home comes back.</p>
        <?php foreach (['builtin' => 'MOOP\'s home — search, organisms and tools', 'intro' => 'MOOP\'s home, with a page above it (a welcome)',
                        'page' => 'One of your pages, instead of MOOP\'s home'] as $value => $label): ?>
          <div class="form-check">
            <input class="form-check-input" type="radio" name="home_mode" id="home_<?= $value ?>" value="<?= $value ?>"
                   <?= $home['mode'] === $value ? 'checked' : '' ?> <?= $value !== 'builtin' && !$pages ? 'disabled' : '' ?>>
            <label class="form-check-label" for="home_<?= $value ?>"><?= $label ?></label>
          </div>
        <?php endforeach; ?>
        <div class="d-flex gap-2 align-items-center mt-2">
          <select class="form-select form-select-sm" name="home_page" style="max-width: 22rem;" <?= !$pages ? 'disabled' : '' ?>>
            <option value="">— choose a page —</option>
            <?php foreach ($pages as $slug => $p): ?>
              <option value="<?= htmlspecialchars($slug) ?>" <?= $home['page'] === $slug ? 'selected' : '' ?>><?= htmlspecialchars($p['title']['en']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-sm btn-primary">Save</button>
        </div>
        <?php if (!$pages): ?><p class="small text-muted mt-2 mb-0">Write a page first to use it here.</p><?php endif; ?>
        <p class="small text-muted mt-2 mb-0">MOOP's own home is always at
          <a href="<?= htmlspecialchars(moop_url_base()) ?>/browse.php" target="_blank"><code><?= htmlspecialchars(moop_url_base()) ?>/browse.php</code></a>.
          <?php if ($home['mode'] === 'page'): ?><strong>Your page replaces it at <code><?= htmlspecialchars(moop_url_base()) ?>/</code></strong> — add it to the
          <a href="manage_menu.php">menu</a> (“MOOP's search &amp; browse page”) so visitors can still reach the search and tools.<?php endif; ?></p>
      </form>
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
          <span class="input-group-text"><?= htmlspecialchars(moop_url_base()) ?>/page.php?p=</span>
          <input type="text" class="form-control" id="slug" name="slug" required pattern="[a-z0-9][a-z0-9-]{0,59}"
                 value="<?= htmlspecialchars($page['slug']) ?>" <?= $mode === 'edit' ? 'readonly' : '' ?>
                 title="Lower-case letters, digits and hyphens">
        </div>
        <div class="form-text"><?= $mode === 'edit' ? 'The address stays fixed, so links to the page keep working.'
                                                    : 'Filled in from the English title; change it if you like. It cannot change later.' ?></div>
      </div>
      <div class="col-md-3">
        <label class="form-label mb-1 small fw-semibold" for="menu_place">Show in menu</label>
        <select class="form-select form-select-sm" id="menu_place" name="menu_place">
          <option value="none" <?= $menu_place === 'none' ? 'selected' : '' ?>>Not in a menu (link to it from another page)</option>
          <option value="top" <?= $menu_place === 'top' ? 'selected' : '' ?>>On the menu bar itself</option>
          <?php foreach ($menu_dropdowns as $key => $label): ?>
            <option value="dd:<?= htmlspecialchars($key) ?>" <?= $menu_place === 'dd:' . $key ? 'selected' : '' ?>>Under ▸ <?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="form-text">New drop-downs and the order within them: <a href="manage_menu.php">Manage Menu</a>.</div>
      </div>
      <div class="col-md-3 text-md-end">
        <?php if ($mode === 'edit'): ?>
          <?= $access_button($page) ?>
          <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars(moop_page_url($page['slug'])) ?>" target="_blank">
            <i class="fa fa-external-link-alt me-1"></i>Open the page</a>
        <?php endif; ?>
        <button type="submit" class="btn btn-sm btn-primary"><i class="fa fa-save me-1"></i>Save</button>
      </div>
    </div>

    <div class="d-flex align-items-center gap-3 mb-2">
      <span class="small fw-semibold">Written in</span>
      <div class="btn-group btn-group-sm" role="group" aria-label="Page format">
        <input type="radio" class="btn-check" name="format" id="format_markdown" value="markdown" <?= $page['format'] !== 'html' ? 'checked' : '' ?>>
        <label class="btn btn-outline-secondary" for="format_markdown">Markdown</label>
        <input type="radio" class="btn-check" name="format" id="format_html" value="html" <?= $page['format'] === 'html' ? 'checked' : '' ?>>
        <label class="btn btn-outline-secondary" for="format_html">HTML</label>
      </div>
      <span class="small text-muted" id="format-note"><?= $page['format'] === 'html'
          ? 'Your own HTML — cards, grids, Bootstrap classes. Scripts and anything that can run are removed.'
          : 'Simple formatting with a few marks (see "Markdown in brief" below).' ?></span>
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
            <label class="form-label mb-1 small fw-semibold" for="body_<?= $lang ?>">Text</label>
            <textarea class="form-control font-monospace mp-body" id="body_<?= $lang ?>" name="body_<?= $lang ?>" rows="22"
                      data-preview="preview_<?= $lang ?>" spellcheck="true"><?= htmlspecialchars($page['body'][$lang]) ?></textarea>
            <?php if ($lang !== 'en'): ?>
              <div class="form-text">Shown to visitors reading in <?= htmlspecialchars($name) ?>. Left empty, they see the English.</div>
            <?php endif; ?>
          </div>
          <div class="col-lg-6">
            <div class="small fw-semibold mb-1">Preview <span class="text-muted fw-normal">— exactly as the page will show it</span></div>
            <div class="border rounded p-3 bg-white content-page-body" id="preview_<?= $lang ?>" style="min-height: 30rem; overflow:auto;"
                 aria-live="polite"><span class="text-muted small">Start typing to see the page.</span></div>
            <div class="alert alert-warning small mt-2 mb-0 d-none mp-removed" id="removed_<?= $lang ?>" role="status"></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </form>

  <?php if ($mode === 'edit'): ?>
  <div class="small mb-3">
    <span class="fw-semibold">Link to this page from another page:</span>
    <?= adm_command('[' . str_replace(['[', ']'], '', $page['title']['en']) . '](' . moop_page_url($page['slug']) . ')', 'Copy this Markdown link, then paste it into another page') ?>
  </div>
  <?php endif; ?>

  <?php
  // Every address an editor can link to, ready to copy, in the page's format. Built from the live
  // site -- its pages, MOOP's own pages, its switched-on tools -- so it is never out of date.
  $lk = [['Home page', moop_url_base() . "/"], ["MOOP's search & browse page (always MOOP's home)", moop_url_base() . "/browse.php"],
         ['About', moop_url_base() . "/about.php"], ['Help', moop_url_base() . "/help.php"]];
  foreach ($pages as $lslug => $lp) $lk[] = ['Page: ' . $lp['title']['en'], moop_page_url($lslug)];
  foreach ($tool_links ?? [] as $tl) if ($tl['type'] === 'tool') $lk[] = ['Tool: ' . $tl['label'], moop_url_base() . $tl['url_path']];
  ?>
  <details class="small mb-3" open>
    <summary class="fw-semibold">Links you can use — copy one into your text</summary>
    <p class="text-muted mt-2 mb-1">To link to another page: in <strong>Markdown</strong>, <code>[words to click](address)</code>; in
      <strong>HTML</strong>, <code>&lt;a href="address"&gt;words to click&lt;/a&gt;</code>. A page that is <em>Not in a menu</em> is
      reached only through links like these — for example, a Protocols overview page listing each protocol.
      Addresses never change once a page is created.</p>
    <table class="table table-sm mb-0" style="max-width: 900px;">
      <?php foreach ($lk as [$what, $addr]): ?>
        <tr><td class="text-nowrap"><?= htmlspecialchars($what) ?></td><td><code><?= htmlspecialchars($addr) ?></code></td>
          <td><?= adm_command($page['format'] === 'html'
                     ? '<a href="' . $addr . '">' . preg_replace('/^(Page|Tool): /', '', $what) . '</a>'
                     : '[' . str_replace(['[', ']'], '', preg_replace('/^(Page|Tool): /', '', $what)) . '](' . $addr . ')',
                   'Copy this link, then paste it into your text') ?></td></tr>
      <?php endforeach; ?>
    </table>
    <p class="text-muted mt-1 mb-0">Outside sites work too: <code>[Planosphere](https://planosphere.stowers.org/)</code>.</p>
  </details>

  <details class="small text-muted mb-4">
    <summary>Markdown in brief</summary>
    <table class="table table-sm mt-2" style="max-width: 640px;">
      <tr><td><code># Heading</code>, <code>## Smaller heading</code></td><td>headings</td></tr>
      <tr><td><code>**bold**</code>, <code>*italic*</code></td><td>emphasis</td></tr>
      <tr><td><code>- item</code> or <code>1. item</code></td><td>lists</td></tr>
      <tr><td><code>[link text](https://example.org)</code></td><td>a link</td></tr>
      <tr><td><code>![description](<?= htmlspecialchars(moop_url_base()) ?>/images/picture.jpg)</code></td><td>an image</td></tr>
      <tr><td><code>| A | B |</code> then <code>|---|---|</code></td><td>a table</td></tr>
      <tr><td>A blank line</td><td>starts a new paragraph</td></tr>
    </table>
    HTML typed into a Markdown page is shown as text, not run — pages cannot carry scripts.
  </details>
  <details class="small text-muted mb-4">
    <summary>HTML pages in brief</summary>
    <p class="mt-2 mb-1">Write or paste any HTML, for example cards in a row:</p>
    <pre class="bg-light p-2 rounded">&lt;div class="row g-3"&gt;
  &lt;div class="col-md-4"&gt;&lt;div class="card h-100"&gt;&lt;div class="card-body"&gt;
    &lt;h5 class="card-title"&gt;Planarian Maintenance&lt;/h5&gt;
    &lt;p&gt;Keeping your worms healthy.&lt;/p&gt;
    &lt;a class="btn btn-primary" href="<?= htmlspecialchars(moop_url_base()) ?>/page.php?p=planarian-maintenance"&gt;Read&lt;/a&gt;
  &lt;/div&gt;&lt;/div&gt;&lt;/div&gt;
&lt;/div&gt;</pre>
    Kept: headings, paragraphs, lists, tables, links, images, <code>div</code>/<code>span</code>, collapsible
    <code>&lt;details&gt;&lt;summary&gt;Question&lt;/summary&gt;…&lt;/details&gt;</code> sections, and
    <code>class</code>, <code>style</code> and <code>title</code> on any of them — so Bootstrap classes work. Removed:
    <code>&lt;script&gt;</code>, <code>onclick=</code>-style handlers, <code>javascript:</code> links,
    <code>&lt;iframe&gt;</code>, <code>&lt;form&gt;</code> and <code>&lt;style&gt;</code>. The preview lists anything removed.
  </details>
<?php endif; ?>
</div>
<?php include __DIR__ . '/_access_rule_dialog.php'; ?>

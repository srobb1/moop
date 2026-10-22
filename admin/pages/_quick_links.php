<?php
/**
 * Quick Links -- the admin sidebar, drawn on every admin page by includes/layout.php.
 *
 * Links go to other PAGES (unlike the gene page's section nav, which scrolls within its page),
 * so each entry carries its tool's icon and the current page is marked rather than tracked
 * by scrollspy. The list is moop_admin_tools() -- the same one the dashboard's tool cards use.
 * Counts beside a link are moop_admin_tool_badges(): figures the dashboard already shows.
 * Below lg the list folds behind a "Quick Links" button so it does not push the page down.
 */
require_once __DIR__ . '/../../lib/admin_tools.php';
$_ql_current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$_ql_badges  = moop_admin_tool_badges();
$_ql_link = function (string $file, string $label, string $icon) use ($_ql_current, $_ql_badges): string {
    $here  = $file === $_ql_current;
    $badge = $_ql_badges[$file] ?? null;
    return '<a href="' . htmlspecialchars($file) . '" class="adm-ql-link' . ($here ? ' active' : '') . '"'
         . ($here ? ' aria-current="page"' : '') . '>'
         . '<i class="fa fa-' . htmlspecialchars($icon) . ' fa-fw"></i>'
         . '<span class="adm-ql-label">' . htmlspecialchars($label) . '</span>'
         . ($badge ? '<span class="badge rounded-pill bg-' . $badge['tone'] . ' adm-ql-badge" title="'
                   . htmlspecialchars($badge['title']) . '">' . htmlspecialchars((string)$badge['n']) . '</span>' : '')
         . '</a>';
};
?>
<nav class="adm-quicklinks" aria-label="Admin quick links">
  <button class="btn btn-sm adm-ql-toggle d-lg-none w-100 mb-2" type="button"
          data-bs-toggle="collapse" data-bs-target="#admQuickLinks" aria-expanded="false" aria-controls="admQuickLinks">
    <i class="fa fa-bars me-1"></i> Quick Links<?= $_ql_badges ? ' <span class="badge rounded-pill bg-warning ms-1">' . count($_ql_badges) . '</span>' : '' ?>
  </button>
  <div class="collapse d-lg-block" id="admQuickLinks">
    <div class="adm-ql-title d-none d-lg-block">Quick Links</div>
    <?= $_ql_link('admin.php', 'Dashboard', 'tachometer-alt') ?>
    <?php foreach (moop_admin_tools() as $_ql_group): ?>
      <div class="adm-ql-group"><?= htmlspecialchars($_ql_group['label']) ?></div>
      <?php foreach ($_ql_group['tools'] as $_ql_tool): ?>
        <?= $_ql_link($_ql_tool['file'], $_ql_tool['label'], $_ql_tool['icon']) ?>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</nav>
<?php unset($_ql_current, $_ql_badges, $_ql_link, $_ql_group, $_ql_tool); ?>

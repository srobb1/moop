<?php
/**
 * A content page's body (page.php). Variables: $page_title (plain text), $page_html (from
 * moop_markdown() -- Parsedown safe mode, so it is already safe to print), and $title_lang /
 * $body_lang: 'en' when that part is the English fallback on a page shown in another language.
 */
?>
<div class="container my-4 content-page" style="max-width: 900px;">
<?php /* A page whose body brings its own main heading (cuttingclass's pages open with a banner and
         an <h1>) would otherwise show its title twice. The browser tab keeps the title either way. */ ?>
<?php if (stripos($page_html, '<h1') === false): ?>
  <h1 class="fw-bold mb-3"<?= !empty($title_lang) ? ' lang="' . htmlspecialchars($title_lang) . '"' : '' ?>><?= htmlspecialchars($page_title) ?></h1>
<?php endif; ?>
  <div class="content-page-body"<?= !empty($body_lang) ? ' lang="' . htmlspecialchars($body_lang) . '"' : '' ?>>
    <?= $page_html ?>
  </div>
</div>

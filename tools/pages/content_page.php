<?php
/**
 * A content page's body (page.php). Variables: $page_title (plain text), $page_html (from
 * moop_markdown() -- Parsedown safe mode, so it is already safe to print).
 */
?>
<div class="container my-4 content-page" style="max-width: 900px;">
  <h1 class="fw-bold mb-3"><?= htmlspecialchars($page_title) ?></h1>
  <div class="content-page-body">
    <?= $page_html ?>
  </div>
</div>

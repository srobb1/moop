<?php
/**
 * Glossary — every defined term, listed.
 *
 * Rendered straight from metadata/glossary.json (glossary_entries()), the same file
 * every dashed-underlined word on the site reads — so this list and those popovers
 * cannot disagree, and an admin's edit in Manage Glossary shows up here with no code.
 *
 * Available: $config, $siteTitle
 */

$entries = glossary_entries();
uksort($entries, 'strcasecmp');
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-book-open me-2"></i>Glossary
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        The terms <?= htmlspecialchars($siteTitle) ?> uses, in one list. These are the same definitions
        you see when you point at a dashed-underlined
        word anywhere on the site.
      </p>
    </div>
  </div>

  <?php if (empty($entries)): ?>
    <div class="alert alert-warning">No glossary terms are defined on this site yet.</div>
  <?php else: ?>

  <div class="row mb-4">
    <div class="col-lg-7">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
        <input type="search" class="form-control border-start-0 moop-input"
               data-help-filter=".glossary-entry" data-filter-msg="glossary-filter-msg"
               data-filter-noun="term" data-filter-nouns="terms"
               placeholder="Find a term — try &quot;gene set&quot;, &quot;GO&quot;, &quot;protein&quot;…"
               autocomplete="off" spellcheck="false" aria-label="Filter glossary terms">
      </div>
      <div id="glossary-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <div class="row">
    <?php foreach ($entries as $term => $def): ?>
      <div class="col-md-6 mb-3 glossary-entry"
           data-search="<?= htmlspecialchars(strtolower($term . ' ' . $def), ENT_QUOTES) ?>">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h2 class="h6 fw-semibold mb-1"><?= htmlspecialchars($term) ?></h2>
            <p class="text-muted small mb-0"><?= htmlspecialchars($def) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <?php endif; ?>
</div>

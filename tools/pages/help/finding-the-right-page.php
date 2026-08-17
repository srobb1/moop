<?php
/**
 * "Which page do I want?" — the router.
 *
 * Renders from docs/page_purposes.json, which scripts/extract_page_purposes.php builds
 * by reading each page's own page_purpose() declaration off the RENDERED page. So every
 * sentence here is the sentence that page actually shows or declares — there is no second
 * copy to drift, and a page that stops declaring one simply drops out.
 *
 * Refresh with:  php scripts/extract_page_purposes.php --write
 *
 * ⚠️ Not every page can be linked. organism / assembly / gene set / group pages need a real
 * id in the URL, and any link written here would hard-code whichever organism happened to be
 * public the day it was written. Those carry a per-route "how you get there" instead, written
 * in the extractor's $meta — NOT a generic line appended to anything without a link. These
 * pages form a chain (organism -> assembly -> gene set), so "pick an organism first" is wrong
 * on the gene-set card. A card can list more than one route when two pages were combined.
 *
 * Available: $config, $siteTitle
 */

$site = $config->getString('site');
$file = __DIR__ . '/../../../docs/page_purposes.json';
$data = function_exists('loadJsonFile') ? loadJsonFile($file, []) : [];
$routes = $data['routes'] ?? [];
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-map-signs me-2"></i>Which page do I want?
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        What each page of <?= htmlspecialchars($siteTitle) ?> is for, in one sentence. Start here if you
        know what you want to do but not where to do it.
      </p>
    </div>
  </div>

  <?php if (empty($routes)): ?>
    <div class="alert alert-warning">
      No page list found. Regenerate it with
      <code>php scripts/extract_page_purposes.php --write</code>.
    </div>
  <?php else: ?>

  <div class="row mb-4">
    <div class="col-lg-7">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
        <input type="search" id="route-filter" class="form-control border-start-0 moop-input"
               placeholder="What do you want to do? — try &quot;download&quot;, &quot;sequence&quot;, &quot;annotation&quot;…"
               autocomplete="off" spellcheck="false" aria-label="Filter pages">
      </div>
      <div id="route-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <div class="row" id="route-list">
    <?php foreach ($routes as $r): ?>
      <?php $search = strtolower($r['title'] . ' ' . $r['purpose']); ?>
      <div class="col-md-6 mb-3 route-card" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3 d-flex flex-column">
            <h6 class="fw-semibold mb-1 text-body"><?= htmlspecialchars($r['title']) ?></h6>
            <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars($r['purpose']) ?></p>
            <?php if (!empty($r['linkable']) && !empty($r['link'])): ?>
              <a class="btn btn-sm btn-outline-moop align-self-start"
                 href="/<?= htmlspecialchars($site) ?><?= htmlspecialchars($r['link']) ?>">
                Go to <?= htmlspecialchars($r['title']) ?> <i class="fa fa-arrow-right ms-1"></i>
              </a>
            <?php else: ?>
              <?php /* No link on purpose — see the header comment. The route is written PER
                       PAGE rather than appended generically: these pages form a chain
                       (organism -> assembly -> gene set), so "pick an organism first" is
                       simply wrong on the gene-set card. A card can carry more than one route
                       when two pages were combined. */ ?>
              <?php foreach ((array)($r['reached_by'] ?? []) as $how): ?>
                <span class="small text-muted align-self-start d-block">
                  <i class="fa fa-info-circle me-1"></i><?= htmlspecialchars($how) ?>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="text-muted small">
    Every page above can be reached from the <a href="/<?= htmlspecialchars($site) ?>/">home page</a>.
  </p>

  <script>
  /* Same filter shape as the help landing: match a data attribute built server-side, never
     rendered text. Task-first wording is the point — someone types "download", not the name
     of the page they have never heard of. */
  document.addEventListener('DOMContentLoaded', function () {
      var input = document.getElementById('route-filter');
      if (!input) return;
      var msg   = document.getElementById('route-filter-msg');
      var cards = Array.prototype.slice.call(document.querySelectorAll('.route-card'));
      function apply() {
          var q = input.value.trim().toLowerCase(), shown = 0;
          cards.forEach(function (c) {
              var hit = !q || (c.dataset.search || '').indexOf(q) !== -1;
              c.style.display = hit ? '' : 'none';
              if (hit) shown++;
          });
          if (msg) {
              msg.style.display = q ? '' : 'none';
              msg.textContent = shown === 0
                  ? 'No page matches “' + input.value.trim() + '”'
                  : shown + (shown === 1 ? ' page' : ' pages') + ' match';
          }
      }
      input.addEventListener('input', apply);
      input.addEventListener('search', apply);
  });
  </script>

  <?php endif; ?>
</div>

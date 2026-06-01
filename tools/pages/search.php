<?php
/**
 * Annotation Search — Display Page
 * Variables: $scope_tree, $organism_info, $all_organisms, $site, $siteTitle
 */
?>
<div class="container mt-4">

  <!-- Header -->
  <div class="mb-4">
    <h4 class="mb-1 fw-bold text-dark">
      <i class="fa fa-search me-1"></i> Annotation Search
      <i class="fa fa-info-circle search-instructions-trigger ms-1"
         style="cursor:pointer; font-size:0.8em;" data-help-type="basic"></i>
    </h4>
    <p class="text-muted small mb-0">
      <strong>Find specific genes</strong> by ID or keyword across organisms and annotation sources.
      Use <a href="moopmart.php" class="text-decoration-none">MOOPmart</a> to bulk-download many genes at once.
    </p>
  </div>

  <form id="searchForm">

    <!-- ① Keyword -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center">
        <span class="step-badge me-2">1</span>
        <strong>Enter a gene ID or annotation keyword</strong>
      </div>
      <div class="card-body py-3">
        <input type="text" class="form-control" id="searchKeywords"
               placeholder="e.g. BRCA1, zinc finger, GO:0006351 (minimum 3 characters)…">
      </div>
    </div>

    <!-- ② Organisms -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2">
        <span class="step-badge me-2">2</span>
        <strong class="me-auto">Limit to specific organisms</strong>
        <small class="text-muted fst-italic">leave unchecked to search all</small>
        <div class="d-flex gap-1 ms-2">
          <button type="button" id="scope-select-all" class="btn btn-sm btn-outline-secondary">All</button>
          <button type="button" id="scope-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
        </div>
      </div>
      <div class="px-2 pt-2 pb-1 border-bottom">
        <input type="text" class="form-control form-control-sm" id="scope-filter"
               placeholder="Filter organisms, assemblies, gene sets…" autocomplete="off">
      </div>
      <div class="card-body p-2" style="overflow-y:auto; max-height:300px;">
        <?php if (empty($scope_tree)): ?>
          <p class="text-muted small p-2">No accessible organisms found.</p>
        <?php else: ?>

        <?php
        // Group-name → color (same palette + hash as index page JS)
        $gp = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'];
        $groupColor = fn($n) => $gp[abs(array_sum(array_map('ord', str_split($n))) * 31) % count($gp)];
        ?>

        <?php /* Hidden scope checkboxes — keep for JS cascade/sync logic */ ?>
        <div class="d-none" id="scope-hidden-cbs">
          <?php $oi = 0; foreach ($scope_tree as $organism => $assemblies): $oi++;
            $oid = 'so_' . $oi; ?>
          <input type="checkbox" class="scope-org-cb" id="<?= $oid ?>"
                 data-org="<?= htmlspecialchars($organism) ?>">
          <?php $ai = 0; foreach ($assemblies as $assembly => $gene_sets): $ai++;
            $aid = $oid . '_a' . $ai; ?>
          <input type="checkbox" class="scope-asm-cb" id="<?= $aid ?>"
                 data-org="<?= htmlspecialchars($organism) ?>"
                 data-asm="<?= htmlspecialchars($assembly) ?>">
          <?php $gi = 0; foreach ($gene_sets as $gs): $gi++; ?>
          <input type="checkbox" class="scope-gs-cb"
                 data-org="<?= htmlspecialchars($organism) ?>"
                 data-asm="<?= htmlspecialchars($assembly) ?>"
                 data-gs="<?= htmlspecialchars($gs) ?>">
          <?php endforeach; endforeach; endforeach; ?>
        </div>

        <?php /* Visual organism selector — same chip style as index page */ ?>
        <div id="scope-org-list">
          <?php foreach ($scope_tree as $organism => $assemblies):
            $info   = $organism_info[$organism] ?? [];
            $genus  = $info['genus']       ?? '';
            $sp     = $info['species']     ?? '';
            $cn     = $info['common_name'] ?? '';
            $label  = trim("$genus $sp") ?: str_replace('_', ' ', $organism);
            $groups = $organism_groups[$organism] ?? [];
            $search = htmlspecialchars(strtolower("$label $cn " . implode(' ', $groups)));
          ?>
          <div class="org-select-row scope-org-row-item"
               data-org="<?= htmlspecialchars($organism) ?>"
               data-search="<?= $search ?>">
            <span class="org-groups">
              <?php foreach ($groups as $g): ?>
              <span class="org-group-chip" style="background:<?= $groupColor($g) ?>"><?= htmlspecialchars($g) ?></span>
              <?php endforeach; ?>
            </span>
            <span class="org-name"><em><?= htmlspecialchars($label) ?></em></span>
            <?php if ($cn): ?>
              <span class="org-common text-muted">· <?= htmlspecialchars($cn) ?></span>
            <?php endif; ?>
            <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
          </div>
          <?php endforeach; ?>
        </div>

        <?php endif; ?>
      </div>
      <div class="card-footer py-1 px-2 text-muted" style="font-size:0.8rem;">
        <span id="scope-summary"></span>
      </div>
    </div>

    <!-- ③ Annotation Sources -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2">
        <span class="step-badge me-2">3</span>
        <strong class="me-auto">Search only specific annotation sources</strong>
        <small class="text-muted fst-italic">leave unchecked to search all</small>
        <div class="d-flex gap-1 ms-2">
          <button type="button" id="sources-select-all" class="btn btn-sm btn-outline-secondary">All</button>
          <button type="button" id="sources-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
        </div>
      </div>
      <div class="px-2 pt-2 pb-1 border-bottom" id="sources-filter-wrap" style="display:none;">
        <input type="text" class="form-control form-control-sm" id="sources-filter"
               placeholder="Filter sources…" autocomplete="off">
      </div>
      <div class="card-body p-2" id="sourcesPanel" style="overflow-y:auto; max-height:300px;">
        <div class="text-center p-3 text-muted">
          <i class="fa fa-spinner fa-spin me-1"></i> Loading sources…
        </div>
      </div>
      <div class="card-footer py-1 px-2 text-muted" style="font-size:0.8rem;">
        <span id="sources-summary"></span>
      </div>
    </div>

    <!-- ④ Search -->
    <div class="mb-4">
      <button type="submit" class="btn btn-lg w-100" id="searchBtn"
              style="background:#7c3aed; border-color:#7c3aed; color:#fff; font-size:1.1rem;">
        <i class="fa fa-search me-2"></i>Search
      </button>
    </div>

  </form>

  <!-- Search Results -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-search-results text-white">
        <h4 class="mb-0">
          <i class="fa fa-list"></i> Search Results
          <i class="fa fa-info-circle search-results-help-trigger"
             style="cursor:pointer; margin-left:0.5rem; font-size:0.9em;"
             data-help-type="results"></i>
        </h4>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

</div>

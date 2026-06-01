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
        <div id="scope-tree">
          <?php $oi = 0; foreach ($scope_tree as $organism => $assemblies): $oi++;
            $info   = $organism_info[$organism] ?? [];
            $genus  = $info['genus']       ?? '';
            $sp     = $info['species']     ?? '';
            $cn     = $info['common_name'] ?? '';
            $label  = trim("$genus $sp") ?: str_replace('_', ' ', $organism);
            $oid    = 'so_' . $oi;
          ?>
          <div class="scope-org mb-1" data-org="<?= htmlspecialchars($organism) ?>">
            <div class="d-flex align-items-center gap-1 px-1 py-1 rounded scope-org-row" style="background:#f8f9fa;">
              <input type="checkbox" class="form-check-input flex-shrink-0 scope-org-cb mb-0"
                     id="<?= $oid ?>" data-org="<?= htmlspecialchars($organism) ?>">
              <label for="<?= $oid ?>" class="form-check-label fw-semibold mb-0 me-auto"
                     style="cursor:pointer; font-size:0.9rem;">
                <em><?= htmlspecialchars($label) ?></em>
                <?php if ($cn): ?>
                  <span class="text-muted fw-normal">(<?= htmlspecialchars($cn) ?>)</span>
                <?php endif; ?>
              </label>
              <i class="fa fa-chevron-down scope-toggle text-muted"
                 style="cursor:pointer; font-size:0.75rem;" data-target="<?= $oid ?>-body"></i>
            </div>
            <div id="<?= $oid ?>-body" class="ps-3 pt-1">
              <?php $ai = 0; foreach ($assemblies as $assembly => $gene_sets): $ai++;
                $aid = $oid . '_a' . $ai;
              ?>
              <div class="scope-asm mb-1" data-org="<?= htmlspecialchars($organism) ?>"
                   data-asm="<?= htmlspecialchars($assembly) ?>">
                <div class="d-flex align-items-center gap-1 px-1 py-1 rounded" style="background:#fff3cd20;">
                  <input type="checkbox" class="form-check-input flex-shrink-0 scope-asm-cb mb-0"
                         id="<?= $aid ?>" data-org="<?= htmlspecialchars($organism) ?>"
                         data-asm="<?= htmlspecialchars($assembly) ?>">
                  <label for="<?= $aid ?>" class="form-check-label fw-semibold mb-0 me-auto"
                         style="cursor:pointer; font-size:0.85rem; color:#b45309;">
                    <?= htmlspecialchars($assembly) ?>
                  </label>
                </div>
                <div class="ps-3">
                  <?php $gi = 0; foreach ($gene_sets as $gs): $gi++;
                    $gsid = $aid . '_g' . $gi;
                  ?>
                  <div class="d-flex align-items-center gap-1 px-1 py-1">
                    <input type="checkbox" class="form-check-input flex-shrink-0 scope-gs-cb mb-0"
                           id="<?= $gsid ?>" data-org="<?= htmlspecialchars($organism) ?>"
                           data-asm="<?= htmlspecialchars($assembly) ?>"
                           data-gs="<?= htmlspecialchars($gs) ?>">
                    <label for="<?= $gsid ?>" class="form-check-label mb-0"
                           style="cursor:pointer; font-size:0.82rem;">
                      <span class="badge bg-gene-set me-1" style="font-size:0.65rem;">GS</span><?= htmlspecialchars($gs) ?>
                    </label>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
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

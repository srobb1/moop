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
        <small class="text-muted fst-italic d-none d-md-inline">leave unchecked to search all</small>
        <div class="d-flex gap-1 ms-2">
          <button type="button" id="scope-select-all" class="btn btn-sm btn-outline-secondary">All</button>
          <button type="button" id="scope-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
        </div>
      </div>

      <div class="row g-0" style="min-height:200px;">

        <!-- Left: organism list -->
        <div class="col-lg-8 border-end d-flex flex-column">
          <div class="px-2 pt-2 pb-1 border-bottom">
            <input type="text" class="form-control form-control-sm" id="scope-filter"
                   placeholder="Filter organisms…" autocomplete="off">
          </div>
          <div style="overflow-y:auto; max-height:340px;" id="scope-org-list">
            <?php if (empty($scope_tree)): ?>
              <p class="text-muted small p-3">No accessible organisms found.</p>
            <?php else: ?>

            <?php
            $gp = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'];
            $groupColor = fn($n) => $gp[abs(array_sum(array_map('ord', str_split($n))) * 31) % count($gp)];
            $oi = 0;
            foreach ($scope_tree as $organism => $assemblies): $oi++;
              $info   = $organism_info[$organism] ?? [];
              $genus  = $info['genus']       ?? '';
              $sp     = $info['species']     ?? '';
              $cn     = $info['common_name'] ?? '';
              $label  = trim("$genus $sp") ?: str_replace('_', ' ', $organism);
              $groups = $organism_groups[$organism] ?? [];
              $search = strtolower("$label $cn " . implode(' ', $groups));
              foreach ($assemblies as $asm => $gene_sets) {
                  $an = $assembly_names[$organism][$asm] ?? '';
                  $search .= ' ' . strtolower($asm . ' ' . $an . ' ' . implode(' ', $gene_sets));
              }
            ?>
            <div class="scope-org-item" data-org="<?= htmlspecialchars($organism) ?>">

              <!-- Organism header row (click = toggle all gene sets) -->
              <div class="org-select-row scope-org-row-item"
                   data-org="<?= htmlspecialchars($organism) ?>"
                   data-search="<?= htmlspecialchars($search) ?>">
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

              <!-- Gene-set rows (one per assembly × gene set) -->
              <?php $ai = 0; foreach ($assemblies as $asm => $gene_sets): $ai++;
                $an = $assembly_names[$organism][$asm] ?? '';
                $gi = 0; foreach ($gene_sets as $gs): $gi++;
                  $gsid = 'sgs_' . $oi . '_' . $ai . '_' . $gi;
              ?>
              <div class="scope-gs-row d-flex align-items-center gap-2 ps-3 pe-2 py-1"
                   style="border-top:1px solid #f3f3f3; font-size:0.79rem;">
                <input type="checkbox" class="form-check-input flex-shrink-0 scope-gs-cb mb-0"
                       id="<?= $gsid ?>"
                       data-org="<?= htmlspecialchars($organism) ?>"
                       data-asm="<?= htmlspecialchars($asm) ?>"
                       data-gs="<?= htmlspecialchars($gs) ?>"
                       data-label="<?= htmlspecialchars($label) ?>"
                       data-cn="<?= htmlspecialchars($cn) ?>">
                <label for="<?= $gsid ?>" class="form-check-label mb-0 text-muted" style="cursor:pointer;">
                  <?php if ($an): ?>
                    <?= htmlspecialchars($an) ?>
                    <span class="ms-1" style="font-size:0.72rem;">(<?= htmlspecialchars($asm) ?>)</span>
                  <?php else: ?>
                    <?= htmlspecialchars($asm) ?>
                  <?php endif; ?>
                  <span class="mx-1">›</span><?= htmlspecialchars($gs) ?>
                </label>
              </div>
              <?php endforeach; endforeach; ?>

            </div>
            <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right: selected organisms panel -->
        <div class="col-lg-4 d-flex flex-column">
          <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
               style="background:#f8f9fa;">
            <span class="small fw-semibold text-muted">Selected</span>
            <span class="badge bg-secondary" id="scope-selected-count">0</span>
          </div>
          <div style="overflow-y:auto; max-height:340px; font-size:0.82rem;" id="scope-selected-panel">
            <div class="text-muted small p-2 fst-italic">None — will search all organisms</div>
          </div>
        </div>

      </div><!-- /row -->
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

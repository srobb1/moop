<?php
/**
 * GENERIC SEARCH — Display Page
 * Variables: $scope_tree, $organism_info, $all_organisms, $site, $siteTitle
 */
?>
<div class="container mt-5">

  <!-- Search Form -->
  <div class="row mb-3">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold">
            <i class="fa fa-search"></i> Search Gene IDs and Annotations
            <i class="fa fa-info-circle search-instructions-trigger"
               style="cursor:pointer; margin-left:0.5rem; font-size:0.8em;"
               data-help-type="basic"></i>
          </h4>
          <form id="searchForm">
            <div class="d-flex gap-2 align-items-center">
              <input type="text" class="form-control" id="searchKeywords"
                     placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
              <button type="submit" class="btn btn-icon btn-search" id="searchBtn"
                      title="Search" data-bs-toggle="tooltip" data-bs-placement="bottom">
                <i class="fa fa-search"></i>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Filters Row -->
  <div class="row mb-3 g-3" id="searchFilters">

    <!-- Scope Tree -->
    <div class="col-lg-5">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center py-2">
          <i class="fa fa-sitemap me-2 text-muted"></i>
          <strong class="me-auto">Scope</strong>
          <div class="d-flex gap-1">
            <button id="scope-select-all" class="btn btn-sm btn-outline-secondary">All</button>
            <button id="scope-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
          </div>
        </div>
        <div class="card-body p-2" style="overflow-y:auto; max-height:360px;">
          <?php if (empty($scope_tree)): ?>
            <p class="text-muted small p-2">No accessible organisms found.</p>
          <?php else: ?>
          <div id="scope-tree">
            <?php $oi = 0; foreach ($scope_tree as $organism => $assemblies): $oi++; ?>
            <?php
              $info   = $organism_info[$organism] ?? [];
              $genus  = $info['genus']       ?? '';
              $sp     = $info['species']     ?? '';
              $cn     = $info['common_name'] ?? '';
              $label  = trim("$genus $sp") ?: str_replace('_', ' ', $organism);
              $oid    = 'so_' . $oi;
            ?>
            <div class="scope-org mb-1" data-org="<?= htmlspecialchars($organism) ?>">
              <!-- Organism row -->
              <div class="d-flex align-items-center gap-1 px-1 py-1 rounded scope-org-row"
                   style="background:#f8f9fa;">
                <input type="checkbox" class="form-check-input flex-shrink-0 scope-org-cb mb-0"
                       id="<?= $oid ?>" data-org="<?= htmlspecialchars($organism) ?>" checked>
                <label for="<?= $oid ?>" class="form-check-label fw-semibold mb-0 me-auto"
                       style="cursor:pointer; font-size:0.9rem;">
                  <em><?= htmlspecialchars($label) ?></em>
                  <?php if ($cn): ?>
                    <span class="text-muted fw-normal">(<?= htmlspecialchars($cn) ?>)</span>
                  <?php endif; ?>
                </label>
                <i class="fa fa-chevron-down scope-toggle text-muted"
                   style="cursor:pointer; font-size:0.75rem;"
                   data-target="<?= $oid ?>-body"></i>
              </div>

              <!-- Assemblies (collapsible) -->
              <div id="<?= $oid ?>-body" class="ps-3 pt-1">
                <?php $ai = 0; foreach ($assemblies as $assembly => $gene_sets): $ai++; ?>
                <?php $aid = $oid . '_a' . $ai; ?>
                <div class="scope-asm mb-1" data-org="<?= htmlspecialchars($organism) ?>"
                     data-asm="<?= htmlspecialchars($assembly) ?>">
                  <!-- Assembly row -->
                  <div class="d-flex align-items-center gap-1 px-1 py-1 rounded"
                       style="background:#fff3cd20;">
                    <input type="checkbox"
                           class="form-check-input flex-shrink-0 scope-asm-cb mb-0"
                           id="<?= $aid ?>"
                           data-org="<?= htmlspecialchars($organism) ?>"
                           data-asm="<?= htmlspecialchars($assembly) ?>" checked>
                    <label for="<?= $aid ?>"
                           class="form-check-label fw-semibold mb-0 me-auto"
                           style="cursor:pointer; font-size:0.85rem; color:#b45309;">
                      <?= htmlspecialchars($assembly) ?>
                    </label>
                  </div>

                  <!-- Gene Sets -->
                  <div class="ps-3">
                    <?php $gi = 0; foreach ($gene_sets as $gs): $gi++; ?>
                    <?php $gsid = $aid . '_g' . $gi; ?>
                    <div class="d-flex align-items-center gap-1 px-1 py-1">
                      <input type="checkbox"
                             class="form-check-input flex-shrink-0 scope-gs-cb mb-0"
                             id="<?= $gsid ?>"
                             data-org="<?= htmlspecialchars($organism) ?>"
                             data-asm="<?= htmlspecialchars($assembly) ?>"
                             data-gs="<?= htmlspecialchars($gs) ?>" checked>
                      <label for="<?= $gsid ?>"
                             class="form-check-label mb-0"
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
    </div>

    <!-- Annotation Sources Panel -->
    <div class="col-lg-7">
      <div class="card shadow-sm h-100">
        <div class="card-header d-flex align-items-center py-2">
          <i class="fa fa-sliders-h me-2 text-muted"></i>
          <strong class="me-auto">Annotation Sources</strong>
          <div class="d-flex gap-1">
            <button id="sources-select-all" class="btn btn-sm btn-outline-secondary">All</button>
            <button id="sources-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
          </div>
        </div>
        <div class="card-body p-2" id="sourcesPanel" style="overflow-y:auto; max-height:360px;">
          <div class="text-center p-3 text-muted">
            <i class="fa fa-spinner fa-spin me-1"></i> Loading sources…
          </div>
        </div>
        <div class="card-footer py-1 px-2 text-muted" style="font-size:0.8rem;">
          <span id="sources-summary"></span>
        </div>
      </div>
    </div>

  </div><!-- end filters row -->

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

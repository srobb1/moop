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
            $rowIdx = 0;
            foreach ($scope_tree as $organism => $assemblies):
              $info   = $organism_info[$organism] ?? [];
              $label  = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);
              $cn     = $info['common_name'] ?? '';
              $groups = $organism_groups[$organism] ?? [];
              foreach ($assemblies as $asm => $gene_sets):
                $an = $assembly_names[$organism][$asm] ?? '';
                $asmDisplay = $an ? $an : $asm;
                $asmAccession = $an ? $asm : '';
                foreach ($gene_sets as $gs):
                  $rowIdx++;
                  $gsid   = 'sgs_' . $rowIdx;
                  $search = strtolower("$label $cn $asm $an $gs " . implode(' ', $groups));
            ?>
            <?php
              $tooltip = $label . ($cn ? ' · ' . $cn : '') . ' · ' . $asmDisplay . ($asmAccession ? ' (' . $asmAccession . ')' : '') . ' › ' . $gs;
            ?>
            <div class="org-select-row scope-gs-full-row"
                 data-search="<?= htmlspecialchars($search) ?>"
                 title="<?= htmlspecialchars($tooltip) ?>">
              <input type="checkbox" class="scope-gs-cb visually-hidden"
                     id="<?= $gsid ?>"
                     data-org="<?= htmlspecialchars($organism) ?>"
                     data-asm="<?= htmlspecialchars($asm) ?>"
                     data-gs="<?= htmlspecialchars($gs) ?>"
                     data-label="<?= htmlspecialchars($label) ?>"
                     data-cn="<?= htmlspecialchars($cn) ?>"
                     data-asm-display="<?= htmlspecialchars($asmDisplay) ?>">
              <span class="org-groups flex-shrink-0">
                <?php foreach ($groups as $g): ?>
                <span class="org-group-chip" style="background:<?= $groupColor($g) ?>"><?= htmlspecialchars($g) ?></span>
                <?php endforeach; ?>
              </span>
              <span class="flex-grow-1 text-truncate" style="min-width:0; white-space:nowrap;">
                <em><?= htmlspecialchars($label) ?></em><?php if ($cn): ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($cn) ?></span><?php endif;
                ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asmDisplay) ?><?php if ($asmAccession): ?> <span style="font-size:0.9em;">(<?= htmlspecialchars($asmAccession) ?>)</span><?php endif; ?> › <?= htmlspecialchars($gs) ?></span>
              </span>
              <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
            </div>
            <?php endforeach; endforeach; endforeach; ?>
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

    <!-- ③ Annotation Types -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2">
        <span class="step-badge me-2">3</span>
        <strong class="me-auto">Select annotation types to search</strong>
        <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted"
                data-bs-toggle="modal" data-bs-target="#ann-types-modal" title="About annotation types">
          <i class="fa fa-info-circle"></i>
        </button>
        <div class="d-flex gap-1 ms-2">
          <button type="button" id="sources-select-all" class="btn btn-sm btn-outline-secondary">All</button>
          <button type="button" id="sources-deselect-all" class="btn btn-sm btn-outline-secondary">None</button>
        </div>
      </div>

      <div class="row g-0" style="min-height:120px;">

        <!-- Left: annotation types list -->
        <div class="col-lg-8 border-end d-flex flex-column">
          <div class="px-2 pt-2 pb-1 border-bottom" id="sources-filter-wrap" style="display:none;">
            <input type="text" class="form-control form-control-sm" id="sources-filter"
                   placeholder="Filter annotation types…" autocomplete="off">
          </div>
          <div id="sourcesPanel" style="overflow-y:auto; max-height:280px;">
            <div class="text-center p-3 text-muted">
              <i class="fa fa-spinner fa-spin me-1"></i> Loading…
            </div>
          </div>
        </div>

        <!-- Right: selected annotation types panel -->
        <div class="col-lg-4 d-flex flex-column">
          <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
               style="background:#f8f9fa;">
            <span class="small fw-semibold text-muted">Selected</span>
            <span class="badge bg-secondary" id="ann-types-selected-count">0</span>
          </div>
          <div style="overflow-y:auto; max-height:280px; font-size:0.82rem;" id="ann-types-selected-panel">
            <div class="text-muted small p-2 fst-italic">No types selected</div>
          </div>
        </div>

      </div><!-- /row -->
    </div>

    <!-- ④ Search -->
    <div class="mb-4">
      <button type="submit" class="btn btn-lg w-100" id="searchBtn"
              style="background:#7c3aed; border-color:#7c3aed; color:#fff; font-size:1.1rem;">
        <i class="fa fa-search me-2"></i>Search
      </button>
    </div>

  </form>

  <!-- Annotation types info modal -->
  <div class="modal fade" id="ann-types-modal" tabindex="-1" aria-labelledby="ann-types-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header py-2">
          <h5 class="modal-title fw-bold" id="ann-types-modal-label">Annotation Types</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted mb-4">
            You must select at least one annotation type in section&nbsp;③ before searching.
            Select only the types relevant to your query — for example, choose <em>Gene Ontology</em>
            to find genes by GO term, or <em>Domains</em> to search protein domain annotations.
            Selecting fewer types makes searches faster and results more focused.
          </p>
          <div class="row g-3">
            <?php foreach ($ann_type_info as $type => $info):
              $color = htmlspecialchars($info['color']);
              $desc  = $info['description'];
              if (!$desc) continue;
            ?>
            <div class="col-md-6">
              <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                  <h6 class="card-title mb-2">
                    <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($type) ?></span>
                  </h6>
                  <p class="card-text small text-muted mb-0"><?= $desc ?></p>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

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

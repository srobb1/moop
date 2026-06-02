<?php
/**
 * Index page content
 * Variables available: $siteTitle, $cards_to_display, $taxonomy_tree_data, $user_access_json, $ip
 */
?>

<div class="container py-3">
  <!-- Page Header -->
  <div class="text-center mb-3">
    <p class="index-site-title"><?= htmlspecialchars($siteTitle) ?></p>
    <hr class="mx-auto page-header-divider">
  </div>

  <!-- Quick search -->
  <div class="qs-wrap mb-3">
    <div class="card border-0 rounded-3 qs-card">
      <div class="card-body p-3">
        <div class="qs-input-wrap">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0 pe-1 text-muted">
              <i class="fa fa-search"></i>
            </span>
            <input type="text" id="qs-input" class="form-control border-start-0 border-end-0 ps-1"
                   placeholder="Search organisms, groups, assemblies, gene sets…"
                   autocomplete="off" spellcheck="false">
            <button id="qs-go" class="btn btn-primary px-3" type="button">Go</button>
          </div>
          <div id="qs-dropdown" class="qs-dropdown"></div>
        </div>
        <div class="qs-examples mt-2">
          <span class="text-muted small me-1">e.g.</span>
          <button class="qs-example-chip" type="button">Anoura caudifer</button>
          <button class="qs-example-chip" type="button">Pallid Bat</button>
          <button class="qs-example-chip" type="button">Bats</button>
          <button class="qs-example-chip" type="button">GCA_004027475.1</button>
          <button class="qs-example-chip" type="button">SIMR_2025-01-24</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Browse by Group collapsible header -->
  <div class="browse-select-header mb-3" id="browse-group-header"
       data-bs-toggle="collapse" data-bs-target="#browse-group-body"
       role="button" aria-expanded="false" aria-controls="browse-group-body">
    <span class="d-flex align-items-center gap-2">
      <i class="fas fa-chevron-down browse-select-chevron"></i>
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Browse by Group</span>
    </span>
  </div>
  <div class="collapse mb-3" id="browse-group-body">
    <div class="groups-strip">
      <div class="index-group-chip-list">
        <?php foreach ($cards_to_display as $card): ?>
          <a href="<?= htmlspecialchars($card['link']) ?>" target="_blank"
             class="index-group-chip text-decoration-none"
             data-group="<?= htmlspecialchars($card['title']) ?>">
            <?= htmlspecialchars($card['title']) ?>
            <?php if (!empty($card['organism_count'])): ?>
              <span class="index-group-chip-count">(<?= $card['organism_count'] ?>)</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Browse & Select collapsible header -->
  <div class="browse-select-header mb-0" id="browse-select-header"
       data-bs-toggle="collapse" data-bs-target="#browse-select-body"
       role="button" aria-expanded="false" aria-controls="browse-select-body">
    <span class="d-flex align-items-center gap-2">
      <i class="fas fa-chevron-down browse-select-chevron"></i>
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Browse &amp; Select</span>
      <button class="btn btn-link btn-sm p-0" data-bs-toggle="modal" data-bs-target="#how-to-modal"
              title="How to use" style="font-size:0.9rem; line-height:1; color:rgba(255,255,255,0.7);"
              onclick="event.stopPropagation()">
        <i class="fas fa-info-circle"></i>
      </button>
    </span>
  </div>

  <!-- How-to modal -->
  <div class="modal fade" id="how-to-modal" tabindex="-1" aria-labelledby="how-to-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold" id="how-to-modal-label">
            <i class="fas fa-info-circle text-info me-2"></i>How to use <?= htmlspecialchars($siteTitle) ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">

          <!-- Two-step workflow -->
          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="info-step-card">
                <div class="info-step-num">1</div>
                <div>
                  <div class="fw-semibold mb-1">Select your organisms</div>
                  <p class="text-muted small mb-0">
                    Pick one or more organisms using any of the four selection modes below.
                    You can mix and match — selections carry over between tabs.
                  </p>
                </div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="info-step-card">
                <div class="info-step-num">2</div>
                <div>
                  <div class="fw-semibold mb-1">Choose a tool</div>
                  <p class="text-muted small mb-0">
                    Click a tool in the <strong>Tool Box</strong>. It opens in a new tab,
                    pre-filtered to exactly the organisms you selected.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Selection modes -->
          <h6 class="fw-semibold text-dark mb-2">Selection modes</h6>
          <div class="row g-2 mb-4">
            <div class="col-sm-6">
              <div class="info-mode-card">
                <div class="fw-semibold small mb-1"><i class="fa fa-list text-primary me-1"></i> Organism Select</div>
                <p class="text-muted small mb-0">Searchable flat list. Filter by scientific name, common name, or group. Best for finding specific species quickly.</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="info-mode-card">
                <div class="fw-semibold small mb-1"><i class="fa fa-sitemap text-primary me-1"></i> Taxon Select</div>
                <p class="text-muted small mb-0">Each row shows the full taxonomic lineage. Filter by any rank — order, family, genus — to find related species.</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="info-mode-card">
                <div class="fw-semibold small mb-1"><i class="fa fa-th text-primary me-1"></i> Group Select</div>
                <p class="text-muted small mb-0">Curated organism groups (e.g. Bats, Reptiles). Click a group card to explore all organisms in that group together.</p>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="info-mode-card">
                <div class="fw-semibold small mb-1"><i class="fa fa-project-diagram text-primary me-1"></i> Tree Select</div>
                <p class="text-muted small mb-0">Interactive phylogenetic tree. Click any branch to select all organisms below it, or individual leaves for single species.</p>
              </div>
            </div>
          </div>

          <!-- Available tools -->
          <h6 class="fw-semibold text-dark mb-2">Available tools</h6>
          <div class="row g-2 mb-3">
            <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-emerald me-2">Retrieve Sequences</span><span class="text-muted small">Download gene, mRNA, CDS, or protein FASTA</span></div></div>
            <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-orange me-2">BLAST Search</span><span class="text-muted small">Search a query sequence against selected genomes</span></div></div>
            <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-violet me-2">Search Organisms</span><span class="text-muted small">Cross-organism annotation comparison table</span></div></div>
            <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-sky me-2">Downloads</span><span class="text-muted small">Browse and download genome assembly files</span></div></div>
          </div>

          <p class="text-muted small mb-0">
            <i class="fas fa-info-circle text-info me-1"></i>
            <strong>Tip:</strong> Selections are remembered as you switch between tabs.
            Use the <strong>Selected Organisms</strong> panel on the right to review your list and remove any entries before running a tool.
          </p>

        </div>
      </div>
    </div>
  </div>

  <!-- Browse & Select: selected organisms full-width top row, then step 1 + step 2 below -->
  <div class="collapse" id="browse-select-body">
  <div class="browse-select-panel">
  <div class="bs-grid" id="organism-tabs-anchor">

    <!-- Row 1: Selected Organisms — full width -->
    <div class="bs-grid-selected">
      <div class="card shadow-sm" id="selected-organisms-card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            Selected Organisms
            <span class="badge bg-light text-dark ms-1" id="selected-count">0</span>
          </h5>
          <button id="clear-all-organisms" class="btn btn-sm btn-outline-light py-0 px-2" style="display:none" title="Clear all">
            <i class="fa fa-times me-1"></i>Clear all
          </button>
        </div>
        <div class="card-body p-2">
          <div id="selected-organisms-list">
            <div class="text-muted fst-italic small px-1">No organisms selected</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2, Col 1: Step 1 — organism selection tabs -->
    <div class="bs-grid-step1">
      <div class="card shadow-sm">
        <div class="card-header bg-tools text-white py-2">
          <span class="step-badge me-2">1</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select one or more organisms</span>
        </div>
        <div class="card-body p-2">
          <ul class="nav nav-tabs" id="organism-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-organism-select" type="button" role="tab">
                <i class="fa fa-list me-1"></i> Organisms Select
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-taxon-select" type="button" role="tab">
                <i class="fa fa-sitemap me-1"></i> Taxon Select
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tree-select" type="button" role="tab">
                <i class="fa fa-project-diagram me-1"></i> Tree Select
              </button>
            </li>
          </ul>

          <div class="tab-content border border-top-0 rounded-bottom p-3" id="organism-tabs-content">

            <!-- Tab 1: Organism Select -->
            <div class="tab-pane fade show active" id="tab-organism-select" role="tabpanel">
              <input type="text" class="form-control form-control-sm mb-2" id="organism-select-filter"
                     placeholder="Filter by name, common name, or group…">
              <div id="organism-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 2: Taxon Select -->
            <div class="tab-pane fade" id="tab-taxon-select" role="tabpanel">
              <input type="text" class="form-control form-control-sm mb-2" id="taxon-select-filter"
                     placeholder="Filter by taxonomy, name, or common name…">
              <div id="taxon-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 3: Tree Select -->
            <div class="tab-pane fade" id="tab-tree-select" role="tabpanel">
              <div class="mb-2">
                <small class="text-muted">
                  <i class="fa fa-info-circle text-info"></i> Click any node to select/deselect. Selecting a branch selects all organisms below it.
                </small>
              </div>
              <input type="text" class="form-control form-control-sm mb-2" id="taxonomy-filter" placeholder="Filter by name…">
              <div class="taxonomy-tree-scroll">
                <div id="taxonomy-tree-container"></div>
              </div>
            </div>

          </div><!-- /tab-content -->
        </div><!-- /card-body -->
      </div><!-- /card -->
    </div><!-- /bs-grid-step1 -->

    <!-- Row 2, Col 2: Step 2 — tool selection -->
    <div class="bs-grid-step2">
      <div id="tools-card-wrapper">
      <?php
      $context = createToolContext('index', ['use_onclick_handler' => true]);
      include_once TOOL_SECTION_PATH;
      ?>
      </div>
    </div><!-- /bs-grid-step2 -->

  </div><!-- /bs-grid -->
  </div><!-- /browse-select-panel -->
  </div><!-- /collapse browse-select-body -->
</div><!-- /container -->

<script src="js/modules/taxonomy-tree.js?v=<?= filemtime(__DIR__ . '/../../js/modules/taxonomy-tree.js') ?>"></script>
<script src="js/index.js?v=<?= filemtime(__DIR__ . '/../../js/index.js') ?>"></script>
<script>
const userAccess    = <?= $user_access_json ?>;
const treeData      = <?= json_encode($taxonomy_tree_data) ?>;
const organismData  = <?= $organism_list_json ?>;

</script>

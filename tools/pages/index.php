<?php
/**
 * Index page content
 * Variables available: $siteTitle, $organism_count, $assembly_count, $cards_to_display, $taxonomy_tree_data, $user_access_json, $ip
 */
?>

<div class="container py-3">
  <!-- Page Header -->
  <div class="text-center mb-3">
    <p class="index-site-title moop-tool-title"><?= htmlspecialchars($siteTitle) ?></p>
    <hr class="mx-auto page-header-divider">
    <p class="mb-2" style="font-size:0.95rem;font-weight:300;color:rgba(8,145,178,0.7);letter-spacing:0.03em;">
      Browse genes, genomes, and annotations<?php if (!empty($organism_count)): ?> across <strong style="font-weight:500;"><?= $organism_count ?></strong> organism<?= $organism_count !== 1 ? 's' : '' ?><?php if (!empty($assembly_count)): ?> and <strong style="font-weight:500;"><?= $assembly_count ?></strong> assembl<?= $assembly_count !== 1 ? 'ies' : 'y' ?><?php endif; ?><?php endif; ?>
    </p>
  </div>

  <!-- Quick search — tabbed -->
  <div class="qs-wrap mb-3">
    <ul class="nav nav-tabs justify-content-end" id="search-tabs" style="border-bottom:0;">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-quick" type="button">
          <i class="fa fa-search me-1"></i>Organisms
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-feature" type="button">
          <i class="fa fa-fingerprint me-1"></i>Sequence ID
        </button>
      </li>
    </ul>
    <div class="tab-content">

      <!-- Tab 1: organism / group / assembly / gene set search -->
      <div class="tab-pane fade show active" id="tab-quick">
        <div class="card border-0 border-top rounded-0 rounded-bottom qs-card">
          <div class="card-body p-3" style="min-height:88px;">
            <div class="qs-input-wrap">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0 pe-1 text-muted">
                  <i class="fa fa-search"></i>
                </span>
                <input type="text" id="qs-input" class="form-control border-start-0 border-end-0 ps-1 moop-input"
                       placeholder="Search organisms, groups, assemblies, gene sets…"
                       autocomplete="off" spellcheck="false">
                <button id="qs-go" class="btn btn-primary px-3" type="button">Go</button>
              </div>
              <div id="qs-dropdown" class="qs-dropdown"></div>
            </div>
            <div class="qs-examples mt-2">
              <span class="text-muted me-1" style="font-size:0.62rem;">e.g.</span>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">Anoura caudifer</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">Pallid Bat</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">Bats</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">GCA_004027475.1</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">SIMR_2025-01-24</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: exact sequence ID search across all accessible databases -->
      <div class="tab-pane fade" id="tab-feature">
        <div class="card border-0 border-top rounded-0 rounded-bottom qs-card">
          <div class="card-body p-3" style="min-height:88px;">
            <div class="qs-input-wrap">
              <div class="input-group">
                <span class="input-group-text bg-white border-end-0 pe-1 text-muted">
                  <i class="fa fa-fingerprint"></i>
                </span>
                <input type="text" id="fs-input" class="form-control border-start-0 border-end-0 ps-1 moop-input"
                       placeholder="Enter exact sequence ID…"
                       autocomplete="off" spellcheck="false">
                <button id="fs-go" class="btn btn-primary px-3" type="button">Go</button>
              </div>
            </div>
            <div class="qs-examples mt-2">
              <span class="text-muted me-1" style="font-size:0.62rem;">e.g.</span>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">LOC100636551</button>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">XM_020002978.1</button>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">ACA1_PVKU01000001.1_000001</button>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">ACA1_PVKU01000001.1_000001.1</button>
            </div>
            <div id="fs-status" class="small mt-2 text-muted" style="display:none;"></div>
            <div id="fs-results"></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <?php if (!empty($featured_groups)): ?>
  <!-- Featured groups -->
  <div class="mb-3 text-center pt-2">
    <div class="text-uppercase fw-semibold mb-2" style="letter-spacing:0.1em;font-size:0.8rem;color:#0891b2;">
      Focus Your Search by Group
    </div>
    <hr class="mx-auto page-header-divider mb-3">
    <div class="d-flex justify-content-center flex-wrap gap-2">
      <?php foreach ($featured_groups as $fg): ?>
        <a href="<?= htmlspecialchars($fg['url']) ?>" class="index-group-chip text-decoration-none"
           data-group="<?= htmlspecialchars($fg['name']) ?>">
          <?= htmlspecialchars($fg['name']) ?>
        </a>
      <?php endforeach; ?>
      <button class="index-group-chip"
              style="background:#e2e8f0;color:#475569;border:none;cursor:pointer;"
              onclick="document.getElementById('browse-group-header').click()">
        More&hellip;
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Browse by Group collapsible header -->
  <div class="browse-select-header mb-3" id="browse-group-header"
       data-bs-toggle="collapse" data-bs-target="#browse-group-body"
       role="button" aria-expanded="false" aria-controls="browse-group-body">
    <span class="d-flex align-items-center gap-2">
      <i class="fas fa-chevron-down browse-select-chevron"></i>
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Search in a Group of Organisms</span>
    </span>
  </div>
  <div class="collapse mb-3" id="browse-group-body">
    <div class="groups-strip">
      <p class="text-muted small mb-2">Click a group to focus your searches on a curated set of organisms.</p>
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
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Search in a Custom Selection of Organisms</span>
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
  <p class="text-muted small mb-2 px-1">Build a custom collection of organisms to focus your searches — complete
    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#6366f1;color:#fff;font-size:0.62rem;font-weight:700;vertical-align:middle;">1</span>
    then
    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#6366f1;color:#fff;font-size:0.62rem;font-weight:700;vertical-align:middle;">2</span>
    below.
  </p>
  <div class="bs-grid" id="organism-tabs-anchor">

    <!-- Row 1: Selected Organisms — full width -->
    <div class="bs-grid-selected">
      <div class="card shadow-sm selection-empty" id="selected-organisms-card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <span class="fw-semibold" style="font-size:0.9rem;">
            Selected Organisms
            <span class="badge bg-light text-dark ms-1" id="selected-count">0</span>
          </span>
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
              <p class="text-muted small mb-2">Filter by scientific name, common name, or group to find specific species. Select one or more to use with a tool.</p>
              <input type="text" class="form-control form-control-sm mb-2 moop-input" id="organism-select-filter"
                     placeholder="Filter by name, common name, or group…">
              <div id="organism-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 2: Taxon Select -->
            <div class="tab-pane fade" id="tab-taxon-select" role="tabpanel">
              <p class="text-muted small mb-2">Each row shows the full taxonomic lineage — filter by any rank to find related species.</p>
              <input type="text" class="form-control form-control-sm mb-2 moop-input" id="taxon-select-filter"
                     placeholder="Filter by taxonomy, name, or common name…">
              <div id="taxon-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 3: Tree Select -->
            <div class="tab-pane fade" id="tab-tree-select" role="tabpanel">
              <p class="text-muted small mb-2">Click any branch to select all organisms below it, or individual leaves for a single species. Use the ❯ chevron to expand without changing your selection.</p>
              <div class="d-flex align-items-center justify-content-end mb-2">
                <div class="d-flex gap-1">
                  <button id="tree-expand-all" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Expand All</button>
                  <button id="tree-collapse-all" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Collapse All</button>
                </div>
              </div>
              <input type="text" class="form-control form-control-sm mb-2 moop-input" id="taxonomy-filter" placeholder="Filter by taxon or organism…">
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

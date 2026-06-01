<?php
/**
 * Index page content
 * Variables available: $siteTitle, $cards_to_display, $taxonomy_tree_data, $user_access_json, $ip
 */
?>

<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto page-header-divider">
  </div>

  <!-- Site Info Card -->
  <div class="row g-4 justify-content-center mb-5">
    <div class="col-md-12 col-lg-10">
      <div class="card shadow-sm border-0 rounded-3 bg-info bg-opacity-10">
        <div class="card-header bg-transparent border-0 d-flex justify-content-between align-items-center"
             style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#site-info-body" aria-expanded="false" aria-controls="site-info-body">
          <h5 class="fw-bold text-dark mb-0">
            <i class="fa fa-circle-info text-info me-2"></i>How to use <?= htmlspecialchars($siteTitle) ?>
          </h5>
          <i class="fa fa-chevron-down text-muted" id="site-info-chevron"></i>
        </div>
        <div class="collapse" id="site-info-body">
          <div class="card-body pt-2">

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
              <div class="col-sm-6 col-lg-3">
                <div class="info-mode-card">
                  <div class="fw-semibold small mb-1"><i class="fa fa-list text-primary me-1"></i> Organism Select</div>
                  <p class="text-muted small mb-0">Searchable flat list. Filter by scientific name, common name, or group. Best for finding specific species quickly.</p>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="info-mode-card">
                  <div class="fw-semibold small mb-1"><i class="fa fa-sitemap text-primary me-1"></i> Taxon Select</div>
                  <p class="text-muted small mb-0">Each row shows the full taxonomic lineage. Filter by any rank — order, family, genus — to find related species.</p>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
                <div class="info-mode-card">
                  <div class="fw-semibold small mb-1"><i class="fa fa-th text-primary me-1"></i> Group Select</div>
                  <p class="text-muted small mb-0">Curated organism groups (e.g. Bats, Reptiles). Click a group card to explore all organisms in that group together.</p>
                </div>
              </div>
              <div class="col-sm-6 col-lg-3">
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
              <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-amber me-2">Annotation Search</span><span class="text-muted small">Filter genes by functional annotation across organisms</span></div></div>
              <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-rose me-2">MOOPmart</span><span class="text-muted small">Bulk export with flexible filters — TSV or FASTA</span></div></div>
              <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-violet me-2">Search Organisms</span><span class="text-muted small">Cross-organism annotation comparison table</span></div></div>
              <div class="col-sm-6 col-lg-4"><div class="info-tool-row"><span class="badge btn-tool-sky me-2">Downloads</span><span class="text-muted small">Browse and download genome assembly files</span></div></div>
            </div>

            <p class="text-muted small mb-0">
              <i class="fa fa-circle-info text-info me-1"></i>
              <strong>Tip:</strong> Selections are remembered as you switch between tabs.
              Use the <strong>Selected Organisms</strong> panel on the right to review your list and remove any entries before running a tool.
            </p>

          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Available Organisms Header -->
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-3">Select organisms to explore</h3>
  </div>

  <!-- Two-column layout: tabs (left) + selection panel (right, always visible) -->
  <div class="row g-4">

    <!-- Left: 4 tabs -->
    <div class="col-lg-8">
      <div class="step-label mb-2">
        <span class="step-badge">1</span>
        <span class="step-text">Select one or more organisms</span>
      </div>
      <ul class="nav nav-tabs" id="organism-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-organism-select" type="button" role="tab">
            <i class="fa fa-list me-1"></i> Organism Select
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-taxon-select" type="button" role="tab">
            <i class="fa fa-sitemap me-1"></i> Taxon Select
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-group-select" type="button" role="tab">
            <i class="fa fa-th me-1"></i> Group Select
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

        <!-- Tab 3: Group Select -->
        <div class="tab-pane fade" id="tab-group-select" role="tabpanel">
          <div class="row g-3">
            <?php foreach ($cards_to_display as $card): ?>
              <div class="col-sm-6">
                <a href="<?= htmlspecialchars($card['link']) ?>" target="_blank" class="text-decoration-none card-link">
                  <div class="card h-100 shadow-sm border-0 rounded-3 organism-card">
                    <div class="card-body text-center d-flex flex-column">
                      <h3 class="card-title mb-3 fw-bold text-dark">
                        <?= htmlspecialchars($card['title']) ?>
                        <?php if (!empty($card['organism_count'])): ?>
                          <span class="text-muted fw-normal fs-6">(<?= $card['organism_count'] ?>)</span>
                        <?php endif; ?>
                      </h3>
                      <p class="card-text text-muted mb-3"><?= htmlspecialchars($card['text']) ?></p>
                    </div>
                  </div>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Tab 4: Tree Select -->
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
    </div><!-- /col-lg-8 -->

    <!-- Right: always-visible selection panel -->
    <div class="col-lg-4">
      <div class="step-label mb-2">
        <span class="step-badge">2</span>
        <span class="step-text">Choose a tool — it runs on your selection</span>
      </div>
      <!-- Tools Card -->
      <div id="tools-card-wrapper">
      <?php
      $context = createToolContext('index', ['use_onclick_handler' => true]);
      include_once TOOL_SECTION_PATH;
      ?>
      <p id="tool-select-hint" class="text-muted small fst-italic text-center mt-2 mb-0" style="display:none">
        ← Select organisms first
      </p>
      </div>

      <div class="card shadow-sm sticky-card mb-3">
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
    </div><!-- /col-lg-4 -->

  </div><!-- /row -->
</div><!-- /container -->

<script src="js/modules/taxonomy-tree.js?v=<?= filemtime(__DIR__ . '/../../js/modules/taxonomy-tree.js') ?>"></script>
<script src="js/index.js?v=<?= filemtime(__DIR__ . '/../../js/index.js') ?>"></script>
<script>
const userAccess    = <?= $user_access_json ?>;
const treeData      = <?= json_encode($taxonomy_tree_data) ?>;
const organismData  = <?= $organism_list_json ?>;

document.getElementById('site-info-body').addEventListener('show.bs.collapse', () => {
    document.getElementById('site-info-chevron').classList.replace('fa-chevron-down', 'fa-chevron-up');
});
document.getElementById('site-info-body').addEventListener('hide.bs.collapse', () => {
    document.getElementById('site-info-chevron').classList.replace('fa-chevron-up', 'fa-chevron-down');
});
</script>

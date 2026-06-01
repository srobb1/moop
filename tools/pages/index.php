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
          <h5 class="fw-bold text-dark mb-0">Welcome to SIMRbase - MOOP Edition</h5>
          <i class="fa fa-chevron-down text-muted" id="site-info-chevron"></i>
        </div>
        <div class="collapse" id="site-info-body">
          <div class="card-body pt-0">
            <p class="card-text text-muted mb-3">
              <strong>MOOP</strong> — to keep company, associate closely. Explore and discover how diverse organisms are mooped on SIMRbase. MOOP stands for Multiple Organisms One Platform. This new version of SIMRbase has the same data as before but it now easier to search across organsims.
            </p>

            <h6 class="fw-semibold text-dark mb-2">Getting Started:</h6>
            <ul class="text-muted mb-3">
              <li><strong>Select Organisms:</strong> Use <em>Group Select</em> for quick predefined groups or <em>Tree Select</em> for custom organism combinations</li>
              <li><strong>Choose a Tool:</strong> Search sequences and annotations, run BLAST comparisons, examine genome assemblies, or analyze multiple organisms together</li>
              <li><strong>Explore Results:</strong> View interactive tables, visualizations, and download data for external analysis</li>
            </ul>

            <p class="text-muted mb-0">
              <strong>New to SIMRbase?</strong> <a href="help.php?topic=getting-started" class="text-info text-decoration-none">Read the Getting Started guide</a> or explore other <a href="help.php" class="text-info text-decoration-none">tutorials and documentation</a> for detailed help.
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
      <div class="card shadow-sm sticky-card mb-3">
        <div class="card-header bg-success text-white">
          <h5 class="mb-0">
            Selected Organisms
            <span class="badge bg-light text-dark ms-1" id="selected-count">0</span>
          </h5>
        </div>
        <div class="card-body p-2">
          <div id="selected-organisms-list">
            <div class="text-muted fst-italic small px-1">No organisms selected</div>
          </div>
        </div>
      </div>

      <!-- Tools Card -->
      <?php
      $context = createToolContext('index', ['use_onclick_handler' => true]);
      include_once TOOL_SECTION_PATH;
      ?>
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

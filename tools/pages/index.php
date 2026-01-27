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
      <div class="card h-100 shadow-sm border-0 rounded-3 bg-info bg-opacity-10">
        <div class="card-body">
          <h5 class="fw-bold text-dark mb-3">Welcome to MOOP</h5>
          <p class="card-text text-muted mb-3">
            <strong>MOOP</strong> — to keep company, associate closely. Explore and discover how diverse organisms are mooped on SIMRbase.
          </p>
          
          <h6 class="fw-semibold text-dark mb-2">Get Started in 3 Steps:</h6>
          <ol class="text-muted mb-3">
            <li><strong>Select Organisms:</strong> Use <em>Group Select</em> for quick predefined groups or <em>Tree Select</em> for custom organism combinations</li>
            <li><strong>Choose a Tool:</strong> Search sequences and annotations, run BLAST comparisons, examine genome assemblies, or analyze multiple organisms together</li>
            <li><strong>Explore Results:</strong> View interactive tables, visualizations, and download data for external analysis</li>
          </ol>
          
          <p class="text-muted mb-0">
            <strong>New to SIMRbase?</strong> <a href="help.php?page=getting-started" class="text-info text-decoration-none">Read the Getting Started guide</a> or explore other <a href="help.php" class="text-info text-decoration-none">tutorials and documentation</a> for detailed help.
          </p>
        </div>
      </div>
    </div>
  </div>

  <!-- Available Organisms Header -->
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-3">Select organisms by group or customize selection with the taxonomy tree</h3>
  </div>

  <!-- View Toggle -->
  <div class="text-center mb-4">
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-outline-primary active" id="card-view-btn" onclick="switchView('card')">
        <i class="fa fa-th"></i> Group Select 
      </button>
      <button type="button" class="btn btn-outline-primary" id="tree-view-btn" onclick="switchView('tree')">
        <i class="fa fa-project-diagram"></i> Tree Select 
      </button>
    </div>
  </div>

  <!-- Card View -->
  <div id="card-view" class="view-container">
    <div class="row g-4 justify-content-center">
      <?php foreach ($cards_to_display as $card): ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?= htmlspecialchars($card['link']) ?>" target="_blank" class="text-decoration-none card-link">
            <div class="card h-100 shadow-sm border-0 rounded-3 organism-card">
              <div class="card-body text-center d-flex flex-column">
                <h3 class="card-title mb-3 fw-bold text-dark"><?= htmlspecialchars($card['title']) ?></h3>
                <p class="card-text text-muted mb-3"><?= htmlspecialchars($card['text']) ?></p>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Taxonomy Tree View -->
  <div id="tree-view" class="view-container hidden">
    <div class="row">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-project-diagram"></i> Taxonomy Tree</h5>
          </div>
          <div class="card-body">
            <!-- Tree Filter -->
            <div class="mb-3">
              <small class="text-muted">
                <i class="fa fa-info-circle text-info"></i> Click any node to select/deselect organisms. Selecting a higher branch selects all organisms below it. Then select a a tool from the Tool Box.
              </small>
            </div>
            <div class="mb-3">
              <input 
                type="text" 
                class="form-control form-control-sm" 
                id="taxonomy-filter" 
                placeholder="Filter by name..."
                >
            </div>
            <div class="taxonomy-tree-scroll">
              <div id="taxonomy-tree-container"></div>
            </div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card shadow-sm sticky-card mb-3">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">
              Selected Organisms 
              <span class="badge bg-light text-dark" id="selected-count">0</span>
            </h5>
          </div>
          <div class="card-body">
            <div id="selected-organisms-list" class="mb-3">
              <div class="text-muted fst-italic">No organisms selected</div>
            </div>
            
            <div class="mt-3">
              <!--
              <small class="text-muted">
                <i class="fa fa-info-circle"></i> Click any node to select/deselect organisms. 
                Selecting a higher branch selects all organisms below it. Then select a tool to proceed.
              </small>
              -->
            </div>
          </div>
        </div>

        <!-- Tools Card -->
        <?php 
        $context = createToolContext('index', ['use_onclick_handler' => true]);
        include_once TOOL_SECTION_PATH;
        ?>
      </div>
    </div>
  </div>
</div>

<script src="js/modules/taxonomy-tree.js"></script>
<script src="js/index.js"></script>
<script>
const userAccess = <?= $user_access_json ?>;
const treeData = <?= json_encode($taxonomy_tree_data) ?>;
</script>

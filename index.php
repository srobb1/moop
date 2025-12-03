<?php
include_once __DIR__ . '/includes/access_control.php';
include_once __DIR__ . '/lib/moop_functions.php';

$config = ConfigManager::getInstance();
$usersFile = $config->getPath('users_file');
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

// Get visitor IP (set in access_control.php)
$ip = $visitor_ip;

$group_data = getGroupData();

// Get cards to display based on access level
$cards_to_display = getIndexDisplayCards($group_data);

// Load taxonomy tree data
$metadata_path = $config->getPath('metadata_path');
$taxonomy_tree_data = json_decode(file_get_contents("$metadata_path/taxonomy_tree_config.json"), true);

// Get user access for taxonomy tree
$taxonomy_user_access = getTaxonomyTreeUserAccess($group_data);

// Convert user_access to JSON for JavaScript
$user_access_json = json_encode($taxonomy_user_access);

$siteTitle = $config->getString('siteTitle');

include_once __DIR__ . '/includes/page-setup.php';
?>

<body class="bg-light">
<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?=$siteTitle?></h1>
    <hr class="mx-auto page-header-divider">
  </div>


  <!-- Site Info Card -->
  <div class="row g-4 justify-content-center mb-5">
    <div class="col-md-12 col-lg-10">
      <div class="card h-100 shadow-sm border-0 rounded-3 bg-info bg-opacity-10">
        <div class="card-body">
          <h3 class="card-title fw-bold text-dark mb-3">
            <!-- <i class="fa fa-info-circle"></i> About This Site -->
          </h3>
          <p class="card-text text-muted mb-0">
            <strong>MOOP</strong> — to keep company, associate closely. Explore and discover how diverse organisms associate closely together on one platform.
          </p>
        </div>
      </div>
    </div>
  </div>
  

  <!-- Available Organisms Header -->
  <div class="text-center mb-4">
    <h3 class="fw-bold mb-3">Select organisms by group or customize seletion with the taxonomy tree </h3>
    <!--- hide ip and access for now
    <p class="text-muted">
      <i class="fa fa-network-wired"></i> IP: <span class="fw-semibold"><?= htmlspecialchars($ip) ?></span>  
      &nbsp;|&nbsp; <i class="fa fa-user-shield"></i> Access: <span class="fw-semibold"><?= htmlspecialchars(get_access_level()) ?></span>
    </p>
    -->
  </div>

  <!-- View Toggle -->
  <div class="text-center mb-4">
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-outline-primary active" id="card-view-btn" onclick="switchView('card')">
        <i class="fa fa-th"></i> Card View
      </button>
      <button type="button" class="btn btn-outline-primary" id="tree-view-btn" onclick="switchView('tree')">
        <i class="fa fa-project-diagram"></i> Tree View
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
                <h3 class="card-title mb-3 fw-bold text-dark"><?= $card['title'] ?></h3>
                <p class="card-text text-muted mb-3"><?= $card['text'] ?></p>
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
            <h5 class="mb-0"><i class="fa fa-project-diagram"></i> Taxonomy Tree</h3>
          </div>
          <div class="card-body taxonomy-tree-scroll">
            <div id="taxonomy-tree-container"></div>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card shadow-sm sticky-card mb-3">
          <div class="card-header bg-success text-white">
            <h5 class="mb-0">
              Selected Organisms 
              <span class="badge bg-light text-dark" id="selected-count">0</span>
            </h3>
          </div>
          <div class="card-body">
            <div id="selected-organisms-list" class="mb-3">
              <div class="text-muted fst-italic">No organisms selected</div>
            </div>
            
            <div class="mt-3">
              <small class="text-muted">
                <i class="fa fa-info-circle"></i> Click any node to select/deselect organisms. 
                Selecting a higher branch selects all organisms below it. Then select a tool to proceed.
              </small>
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


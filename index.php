<?php
include_once __DIR__ . '/includes/access_control.php';

$usersFile = $users_file;
$users = [];
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true);
}

// Get visitor IP for display purposes only
$ip = $visitor_ip;

function get_group_data() {
    global $metadata_path;
    $groups_file = "$metadata_path/organism_assembly_groups.json";
    $groups_data = [];
    if (file_exists($groups_file)) {
        $groups_data = json_decode(file_get_contents($groups_file), true);
    }
    return $groups_data;
}

$group_data = get_group_data();

function get_all_cards($group_data) {
    $cards = [];
    foreach ($group_data as $data) {
        foreach ($data['groups'] as $group) {
            if (!isset($cards[$group])) {
                $cards[$group] = [
                    'title' => $group,
                    'text' => "Explore $group Data",
                    'link' => 'tools/display/groups_display.php?group=' . urlencode($group)
                ];
            }
        }
    }
    return $cards;
}

$all_cards = get_all_cards($group_data);

// Determine cards to display based on access_level
$cards_to_display = [];

if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
    $cards_to_display = $all_cards;
} elseif (is_logged_in()) {
    if (isset($all_cards['Public'])) {
        $cards_to_display['Public'] = $all_cards['Public'];
    }
    foreach (get_user_access() as $organism => $assemblies) {
        if (!isset($cards_to_display[$organism])) {
            // Format organism name: split on underscores, capitalize first word, lowercase rest, italicize
            $parts = explode('_', $organism);
            $formatted_name = ucfirst(strtolower($parts[0]));
            for ($i = 1; $i < count($parts); $i++) {
                $formatted_name .= ' ' . strtolower($parts[$i]);
            }
            $formatted_name = '<i>' . $formatted_name . '</i>';
            
            $cards_to_display[$organism] = [
                'title' => $formatted_name,
                'text'  => "Explore " . $formatted_name . " Data",
                'link'  => 'tools/display/organism_display.php?organism=' . urlencode($organism)
            ];
        }
    }
} else {
    if (isset($all_cards['Public'])) {
        $cards_to_display['Public'] = $all_cards['Public'];
    }
}

// Load phylogenetic tree data
$phylo_tree_data = json_decode(file_get_contents("$metadata_path/phylo_tree_config.json"), true);

// Build user access for phylo tree - include all organisms if admin/ALL access
$phylo_user_access = [];
if (get_access_level() === 'ALL' || get_access_level() === 'Admin') {
    // Admin gets access to all organisms
    foreach ($group_data as $data) {
        $organism = $data['organism'];
        if (!isset($phylo_user_access[$organism])) {
            $phylo_user_access[$organism] = true;
        }
    }
} elseif (is_logged_in()) {
    // Logged-in users get their specific access
    $phylo_user_access = get_user_access();
} else {
    // Public users: get organisms in Public group
    foreach ($group_data as $data) {
        if (in_array('Public', $data['groups'])) {
            $organism = $data['organism'];
            if (!isset($phylo_user_access[$organism])) {
                $phylo_user_access[$organism] = true;
            }
        }
    }
}

// Convert user_access to JSON for JavaScript
$user_access_json = json_encode($phylo_user_access);

include_once __DIR__ . '/includes/header.php';
?>

<body class="bg-light">
<div class="container py-5">
  <!-- Page Header -->
  <div class="text-center mb-5">
    <h1 class="fw-bold mb-3"><?=$siteTitle?></h1>
    <hr class="mx-auto page-header-divider">
    <h2 class="fw-bold mt-4 mb-3">Available Organisms</h2>
    <p class="text-muted">
      <i class="fa fa-network-wired"></i> IP: <span class="fw-semibold"><?= htmlspecialchars($ip) ?></span>  
      &nbsp;|&nbsp; <i class="fa fa-user-shield"></i> Access: <span class="fw-semibold"><?= htmlspecialchars(get_access_level()) ?></span>
    </p>
  </div>

  <!-- View Toggle -->
  <div class="text-center mb-4">
    <div class="btn-group" role="group">
      <button type="button" class="btn btn-outline-primary active" id="card-view-btn" onclick="switchView('card')">
        <i class="fa fa-th"></i> Card View
      </button>
      <button type="button" class="btn btn-outline-primary" id="tree-view-btn" onclick="switchView('tree')">
        <i class="fa fa-project-diagram"></i> Phylogenetic Tree
      </button>
    </div>
  </div>

  <!-- Card View -->
  <div id="card-view" class="view-container">
    <div class="row g-4 justify-content-center">
      <?php foreach ($cards_to_display as $card): ?>
        <div class="col-md-6 col-lg-4">
          <a href="<?= htmlspecialchars($card['link']) ?>" class="text-decoration-none">
            <div class="card h-100 shadow-sm border-0 rounded-3 organism-card">
              <div class="card-body text-center d-flex flex-column">
                <div class="mb-3">
                  <div class="organism-icon mx-auto">
                    <i class="fa fa-dna"></i>
                  </div>
                </div>
                <h5 class="card-title mb-3 fw-bold text-dark"><?= $card['title'] ?></h5>
                <p class="card-text text-muted mb-3"><?= $card['text'] ?></p>
                <div class="mt-auto">
                  <span class="btn btn-primary btn-sm">
                    View Details <i class="fa fa-arrow-right"></i>
                  </span>
                </div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Phylogenetic Tree View -->
  <div id="tree-view" class="view-container hidden">
    <div class="row">
      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fa fa-project-diagram"></i> Phylogenetic Tree</h5>
          </div>
          <div class="card-body phylo-tree-scroll">
            <div id="phylo-tree-container"></div>
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
              <small class="text-muted">
                <i class="fa fa-info-circle"></i> Click any node to select/deselect organisms. 
                Selecting a higher branch selects all organisms below it.
              </small>
            </div>
          </div>
        </div>

        <!-- Tools Card -->
        <?php 
        $context = ['display_name' => 'Multi-Organism Search'];
        include_once __DIR__ . '/tools/tool_config.php';
        include_once __DIR__ . '/tools/moop_functions.php';
        $tools = getAvailableTools($context);
        
        if (!empty($tools)):
        ?>
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fa fa-toolbox"></i> Tools</h5>
            </div>
            <div class="card-body p-2">
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($tools as $tool_id => $tool): ?>
                        <button 
                           class="btn <?= htmlspecialchars($tool['btn_class']) ?> btn-sm"
                           title="<?= htmlspecialchars($tool['description']) ?>"
                           id="tool-btn-<?= htmlspecialchars($tool_id) ?>"
                           onclick="handleToolClick('<?= htmlspecialchars($tool_id) ?>')">
                          <i class="fa <?= htmlspecialchars($tool['icon']) ?>"></i>
                          <span><?= htmlspecialchars($tool['name']) ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="js/phylo_tree.js"></script>
<script>
const userAccess = <?= $user_access_json ?>;
const treeData = <?= json_encode($phylo_tree_data) ?>;

function handleToolClick(toolId) {
  if (!phyloTree || phyloTree.selectedOrganisms.size === 0) {
    alert('Please select at least one organism');
    return;
  }
  
  const organisms = Array.from(phyloTree.selectedOrganisms);
  
  if (toolId === 'phylo_search') {
    const params = organisms.map(org => `organisms[]=${encodeURIComponent(org)}`).join('&');
    window.location.href = `tools/search/multi_organism_search.php?${params}`;
  } else if (toolId === 'download_fasta') {
    const params = organisms.map(org => encodeURIComponent(org)).join(',');
    window.location.href = `tools/extract/download_fasta.php?organisms=${params}`;
  }
}

function switchView(view) {
  const cardView = document.getElementById('card-view');
  const treeView = document.getElementById('tree-view');
  const cardBtn = document.getElementById('card-view-btn');
  const treeBtn = document.getElementById('tree-view-btn');
  
  if (view === 'card') {
    cardView.style.display = 'block';
    treeView.style.display = 'none';
    cardBtn.classList.add('active');
    treeBtn.classList.remove('active');
  } else {
    cardView.style.display = 'none';
    treeView.style.display = 'block';
    cardBtn.classList.remove('active');
    treeBtn.classList.add('active');
    
    // Initialize tree on first view
    if (!phyloTree) {
      initPhyloTree(treeData, userAccess);
    }
  }
}
</script>

<!-- Enhanced CSS -->
<style>
  /* Organism card styling */
  .organism-card {
    transition: all 0.3s ease-in-out;
    border: 1px solid rgba(0,0,0,0.05) !important;
  }
  
  .organism-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 1rem 2rem rgba(0,0,0,0.15) !important;
  }
  
  /* Icon circle */
  .organism-icon {
    width: 70px;
    height: 70px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    transition: all 0.3s ease;
  }
  
  .organism-card:hover .organism-icon {
    transform: scale(1.1) rotate(5deg);
  }
  
  /* Card text colors */
  .organism-card .card-title {
    color: #2c3e50;
  }
  
  .organism-card:hover .card-title {
    color: #667eea;
  }
  
  /* Button styling */
  .organism-card .btn {
    transition: all 0.3s ease;
  }
  
  .organism-card:hover .btn {
    transform: translateX(5px);
  }

  /* Phylogenetic Tree Styling */
  .phylo-tree-scroll {
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: auto;
  }

  .phylo-node {
    padding: 8px 12px;
    margin: 2px 0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    background: #f8f9fa;
    border: 2px solid transparent;
    user-select: none;
  }

  .phylo-node:hover {
    background: #e9ecef;
    transform: translateX(4px);
  }

  .phylo-node.selected {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-color: #667eea;
    font-weight: 600;
  }

  .phylo-node.partial {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.3) 0%, rgba(118, 75, 162, 0.3) 100%);
    border-color: #667eea;
    border-style: dashed;
  }

  .phylo-icon {
    margin-right: 8px;
    font-size: 16px;
    min-width: 20px;
  }

  .phylo-name {
    flex: 1;
  }

  .phylo-leaf {
    font-style: italic;
    background: #fff;
    border: 1px solid #dee2e6;
  }

  .phylo-leaf:hover {
    border-color: #667eea;
    background: #f0f4ff;
  }

  .phylo-leaf.selected {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    border-color: #28a745;
  }

  .phylo-branch {
    font-weight: 500;
  }

  .view-container {
    animation: fadeIn 0.3s ease-in;
  }

  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }

  #selected-organisms-list .badge {
    font-size: 0.85rem;
    padding: 0.4em 0.6em;
  }
</style>

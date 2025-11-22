<?php
include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$metadata_path = $config->getPath('metadata_path');
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$siteTitle = $config->getString('siteTitle');

// Get the group name from query parameter
$group_name = $_GET['group'] ?? '';

if (empty($group_name)) {
    header("Location: /$site/index.php");
    exit;
}

// Load group descriptions using helper
$group_descriptions_file = "$metadata_path/group_descriptions.json";
$group_descriptions = loadJsonFile($group_descriptions_file, []);

// Load organism assembly groups
$group_data = getGroupData();

// Find the description for this group
$group_info = null;
foreach ($group_descriptions as $desc) {
    if ($desc['group_name'] === $group_name && ($desc['in_use'] ?? false)) {
        $group_info = $desc;
        break;
    }
}

// Get organisms in this group that user has access to
$group_organisms = getAccessibleOrganismsInGroup($group_name, $group_data);

// Access control: Check if user has access to this group (with automatic redirect)
if (!is_public_group($group_name)) {
    requireAccess('Collaborator', $group_name);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($group_name) ?> - <?= $siteTitle ?></title>
  <?php include_once __DIR__ . '/../includes/head.php'; ?>
  <!-- Display page styles (consolidated from display_styles.css and shared_results_table.css) -->
  <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">

  <!-- Header and Tools Row -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><?= htmlspecialchars($group_name) ?></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> <?= htmlspecialchars($group_name) ?>: Search Gene IDs and Annotations</h4>
          <form id="groupSearchForm">
            <div class="row">
              <div class="col-md-10">
                <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                <small class="form-text text-muted-gray">
                  Use quotes for exact phrases (e.g., "ABC transporter"). Searches across all organisms in this group.
                </small>
              </div>
              <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100" id="searchBtn">
                  <i class="fa fa-search"></i> Search
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
      <?php 
      $context = createGroupToolContext($group_name);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-info text-white">
        <h4 class="mb-0"><i class="fa fa-list"></i> Search Results</h4>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

  <?php if ($group_info): ?>
    <!-- Group Description Section -->
    <div class="row mb-5" id="groupDescription">
      <?php if (!empty($group_info['images'])): ?>
        <?php foreach ($group_info['images'] as $image): ?>
          <?php if (!empty($image['file'])): ?>
            <div class="col-md-4 mb-3">
              <div class="card">
                <img src="/<?= $images_path ?>/<?= htmlspecialchars($image['file']) ?>" class="card-img-top" alt="<?= htmlspecialchars($group_name) ?>">
                <?php if (!empty($image['caption'])): ?>
                  <div class="card-body">
                    <p class="card-text small text-muted"><?= $image['caption'] ?></p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endif; ?>
      
      <div class="<?= !empty($group_info['images'][0]['file']) ? 'col-md-8' : 'col-12' ?>">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4">About <?= htmlspecialchars($group_name) ?></h3>
            <?php if (!empty($group_info['html_p'])): ?>
              <?php foreach ($group_info['html_p'] as $paragraph): ?>
                <p class="<?= htmlspecialchars($paragraph['class'] ?? '') ?>" style="<?= htmlspecialchars($paragraph['style'] ?? '') ?>">
                  <?= $paragraph['text'] ?>
                </p>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Organisms Section -->
  <div class="row mb-5" id="organismsSection">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4">Organisms in <?= htmlspecialchars($group_name) ?> Group</h3>
          <?php if (empty($group_organisms)): ?>
            <div class="alert alert-info mb-0">
              <i class="fa fa-info-circle"></i> No organisms are currently available in this group.
            </div>
          <?php else: ?>
            <div class="row g-3">
              <?php foreach ($group_organisms as $organism => $assemblies): ?>
                <?php
                  $organism_json_path = "$organism_data/$organism/organism.json";
                  $organism_info = [];
                  if (file_exists($organism_json_path)) {
                    $organism_info = json_decode(file_get_contents($organism_json_path), true);
                    
                    // Handle improperly wrapped JSON (extra outer braces)
                    if ($organism_info && !isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
                      $keys = array_keys($organism_info);
                      if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
                        $organism_info = $organism_info[$keys[0]];
                      }
                    }
                  }
                  $genus = $organism_info['genus'] ?? '';
                  $species = $organism_info['species'] ?? '';
                  $common_name = $organism_info['common_name'] ?? '';
                  
                  $image_src = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
                  $show_image = !empty($image_src);
                ?>
                <div class="col-md-6 col-lg-4">
                  <a href="/<?= $site ?>/tools/organism_display.php?organism=<?= urlencode($organism) ?>&group=<?= urlencode($group_name) ?>" 
                     class="text-decoration-none">
                    <div class="card h-100 shadow-sm organism-card">
                      <div class="card-body text-center">
                        <div class="organism-image-container mb-3">
                          <?php if ($show_image): ?>
                            <img src="<?= $image_src ?>" 
                                 alt="<?= htmlspecialchars($organism) ?>"
                                 class="organism-card-image"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <?php endif; ?>
                          <div class="organism-card-icon <?= $show_image ? 'display-none' : '' ?>" style="display: <?= $show_image ? 'none' : 'flex' ?>;">
                            <i class="fa fa-dna fa-4x text-primary"></i>
                          </div>
                        </div>
                        <h5 class="card-title mb-2">
                          <em><?= htmlspecialchars($genus . ' ' . $species) ?></em>
                        </h5>
                        <?php if ($common_name): ?>
                          <p class="text-muted mb-0"><?= htmlspecialchars($common_name) ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                  </a>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

</div>


<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<!-- DataTables 1.13.4 core and Bootstrap 5 theme JavaScript -->
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<!-- DataTables Buttons 2.3.6 core functionality -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<!-- DataTables Buttons 2.3.6 with Bootstrap 5 theme -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<!-- HTML5 export module for CSV and Excel functionality -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<!-- Print functionality for DataTables -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<!-- Column visibility toggle functionality -->
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<!-- jszip for Excel export functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<!-- Column reordering functionality -->
<script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
<script src="/<?= $site ?>/js/features/datatable-config.js"></script>
<script src="/<?= $site ?>/tools/shared_results_table.js"></script>
<script>
// Data variables - PHP provides these for use by the external JS file
const groupOrganisms = <?= json_encode(array_keys($group_organisms)) ?>;
const groupName = <?= json_encode($group_name) ?>;
const sitePath = '/<?= $site ?>';
</script>

<!-- Page-specific logic -->
<script src="/<?= $site ?>/js/pages/groups-display.js"></script>

</body>
</html>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>

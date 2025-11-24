<?php
include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');

// Get organisms from query parameters
$organisms = $_GET['organisms'] ?? [];
if (is_string($organisms)) {
    $organisms = [$organisms];
}

if (empty($organisms)) {
    header("Location: /$site/index.php");
    exit;
}

// Validate access for all organisms
foreach ($organisms as $organism) {
    $is_public = is_public_organism($organism);
    $has_organism_access = has_access('Collaborator', $organism);
    
    if (!$has_organism_access && !$is_public) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>Multi-Organism Search - <?= $siteTitle ?></title>
  <?php include_once __DIR__ . '/../includes/head.php'; ?>
  <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
  <link rel="stylesheet" href="/<?= $site ?>/css/advanced-search-filter.css">
  <link rel="stylesheet" href="/<?= $site ?>/css/search-controls.css">
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
          <h1 class="fw-bold mb-0 text-center">Multi-Organism Search</h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations</h4>
          <form id="multiOrgSearchForm">
            <div class="row align-items-center">
              <div class="col">
                <div class="d-flex gap-2 align-items-center">
                  <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                  <button type="submit" class="btn btn-icon btn-search" id="searchBtn" title="Search" data-bs-toggle="tooltip" data-bs-placement="bottom">
                    <i class="fa fa-search"></i>
                  </button>
                </div>
              </div>
            </div>
            <div class="row mt-2">
              <div class="col">
                <small class="form-text text-muted-gray">
                  Use quotes for exact phrases (e.g., "ABC transporter"). Searches across all selected organisms.
                </small>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Tools Column -->
    <div class="col-lg-4">
      <?php 
      $context = createMultiOrganismToolContext($organisms);
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

  <!-- Organisms Section -->
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4">Selected Organisms</h3>
          <div class="row g-3">
            <?php
            $organism_list = is_array($organisms) ? $organisms : [$organisms];
            foreach ($organism_list as $organism): 
                $organism_data_result = loadOrganismAndGetImagePath($organism, $images_path, $absolute_images_path);
                $organism_info = $organism_data_result['organism_info'];
                $image_src = $organism_data_result['image_path'];
                $show_image = !empty($image_src);
                $genus = $organism_info['genus'] ?? '';
                $species = $organism_info['species'] ?? '';
                $common_name = $organism_info['common_name'] ?? '';
            ?>
              <div class="col-md-6 col-lg-4">
                <a href="/<?= $site ?>/tools/organism_display.php?organism=<?= urlencode($organism) ?><?php foreach($organism_list as $org): ?>&multi_search[]=<?= urlencode($org) ?><?php endforeach; ?>" 
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
        </div>
      </div>
    </div>
  </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
<script src="/<?= $site ?>/js/features/datatable-config.js"></script>
<script src="/<?= $site ?>/tools/shared_results_table.js"></script>
<script src="/<?= $site ?>/js/core/annotation-search.js"></script>
<script src="/<?= $site ?>/js/features/advanced-search-filter.js"></script>
<script>
// Data variables - PHP provides these for use by the external JS file
const selectedOrganisms = <?= json_encode(is_array($organisms) ? $organisms : [$organisms]) ?>;
const totalOrganisms = selectedOrganisms.length;
const sitePath = '/<?= $site ?>';
</script>

<!-- Page-specific logic -->
<script src="/<?= $site ?>/js/pages/multi-organism-search.js"></script>

</body>
</html>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>

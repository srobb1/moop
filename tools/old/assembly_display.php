<?php
include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');

// Validate parameters
$organism_name = validateOrganismParam($_GET['organism'] ?? '');
$assembly_param = validateAssemblyParam($_GET['assembly'] ?? '');

// Setup organism context (loads info, checks access)
$organism_context = setupOrganismDisplayContext($organism_name, $organism_data, true);
$organism_info = $organism_context['info'];

// Verify database exists
$db_path = verifyOrganismDatabase($organism_name, $organism_data);

// The assembly parameter could be either a genome_name or genome_accession
// Try to get assembly stats using the parameter as-is first (might be accession)
$assembly_info = getAssemblyStats($assembly_param, $db_path);

// If not found, try to look it up by name in the database
if (empty($assembly_info)) {
    // Query by genome_name instead of accession
    $query = "SELECT g.genome_id, g.genome_accession, g.genome_name,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'gene' THEN f.feature_id END) as gene_count,
                     COUNT(DISTINCT CASE WHEN f.feature_type = 'mRNA' THEN f.feature_id END) as mrna_count,
                     COUNT(DISTINCT f.feature_id) as total_features
              FROM genome g
              LEFT JOIN feature f ON g.genome_id = f.genome_id
              WHERE g.genome_name = ?
              GROUP BY g.genome_id";
    
    $results = fetchData($query, $db_path, [$assembly_param]);
    $assembly_info = !empty($results) ? $results[0] : [];
}

if (empty($assembly_info)) {
    die("Error: Assembly not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($assembly_info['genome_name']) ?> - <?= $siteTitle ?></title>
  <?php include_once __DIR__ . '/../includes/head-resources.php'; ?>
  <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
  <link rel="stylesheet" href="/<?= $site ?>/css/parent.css">
  <link rel="stylesheet" href="/<?= $site ?>/css/advanced-search-filter.css">
  <link rel="stylesheet" href="/<?= $site ?>/css/search-controls.css">
  <style>
    /* Override feature-header h1 color for assembly display */
    .assembly-header-custom h1 {
      background-color: #d97706 !important;
    }
  </style>
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><?= htmlspecialchars($assembly_info['genome_name']) ?></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations</h4>
          <form id="assemblySearchForm">
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
                  Use quotes for exact phrases (e.g., "ABC transporter"). Searches this assembly only.
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
      $context = createToolContext('assembly', [
          'organism' => $organism_name,
          'assembly' => $assembly_accession,
          'display_name' => $assembly_info['genome_name']
      ]);
      include_once TOOL_SECTION_PATH;
      ?>
    </div>
  </div>

  <!-- Search Results Section -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-4">
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

  <!-- Assembly Header Section with Info -->
  <div class="row mb-4" id="assemblyHeader">
    <?php 
    $image_data = getOrganismImageWithCaption($organism_info, $images_path, $absolute_images_path);
    $image_src = $image_data['image_path'];
    $image_info = ['caption' => $image_data['caption'], 'link' => $image_data['link']];
    $show_image = !empty($image_src);
    $image_alt = htmlspecialchars($organism_info['common_name'] ?? $organism_name);
    ?>
    
    <?php if ($show_image): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="<?= $image_src ?>" 
               class="card-img-top" 
               alt="<?= $image_alt ?>">
          <?php if (!empty($image_info['caption'])): ?>
            <div class="card-body">
              <p class="card-text small text-muted">
                <?php if (!empty($image_info['link'])): ?>
                  <a href="<?= $image_info['link'] ?>" target="_blank" class="text-decoration-none">
                    <?= $image_info['caption'] ?> <i class="fa fa-external-link-alt fa-xs"></i>
                  </a>
                <?php else: ?>
                  <?= $image_info['caption'] ?>
                <?php endif; ?>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="<?= $show_image ? 'col-md-8' : 'col-12' ?>">
      <div class="feature-header assembly-header-custom shadow">
        <h1 class="mb-0">
          <?= htmlspecialchars($assembly_info['genome_name']) ?>
          <span class="badge bg-light text-dark ms-2">Assembly</span>
        </h1>
        
        <div class="feature-info-item">
          <strong>Accession:</strong> <span class="feature-value text-monospace"><?= htmlspecialchars($assembly_info['genome_accession']) ?></span>
        </div>
        <div class="feature-info-item">
          <strong>Organism:</strong> <span class="feature-value"><em><a href="/<?= $site ?>/tools/organism_display.php?organism=<?= urlencode($organism_name) ?>" class="link-light-bordered"><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></a></em></span>
        </div>
      </div>
    </div>
  </div>

  <!-- Gene and mRNA Counts Section -->
  <div class="row g-4 mb-5" id="assemblyStats">
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-gene mb-3">Genes</h2>
            <h2 class="fw-bold feature-color-gene"><?= number_format($assembly_info['gene_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="text-center">
            <h2 class="fw-bold feature-color-mrna mb-3">Transcripts</h2>
            <h2 class="fw-bold feature-color-mrna"><?= number_format($assembly_info['mrna_count'] ?? 0) ?></h2>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Assembly Downloads Section -->
  <?php
  $fasta_files = getAssemblyFastaFiles($organism_name, $assembly_accession);
  ?>
  
  <?php if (!empty($fasta_files)): ?>
  <div class="row mb-4" id="assemblyDownloads">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-download"></i> Download Sequence Files</h3>
          <div class="row g-3">
            <?php foreach ($fasta_files as $type => $file_info): ?>
              <div class="col-6 col-md-3">
                <a href="/<?= $site ?>/tools/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly_accession) ?>&type=<?= urlencode($type) ?>" 
                   class="btn btn-primary w-100 py-4 fw-bold"
                   style="border-radius: 0.75rem; font-size: 1rem;"
                   download>
                  <i class="fa fa-download me-2"></i><?= htmlspecialchars($file_info['label']) ?>
                </a>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

</div>

<!-- Include jQuery and DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
<script src="/<?= $site ?>/js/modules/datatable-config.js"></script>
<script src="/<?= $site ?>/js/modules/shared-results-table.js"></script>

<script src="/<?= $site ?>/js/modules/annotation-search.js"></script>
<script src="/<?= $site ?>/js/modules/advanced-search-filter.js"></script>

<script>
// Data variables - PHP provides these for use by the external JS file
const sitePath = '/<?= $site ?>';
const organismName = '<?= $organism_name ?>';
const assemblyAccession = '<?= $assembly_accession ?>';
</script>

<!-- Page-specific logic -->
<script src="/<?= $site ?>/js/assembly-display.js"></script>

</body>
</html>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>

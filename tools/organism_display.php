<?php
include_once __DIR__ . '/tool_init.php';

// Load page-specific config
$organism_data = $config->getPath('organism_data');
$absolute_images_path = $config->getPath('absolute_images_path');
$siteTitle = $config->getString('siteTitle');

// Setup organism context (validates param, loads info, checks access)
$organism_context = setupOrganismDisplayContext($_GET['organism'] ?? '', $organism_data);
$organism_name = $organism_context['name'];
$organism_info = $organism_context['info'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title><?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?> - <?= $siteTitle ?></title>
  <?php include_once __DIR__ . '/../includes/head.php'; ?>
  <!-- Display page styles (consolidated from display_styles.css and shared_results_table.css) -->
  <link rel="stylesheet" href="/<?= $site ?>/css/display.css">
</head>
<body class="bg-light">

<?php include_once __DIR__ . '/../includes/navbar.php'; ?>

<div class="container mt-5">
  <?php
  // Build navigation context using smart builder
  $nav_context = buildNavContext('organism', [
      'organism' => $organism_name,
      'group' => $_GET['group'] ?? '',
      'parent' => $_GET['parent'] ?? '',
      'multi_search' => $_GET['multi_search'] ?? []
  ]);
  echo render_navigation_buttons($nav_context);
  ?>

  <!-- Search Section -->
  <div class="row mb-4">
    <!-- Title and Search Column -->
    <div class="col-lg-8">
      <div class="card shadow-sm h-100">
        <!-- Title Card -->
        <div class="card-header bg-light border-bottom">
          <h1 class="fw-bold mb-0 text-center"><em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em></h1>
        </div>

        <!-- Search Section -->
        <div class="card-body bg-search-light">
          <h4 class="mb-3 text-primary fw-bold"><i class="fa fa-search"></i> Search Gene IDs and Annotations</h4>
          <form id="organismSearchForm">
            <div class="row">
              <div class="col-md-10">
                <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
                <small class="form-text text-muted-gray">
                  Use quotes for exact phrases (e.g., "ABC transporter"). Searches this organism only.
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
      $context = createOrganismToolContext($organism_name, $organism_info['common_name'] ?? $organism_name);
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

  <!-- Organism Header Section -->
  <div class="row mb-4" id="organismHeader">
    <?php 
    $image_src = getOrganismImagePath($organism_info, $images_path, $absolute_images_path);
    $image_info = getOrganismImageCaption($organism_info, $absolute_images_path);
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
      <div class="card shadow-sm">
        <div class="card-body">
          <h1 class="fw-bold mb-2">
            <?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?>
          </h1>
          <h3 class="text-muted mb-3">
            <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> 
                <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>
          </h3>
          
          <?php if (!empty($organism_info['taxon_id'])): ?>
            <p class="mb-3">
              <strong>Taxon ID:</strong> 
              <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($organism_info['taxon_id']) ?>" 
                 target="_blank" 
                 class="text-decoration-none">
                <?= htmlspecialchars($organism_info['taxon_id']) ?>
                <i class="fa fa-external-link-alt fa-xs"></i>
              </a>
            </p>
          <?php endif; ?>

          <?php if (!empty($organism_info['subclassification']['type']) && !empty($organism_info['subclassification']['value'])): ?>
            <p class="mb-3">
              <strong><?= htmlspecialchars($organism_info['subclassification']['type']) ?>:</strong> 
              <?= htmlspecialchars($organism_info['subclassification']['value']) ?>
            </p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Description Section -->
  <div id="organismContent">
  <?php if (!empty($organism_info['html_p']) && is_array($organism_info['html_p'])): ?>
    <div class="row mb-4">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-body">
            <h3 class="card-title mb-4">About <?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?></h3>
            <div class="organism-text">
              <?php foreach ($organism_info['html_p'] as $paragraph): ?>
                <p class="<?= htmlspecialchars($paragraph['class'] ?? '') ?>" 
                   style="<?= htmlspecialchars($paragraph['style'] ?? '') ?>">
                  <?= $paragraph['text'] ?>
                </p>
              <?php endforeach; ?>
            </div>
            
            <?php if (!empty($organism_info['text_src'])): ?>
              <div class="mt-3 text-muted">
                <small>
                  <?php if (filter_var($organism_info['text_src'], FILTER_VALIDATE_URL)): ?>
                    Source: <a href="<?= htmlspecialchars($organism_info['text_src']) ?>" target="_blank">Link</a>
                  <?php else: ?>
                    Source: <?= htmlspecialchars($organism_info['text_src']) ?>
                  <?php endif; ?>
                </small>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- Assemblies Section -->
  <?php
  // Get accessible assemblies for this organism
  $group_data = getGroupData();
  $accessible_assemblies = [];
  
  foreach ($group_data as $data) {
      if ($data['organism'] === $organism_name) {
          if (has_assembly_access($organism_name, $data['assembly'])) {
              $accessible_assemblies[] = $data['assembly'];
          }
      }
  }
  ?>
  
  <?php if (!empty($accessible_assemblies)): ?>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4">Available Assemblies</h3>
          <div class="row g-3">
            <?php foreach ($accessible_assemblies as $assembly): ?>
              <?php $fasta_files = getAssemblyFastaFiles($organism_name, $assembly); ?>
              <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm organism-card">
                  <div class="card-body text-center">
                    <a href="/<?= $site ?>/tools/assembly_display.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>" 
                       class="text-decoration-none">
                      <h5 class="card-title mb-3">
                        <?= htmlspecialchars($assembly) ?> <i class="fa fa-external-link-alt"></i>
                      </h5>
                    </a>
                    <?php if (!empty($fasta_files)): ?>
                      <div class="mt-3 pt-2 border-top">
                        <?php foreach ($fasta_files as $type => $file_info): ?>
                          <a href="/<?= $site ?>/tools/fasta_download_handler.php?organism=<?= urlencode($organism_name) ?>&assembly=<?= urlencode($assembly) ?>&type=<?= urlencode($type) ?>" 
                             class="btn btn-sm btn-primary w-100 mb-2"
                             download>
                            <i class="fa fa-download"></i> <?= htmlspecialchars($file_info['label']) ?>
                          </a>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- End organismContent -->
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
<script src="/<?= $site ?>/js/datatable-config.js"></script>
<script src="/<?= $site ?>/tools/shared_results_table.js"></script>

<script>
const sitePath = '/<?= $site ?>';
const organismName = '<?= $organism_name ?>';
let allResults = [];
let searchedOrganisms = 0;
const totalOrganisms = 1; // Single organism search
let organismImagePaths = {}; // Store image paths by organism

// Context for navigation and data flow
const pageContext = {
    context_organism: '<?= htmlspecialchars($organism_name) ?>',
    context_assembly: '',
    context_group: '',
    organisms: '',
    display_name: ''
};

$('#organismSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide organism content on first search
    if ($('#searchResults').is(':hidden')) {
        $('#organismHeader').slideUp();
        $('#organismContent').slideUp();
        $('#backToOrganismBtn').show();
    }
    
    // Reset and show results section
    allResults = [];
    searchedOrganisms = 0;
    $('#searchResults').show();
    $('#resultsContainer').html('');
    $('#searchInfo').html(`Searching for: <strong>${keywords}</strong> in <?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?>`);
    
    // Show progress bar
    $('#searchProgress').html(`
        <div class="search-progress-bar">
            <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
        </div>
        <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
    `);
    
    // Search the single organism
    searchOrganism(organismName, keywords, quotedSearch);
});

// Back to organism button handler
$('#backToOrganismBtn').on('click', function(e) {
    e.preventDefault();
    $('#searchResults').slideUp();
    $('#organismHeader').slideDown();
    $('#organismContent').slideDown();
    $('#backToOrganismBtn').hide();
    $('#searchKeywords').val('');
});

function searchOrganism(organism, keywords, quotedSearch) {
    $('#progressText').html(`Searching ${organism}...`);
    
    $.ajax({
        url: sitePath + '/tools/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: '', // No group context
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(response) {
            searchedOrganisms++;
            
            if (response.results && response.results.length > 0) {
                allResults = allResults.concat(response.results);
                // Store organism image path
                if (response.organism_image_path) {
                    organismImagePaths[response.organism] = response.organism_image_path;
                }
            }
            
            // Update progress
            const progress = (searchedOrganisms / totalOrganisms) * 100;
            $('#progressFill').css('width', progress + '%').text(Math.round(progress) + '%');
            
            // Display final results
            displayResults();
        },
        error: function(xhr, status, error) {
            console.error('Search error for ' + organism + ':', error);
            searchedOrganisms++;
            const progress = (searchedOrganisms / totalOrganisms) * 100;
            $('#progressFill').css('width', progress + '%').text(Math.round(progress) + '%');
            displayResults();
        }
    });
}

function displayResults() {
    if (allResults.length === 0) {
        $('#searchProgress').html('<div class="alert alert-warning">No results found. Try different search terms.</div>');
    } else {
        $('#searchProgress').html(`
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${allResults.length} result${allResults.length !== 1 ? 's' : ''}.
                <hr class="my-2">
                <small>
                    <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                    <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                    <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print) to download selected data.<br>
                    <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                </small>
            </div>
        `);
        
        // Group results by organism (will be single organism)
        const resultsByOrganism = {};
        allResults.forEach(result => {
            if (!resultsByOrganism[result.organism]) {
                resultsByOrganism[result.organism] = [];
            }
            resultsByOrganism[result.organism].push(result);
        });
        
        // Display results for the organism using shared function
        Object.keys(resultsByOrganism).forEach(organism => {
            const results = resultsByOrganism[organism];
            const imageUrl = organismImagePaths[organism] || '';
            $('#resultsContainer').append(createOrganismResultsTable(organism, results, sitePath, 'tools/parent_display.php', imageUrl));
        });
    }
}
</script>

</body>
</html>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>

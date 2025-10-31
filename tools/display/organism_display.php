<?php
include_once __DIR__ . '/../../access_control.php';

// Get organism name from query parameter
$organism_name = $_GET['organism'] ?? '';

if (empty($organism_name)) {
    header("Location: /$site/index.php");
    exit;
}

// Load organism JSON file
$organism_json_path = "$organism_data/$organism_name/organism.json";
$organism_info = null;

if (file_exists($organism_json_path)) {
    $json_content = file_get_contents($organism_json_path);
    $organism_info = json_decode($json_content, true);
    
    // Handle improperly wrapped JSON (extra outer braces)
    if ($organism_info && !isset($organism_info['genus']) && !isset($organism_info['common_name'])) {
        // Check if data is wrapped in an unnamed object
        $keys = array_keys($organism_info);
        if (count($keys) > 0 && is_array($organism_info[$keys[0]]) && isset($organism_info[$keys[0]]['genus'])) {
            $organism_info = $organism_info[$keys[0]];
        }
    }
}

if (!$organism_info || !isset($organism_info['common_name'])) {
    header("Location: /$site/index.php");
    exit;
}

// Access control: Check if user has access to this organism
// Allow access if: user has ALL/Admin access, OR organism is public, OR user has specific access
$is_public = is_public_organism($organism_name);
$has_organism_access = has_access('Collaborator', $organism_name);

if (!$has_organism_access && !$is_public) {
    header("Location: /$site/access_denied.php");
    exit;
}

include_once realpath(__DIR__ . '/../../header.php');
include_once realpath(__DIR__ . '/../../toolbar.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($organism_info['common_name'] ?? str_replace('_', ' ', $organism_name)) ?> - <?= $siteTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css">
  <link rel="stylesheet" href="shared_results_table.css">
  <style>
    #searchKeywords::placeholder {
      color: #999;
      opacity: 1;
    }
  </style>
</head>
<body class="bg-light">

<div class="container mt-5">
  <!-- Navigation Buttons -->
  <div class="mb-3">
    <a href="/<?= $site ?>/index.php" class="btn btn-secondary"><i class="fa fa-home"></i> Back to Home</a>
    <button class="btn btn-secondary" id="backToOrganismBtn" style="display: none;">
      <i class="fa fa-arrow-left"></i> Back to <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em> Page
    </button>
  </div>

  <!-- Search Section -->
  <div class="card shadow-sm mb-4">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0"><i class="fa fa-search"></i> <em><?= htmlspecialchars($organism_info['genus'] ?? '') ?> <?= htmlspecialchars($organism_info['species'] ?? '') ?></em>: Search Gene IDs and Annotations</h4>
    </div>
    <div class="card-body" style="background-color: rgba(13, 110, 253, 0.08);">
      <form id="organismSearchForm">
        <div class="row">
          <div class="col-md-10">
            <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
            <small class="form-text" style="color: #999;">
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

  <!-- Search Results Section -->
  <div id="searchResults" style="display: none;">
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
    <?php if (!empty($organism_info['images']) && is_array($organism_info['images'])): ?>
      <div class="col-md-4 mb-3">
        <div class="card shadow-sm">
          <img src="/<?= $images_path ?>/<?= htmlspecialchars($organism_info['images'][0]['file']) ?>" 
               class="card-img-top" 
               alt="<?= htmlspecialchars($organism_info['common_name'] ?? $organism_name) ?>">
          <?php if (!empty($organism_info['images'][0]['caption'])): ?>
            <div class="card-body">
              <p class="card-text small text-muted">
                <?= $organism_info['images'][0]['caption'] ?>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="<?= !empty($organism_info['images']) ? 'col-md-8' : 'col-12' ?>">
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

  <!-- Data Resources Section -->
  <?php
  // Scan organism directory for data files
  $organism_dir = "$organism_data/$organism_name";
  $data_files = [];
  
  if (is_dir($organism_dir)) {
      // Look for subdirectories with assembly data
      $subdirs = glob($organism_dir . '/*', GLOB_ONLYDIR);
      
      foreach ($subdirs as $subdir) {
          $subdir_name = basename($subdir);
          
          // Look for FASTA files
          $fasta_files = glob($subdir . '/*.fa');
          foreach ($fasta_files as $fasta_file) {
              $filename = basename($fasta_file);
              $relative_path = "$organism_name/$subdir_name/$filename";
              
              // Categorize by file type
              if (strpos($filename, '.cds.nt.fa') !== false) {
                  $data_files['cds'][] = $relative_path;
              } elseif (strpos($filename, '.protein.aa.fa') !== false) {
                  $data_files['protein'][] = $relative_path;
              } elseif (strpos($filename, '.transcript.nt.fa') !== false) {
                  $data_files['transcript'][] = $relative_path;
              } elseif (strpos($filename, '.genome.fa') !== false || strpos($filename, '_genomic.') !== false) {
                  $data_files['genome'][] = $relative_path;
              }
          }
      }
      
      // Look for SQLite database files
      $sqlite_files = glob($organism_dir . '/*.sqlite');
      foreach ($sqlite_files as $sqlite_file) {
          $filename = basename($sqlite_file);
          $relative_path = "$organism_name/$filename";
          $data_files['database'][] = $relative_path;
      }
  }
  ?>
  
  <?php if (!empty($data_files)): ?>
  <div class="row mb-5">
    <div class="col-12">
      <div class="card shadow-sm">
        <div class="card-body">
          <h3 class="card-title mb-4"><i class="fa fa-database"></i> Available Resources</h3>
          
          <div class="row g-3">
            <?php if (!empty($data_files['genome'])): ?>
              <?php foreach ($data_files['genome'] as $file): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-dna"></i> Genome FASTA</h5>
                  <p class="text-muted small mb-2">Complete genome sequence</p>
                  <code class="small"><?= htmlspecialchars($file) ?></code>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($data_files['protein'])): ?>
              <?php foreach ($data_files['protein'] as $file): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-atom"></i> Protein FASTA</h5>
                  <p class="text-muted small mb-2">Amino acid sequences</p>
                  <code class="small"><?= htmlspecialchars($file) ?></code>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($data_files['cds'])): ?>
              <?php foreach ($data_files['cds'] as $file): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-code"></i> CDS FASTA</h5>
                  <p class="text-muted small mb-2">Coding sequences</p>
                  <code class="small"><?= htmlspecialchars($file) ?></code>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($data_files['transcript'])): ?>
              <?php foreach ($data_files['transcript'] as $file): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-file-code"></i> Transcript FASTA</h5>
                  <p class="text-muted small mb-2">Transcript sequences</p>
                  <code class="small"><?= htmlspecialchars($file) ?></code>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
            
            <?php if (!empty($data_files['database'])): ?>
              <?php foreach ($data_files['database'] as $file): ?>
              <div class="col-md-6">
                <div class="resource-card p-3 border rounded">
                  <h5 class="text-primary mb-2"><i class="fa fa-database"></i> Gene Database</h5>
                  <p class="text-muted small mb-2">SQLite database</p>
                  <code class="small"><?= htmlspecialchars($file) ?></code>
                </div>
              </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
  </div><!-- End organismContent -->
</div>

<style>
  .organism-text {
    text-align: justify;
    line-height: 1.6;
  }
  
  .organism-text p {
    margin-bottom: 1rem;
  }
  
  .resource-card {
    background-color: #f8f9fa;
    transition: all 0.3s ease;
  }
  
  .resource-card:hover {
    background-color: #e9ecef;
    transform: translateY(-2px);
    box-shadow: 0 0.25rem 0.5rem rgba(0,0,0,0.1);
  }
  
  .card {
    border: 1px solid rgba(0,0,0,0.1);
  }
  
  code {
    word-break: break-all;
  }
</style>

<!-- Include jQuery and DataTables -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/colreorder/1.6.2/js/dataTables.colReorder.min.js"></script>
<script src="shared_results_table.js"></script>

<script>
const sitePath = '/<?= $site ?>';
const organismName = '<?= $organism_name ?>';
let allResults = [];
let searchedOrganisms = 0;
const totalOrganisms = 1; // Single organism search

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
        url: sitePath + '/tools/search/annotation_search_ajax.php',
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
            $('#resultsContainer').append(createOrganismResultsTable(organism, results, sitePath, 'tools/display/parent.php'));
        });
    }
}
</script>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>

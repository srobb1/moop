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
  
  /* Search widget styles */
  .organism-results {
    margin-bottom: 30px;
  }
  
  .organism-thumbnail {
    height: 40px;
    width: 40px;
    object-fit: cover;
    border-radius: 4px;
    vertical-align: middle;
  }
  
  .search-progress-bar {
    width: 100%;
    height: 30px;
    background-color: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
  }
  
  .search-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transition: width 0.3s ease;
  }
  
  .results-table {
    font-size: 0.9rem;
  }
  
  .results-table thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 10;
  }
  
  .wrap-text {
    white-space: normal !important;
    word-wrap: break-word;
    word-break: break-word;
  }
  
  .loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 3px solid rgba(0,0,0,.1);
    border-radius: 50%;
    border-top-color: #007bff;
    animation: spin 1s ease-in-out infinite;
  }
  
  @keyframes spin {
    to { transform: rotate(360deg); }
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
<script src="/<?= $site ?>/js/datatable.js"></script>

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
        
        // Display results for the organism
        Object.keys(resultsByOrganism).forEach(organism => {
            const results = resultsByOrganism[organism];
            $('#resultsContainer').append(createOrganismResultsTable(organism, results));
        });
    }
}

function createOrganismResultsTable(organism, results) {
    const tableId = '#resultsTable_' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const selectId = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const genus = results[0]?.genus || '';
    const species = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    
    const organismDisplay = `<em>${genus} ${species}</em>`;
    const commonNameDisplay = commonName ? ` (${commonName})` : '';
    
    const imagePath = sitePath + '/images/';
    const imageFile = organism + '.jpg';
    const fallbackId = 'icon-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    const imageHtml = `<img src="${imagePath}${imageFile}" class="organism-thumbnail" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';" style="margin-right: 8px;">
                       <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`;
    
    const anchorId = 'results-' + organism.replace(/[^a-zA-Z0-9]/g, '_');
    
    // Check if this is a uniquename search (no annotation columns)
    const isUniquenameSearch = !results[0]?.annotation_source;
    
    let html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${imageHtml}${organismDisplay}${commonNameDisplay}
                <span class="badge bg-primary">${results.length} result${results.length !== 1 ? 's' : ''}</span>
            </h5>
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table id="${tableId.substring(1)}" class="table table-sm table-striped table-hover results-table" style="display: none; width: 100%; max-width: none;">
                    <thead>
                        <tr>
                            <th>Species</th>
                            <th>Type</th>
                            <th>Feature ID</th>
                            <th>Name</th>
                            <th>Description</th>`;
    
    if (!isUniquenameSearch) {
        html += `
                            <th>Annotation Source</th>
                            <th>Annotation ID</th>
                            <th>Annotation Description</th>`;
    }
    
    html += `
                        </tr>
                    </thead>
                    <tbody>`;
    
    results.forEach(result => {
        html += `
            <tr>
                <td>${result.genus} ${result.species}</td>
                <td>${result.feature_type}</td>
                <td><a href="${sitePath}/tools/display/parent.php?organism=${encodeURIComponent(organism)}&uniquename=${encodeURIComponent(result.feature_uniquename)}" target="_blank">${result.feature_uniquename}</a></td>
                <td>${result.feature_name}</td>
                <td>${result.feature_description}</td>`;
        
        if (!isUniquenameSearch) {
            html += `
                <td>${result.annotation_source}</td>
                <td>${result.annotation_accession}</td>
                <td>${result.annotation_description}</td>`;
        }
        
        html += `
            </tr>`;
    });
    
    html += `
                    </tbody>
                </table>
                <div class="loader" style="text-align: center; padding: 20px;">
                    <div class="loading-spinner"></div>
                    <p>Initializing table...</p>
                </div>
            </div>
        </div>
    `;
    
    setTimeout(() => initializeResultsTable(tableId, selectId), 100);
    
    return html;
}

function initializeResultsTable(tableId, selectId) {
    const originalThead = $(tableId + ' thead').clone();
    const columnTitles = [];
    $(tableId + ' thead tr th').each(function() {
        columnTitles.push($(this).text());
    });
    
    // Determine if this is a uniquename search (fewer columns)
    const isUniquenameSearch = columnTitles.length === 5; // Species, Type, Feature ID, Name, Description
    
    // Add checkbox column to header row
    $(tableId + ' thead tr').prepend('<th>Select</th>');
    
    // Add checkboxes to each row
    $(tableId + ' tbody tr').each(function() {
        $(this).prepend('<td><input type="checkbox" class="row-select"></td>');
    });
    
    // Create search row
    let searchRowHtml = '<tr>';
    searchRowHtml += '<th><button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' + selectId + '"><span>Select All</span></button></th>';
    columnTitles.forEach(function(title, index) {
        const colIndex = index + 1;
        searchRowHtml += '<th data-column-index="' + colIndex + '"><input style="text-align:center; border: solid 1px #808080; border-radius: 4px; width: 100%; max-width: 200px;" type="text" placeholder="Filter..." class="column-search" /></th>';
    });
    searchRowHtml += '</tr>';
    
    $(tableId + ' thead').prepend(searchRowHtml);
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Brtlpi',
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false,
        buttons: [
            { extend: 'copy', text: 'Copy', exportOptions: { columns: ':visible' } },
            { extend: 'csv', text: 'CSV', exportOptions: { columns: ':visible' } },
            { extend: 'excel', text: 'Excel', exportOptions: { columns: ':visible' } },
            { extend: 'pdf', text: 'PDF', exportOptions: { columns: ':visible' } },
            { extend: 'print', text: 'Print', exportOptions: { columns: ':visible' } },
            { extend: 'colvis', text: 'Column Visibility' }
        ],
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        columnDefs: isUniquenameSearch ? [
            { width: "80px", targets: 0, orderable: false },  // Select - not sortable
            { width: "150px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "80px", targets: 2 },  // Type
            { width: "180px", targets: 3 }, // Feature ID
            { width: "100px", targets: 4 }, // Name
            { width: "200px", targets: 5 }  // Description
        ] : [
            { width: "80px", targets: 0, orderable: false },  // Select - not sortable
            { width: "150px", targets: 1, visible: false }, // Species - hidden but included in exports
            { width: "80px", targets: 2 },  // Type
            { width: "180px", targets: 3 }, // Feature ID
            { width: "100px", targets: 4 }, // Name
            { width: "200px", targets: 5 }, // Description
            { width: "200px", targets: 6 }, // Annotation Source
            { width: "150px", targets: 7 }, // Annotation ID
            { width: "400px", targets: 8, className: "wrap-text" }  // Annotation Description (with wrapping)
        ],
        colReorder: true,
        retrieve: true,
        initComplete: function() {
            $(tableId).show();
            $(tableId).closest('.table-responsive').find('.loader').remove();
            
            const $scrollHead = $(tableId).closest('.dataTables_wrapper').find('.dataTables_scrollHead');
            const $scrollHeadTable = $scrollHead.find('table');
            
            $scrollHead.css({
                'display': 'block',
                'visibility': 'visible',
                'height': 'auto',
                'min-height': '80px',
                'overflow': 'visible'
            });
            
            $scrollHeadTable.css({
                'display': 'table',
                'visibility': 'visible',
                'height': 'auto'
            });
            
            $scrollHead.find('thead').css({
                'display': 'table-header-group',
                'visibility': 'visible',
                'height': 'auto'
            });
            
            $scrollHead.find('thead tr').css({
                'display': 'table-row',
                'visibility': 'visible',
                'height': 'auto'
            });
            
            $scrollHead.find('thead th').css({
                'display': 'table-cell',
                'visibility': 'visible',
                'height': 'auto',
                'line-height': 'normal',
                'padding': '8px'
            });
            
            setTimeout(() => {
                $scrollHead.find('thead tr:eq(0) th input').css({
                    'height': '32px',
                    'line-height': '1.2',
                    'padding': '4px 8px',
                    'font-size': '13px'
                });
                $scrollHead.find('thead tr:eq(0) th button').css({
                    'height': '32px',
                    'line-height': '1.2',
                    'padding': '4px 8px',
                    'font-size': '13px'
                });
                $scrollHead.find('thead tr:eq(0) th').css({
                    'height': '40px',
                    'padding': '4px'
                });
                $scrollHead.find('thead tr:eq(1) th').css({
                    'height': '36px',
                    'padding': '8px',
                    'text-align': 'center'
                });
            }, 100);
        }
    });
    
    // Add column search functionality
    $(tableId + ' thead tr:eq(0) th').each(function() {
        const $searchInput = $(this).find('input.column-search');
        if ($searchInput.length > 0) {
            const columnIndex = parseInt($(this).attr('data-column-index'));
            $searchInput.on('keyup change', function () {
                if (table.column(columnIndex).search() !== this.value) {
                    table.column(columnIndex).search(this.value).draw();
                }
            });
        }
    });
    
    // Select/Deselect all handler
    $('#toggle-select-btn' + selectId).on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        const allRows = table.rows().nodes();
        const checkedCount = $(allRows).find('input.row-select:checked').length;
        const totalCount = $(allRows).find('input.row-select').length;
        const allChecked = checkedCount === totalCount;
        
        if (allChecked) {
            $(allRows).find('input.row-select').prop('checked', false);
            $btn.find('span').text('Select All');
        } else {
            $(allRows).find('input.row-select').prop('checked', true);
            $btn.find('span').text('Deselect All');
        }
    });
    
    // Individual checkbox handler
    $(tableId).on('change', '.row-select', function () {
        const allRows = table.rows().nodes();
        const totalCheckboxes = $(allRows).find('input.row-select').length;
        const checkedCheckboxes = $(allRows).find('input.row-select:checked').length;
        const $btn = $('#toggle-select-btn' + selectId);
        
        if (checkedCheckboxes === totalCheckboxes) {
            $btn.find('span').text('Deselect All');
        } else {
            $btn.find('span').text('Select All');
        }
    });
}
</script>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>

<?php
include_once __DIR__ . '/../../access_control.php';

// Get the group name from query parameter
$group_name = $_GET['group'] ?? '';

if (empty($group_name)) {
    header("Location: /moop/index.php");
    exit;
}

// Load group descriptions
$group_descriptions_file = "$organism_data/group_descriptions.json";
$group_descriptions = [];
if (file_exists($group_descriptions_file)) {
    $group_descriptions = json_decode(file_get_contents($group_descriptions_file), true);
}

// Load organism assembly groups
$groups_file = "$organism_data/organism_assembly_groups.json";
$groups_data = [];
if (file_exists($groups_file)) {
    $groups_data = json_decode(file_get_contents($groups_file), true);
}

// Find the description for this group
$group_info = null;
foreach ($group_descriptions as $desc) {
    if ($desc['group_name'] === $group_name && ($desc['in_use'] ?? false)) {
        $group_info = $desc;
        break;
    }
}

// Find all organisms that belong to this group
$group_organisms = [];
foreach ($groups_data as $data) {
    if (in_array($group_name, $data['groups'])) {
        $organism = $data['organism'];
        $assembly = $data['assembly'];
        
        if (!isset($group_organisms[$organism])) {
            $group_organisms[$organism] = [];
        }
        $group_organisms[$organism][] = $assembly;
    }
}

// Sort organisms alphabetically
ksort($group_organisms);

// Access control: Check if user has access to this group
// Public group is accessible to everyone, others require proper access
if ($group_name !== 'Public') {
    if (!has_access('Collaborator', $group_name)) {
        header("Location: /$site/access_denied.php");
        exit;
    }
}

include_once realpath(__DIR__ . '/../../header.php');
include_once realpath(__DIR__ . '/../../toolbar.php');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?= htmlspecialchars($group_name) ?> - <?= $siteTitle ?></title>
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
  <div class="mb-3">
    <a href="/<?= $site ?>/index.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to Home</a>
    <button id="backToGroupBtn" class="btn btn-secondary ms-2" style="display: none;" onclick="location.reload();">
      <i class="fa fa-arrow-left"></i> Back to <?= htmlspecialchars($group_name) ?>
    </button>
  </div>

  <div class="text-center mb-4">
    <h1 class="fw-bold"><i class="fa fa-layer-group"></i> <?= htmlspecialchars($group_name) ?></h1>
  </div>

  <!-- Search Section -->
  <div class="card shadow-sm mb-5">
    <div class="card-header bg-primary text-white">
      <h4 class="mb-0"><i class="fa fa-search"></i> <?= htmlspecialchars($group_name) ?>: Search Gene IDs and Annotations</h4>
    </div>
    <div class="card-body" style="background-color: rgba(13, 110, 253, 0.08);">
      <form id="groupSearchForm">
        <div class="row">
          <div class="col-md-10">
            <input type="text" class="form-control" id="searchKeywords" placeholder="Enter gene ID or annotation keywords (minimum 3 characters)..." required>
            <small class="form-text" style="color: #999;">
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

  <!-- Search Results Section -->
  <div id="searchResults" style="display: none;">
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
  <div class="mb-5" id="organismsSection">
    <h2 class="fw-bold mb-4"><i class="fa fa-dna"></i> Organisms in <?= htmlspecialchars($group_name) ?> Group</h2>
    
    <?php if (empty($group_organisms)): ?>
      <div class="alert alert-info">
        <i class="fa fa-info-circle"></i> No organisms are currently available in this group.
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($group_organisms as $organism => $assemblies): ?>
          <?php
            $organism_json_path = "$organism_data/$organism/organism.json";
            $organism_info = [];
            if (file_exists($organism_json_path)) {
              $organism_info = json_decode(file_get_contents($organism_json_path), true);
            }
            $genus = $organism_info['genus'] ?? '';
            $species = $organism_info['species'] ?? '';
            $common_name = $organism_info['common_name'] ?? '';
            $image_file = "$organism.jpg";
          ?>
          <div class="col-md-6 col-lg-4">
            <a href="/<?= $site ?>/tools/display/organism_display.php?organism=<?= urlencode($organism) ?>" 
               class="text-decoration-none">
              <div class="card h-100 shadow-sm organism-card">
                <div class="card-body text-center">
                  <div class="organism-image-container mb-3">
                    <img src="/<?= $site ?>/images/<?= htmlspecialchars($image_file) ?>" 
                         alt="<?= htmlspecialchars($organism) ?>"
                         class="organism-card-image"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="organism-card-icon" style="display: none;">
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

<style>
  .organism-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.1);
    cursor: pointer;
  }
  
  .organism-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.15) !important;
  }
  
  .organism-image-container {
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }
  
  .organism-card-image {
    max-width: 100%;
    max-height: 150px;
    object-fit: cover;
    border-radius: 8px;
  }
  
  .organism-card-icon {
    height: 150px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .organism-card .card-title {
    color: #2c3e50;
    font-size: 1.1rem;
  }
  
  .organism-card:hover .card-title {
    color: #007bff;
  }
  
  .search-progress-bar {
    height: 20px;
    background: #e9ecef;
    border-radius: 5px;
    overflow: hidden;
  }
  
  .search-progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #007bff, #0056b3);
    transition: width 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 12px;
  }
  
  .organism-results {
    margin-bottom: 20px;
  }
  
  .organism-thumbnail {
    height: 40px;
    width: 40px;
    object-fit: cover;
    border-radius: 4px;
    vertical-align: middle;
  }
  
  .jump-link {
    text-decoration: none;
    color: #0d6efd;
    padding: 2px 6px;
    border-radius: 3px;
    transition: background-color 0.2s;
  }
  
  .jump-link:hover {
    background-color: #e7f1ff;
    text-decoration: none;
  }
  
  .jump-link .badge {
    margin-left: 4px;
  }
  
  .loading-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 3px solid rgba(0,123,255,.3);
    border-radius: 50%;
    border-top-color: #007bff;
    animation: spinner .6s linear infinite;
  }
  
  @keyframes spinner {
    to {transform: rotate(360deg);}
  }
  
  /* DataTables Buttons styling */
  .dt-buttons {
    margin-bottom: 10px;
  }
  
  .dt-button {
    margin-right: 5px;
    margin-bottom: 5px;
  }
  
  .dataTables_wrapper .dataTables_filter input {
    border: 1px solid #ced4da;
    border-radius: 4px;
    padding: 5px 10px;
  }
  
  .results-table tbody tr.selected {
    background-color: #e7f3ff !important;
  }
  
  .results-table thead tr:first-child input[type="text"] {
    font-size: 12px;
    padding: 6px 8px;
    line-height: 1.2;
    height: 32px;
    box-sizing: border-box;
  }
  
  .results-table thead tr:first-child input[type="text"]::placeholder {
    text-align: center;
  }
  
  .results-table thead tr:first-child th {
    height: auto !important;
    padding: 4px !important;
    vertical-align: middle !important;
  }
  
  /* Fix sorting arrows - remove DataTables default sorting icons from search row */
  .results-table thead tr:first-child th.sorting::before,
  .results-table thead tr:first-child th.sorting::after,
  .results-table thead tr:first-child th.sorting_asc::before,
  .results-table thead tr:first-child th.sorting_asc::after,
  .results-table thead tr:first-child th.sorting_desc::before,
  .results-table thead tr:first-child th.sorting_desc::after {
    display: none !important;
  }
  
  /* Remove sorting arrows from Select column (first column in second row) */
  .results-table thead tr:nth-child(2) th:first-child::before,
  .results-table thead tr:nth-child(2) th:first-child::after {
    display: none !important;
  }
  
  .results-table thead tr:nth-child(2) th:first-child {
    cursor: default !important;
  }
  
  /* Keep sorting arrows only on label row (second row) */
  .results-table thead tr:nth-child(2) th.sorting::before,
  .results-table thead tr:nth-child(2) th.sorting::after,
  .results-table thead tr:nth-child(2) th.sorting_asc::before,
  .results-table thead tr:nth-child(2) th.sorting_asc::after,
  .results-table thead tr:nth-child(2) th.sorting_desc::before,
  .results-table thead tr:nth-child(2) th.sorting_desc::after {
    display: inline-block !important;
  }
  
  /* Fix spacing for DataTables sorting arrows - only in label row */
  .results-table thead tr:nth-child(2) th.sorting::before,
  .results-table thead tr:nth-child(2) th.sorting_asc::before,
  .results-table thead tr:nth-child(2) th.sorting_desc::before {
    right: 1em !important;
    content: "↑" !important;
    opacity: 0.3;
  }
  
  .results-table thead tr:nth-child(2) th.sorting::after,
  .results-table thead tr:nth-child(2) th.sorting_asc::after,
  .results-table thead tr:nth-child(2) th.sorting_desc::after {
    right: 0.5em !important;
    content: "↓" !important;
    opacity: 0.3;
  }
  
  .results-table thead tr:nth-child(2) th.sorting_asc::before {
    opacity: 1 !important;
  }
  
  .results-table thead tr:nth-child(2) th.sorting_desc::after {
    opacity: 1 !important;
  }
  
  /* Add padding to header cells to make room for arrows */
  .results-table thead tr:nth-child(2) th {
    padding-right: 2.5em !important;
    position: relative;
  }
  
  .btn_select_all {
    font-size: 12px;
    background-color: #f8f9fa;
  }
  
  .btn_select_all:hover {
    background-color: #e9ecef;
  }
  
  /* Force header rows to display */
  .results-table thead tr {
    display: table-row !important;
  }
  
  .results-table thead tr th {
    display: table-cell !important;
    font-weight: bold;
    background-color: #f8f9fa !important;
    border-bottom: 2px solid #dee2e6 !important;
    padding: 8px !important;
    color: #212529 !important;
    text-align: center !important;
    vertical-align: middle !important;
  }
  
  /* Label row styling (second row) */
  .results-table thead tr:nth-child(2) th {
    background-color: #e9ecef !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    text-align: center !important;
  }
  
  /* Search row styling (first row) */
  .results-table thead tr:first-child th {
    background-color: #ffffff !important;
    border-bottom: 1px solid #dee2e6 !important;
    padding: 5px !important;
  }
  
  /* Override any DataTables CSS that might hide headers */
  table.dataTable thead {
    display: table-header-group !important;
  }
  
  table.dataTable thead tr {
    display: table-row !important;
    visibility: visible !important;
    height: auto !important;
  }
  
  table.dataTable thead th {
    display: table-cell !important;
    visibility: visible !important;
    height: auto !important;
    min-height: 30px !important;
  }
  
  /* Table scrolling and width */
  .dataTables_wrapper {
    width: 100%;
    overflow-x: auto;
  }
  
  .table-responsive {
    overflow-x: auto !important;
  }
  
  .results-table {
    width: 100% !important;
  }
  
  .dataTables_scroll {
    overflow-x: auto !important;
  }
  
  .dataTables_scrollHead {
    overflow: visible !important;
    width: 100% !important;
  }
  
  .dataTables_scrollHeadInner {
    width: 100% !important;
  }
  
  .dataTables_scrollHeadInner table {
    width: 100% !important;
  }
  
  .dataTables_scrollBody {
    overflow-x: auto !important;
  }
  
  /* Force header visibility when scrollX is enabled */
  .dataTables_scrollHead table thead {
    display: table-header-group !important;
  }
  
  .dataTables_scrollHead table thead tr {
    display: table-row !important;
  }
  
  .dataTables_scrollHead table thead th {
    display: table-cell !important;
  }
  
  /* Prevent other columns from wrapping */
  .results-table tbody td {
    white-space: nowrap;
  }
  
  /* Allow wrapping in Description columns with width limits */
  .results-table tbody td:nth-child(6) {  /* Description column */
    white-space: normal !important;
    word-wrap: break-word;
    word-break: break-word;
    max-width: 200px;
    min-width: 150px;
  }
  
  /* Annotation Description column - last column */
  .results-table tbody td:last-child {
    white-space: normal !important;
    word-wrap: break-word !important;
    word-break: break-word !important;
    max-width: 400px !important;
    min-width: 350px !important;
    width: 400px !important;
  }
  
  /* Force width on Annotation Description column header */
  .results-table thead th:last-child {
    min-width: 350px !important;
    max-width: 400px !important;
    width: 400px !important;
  }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
<script>
const groupOrganisms = <?= json_encode(array_keys($group_organisms)) ?>;
const groupName = <?= json_encode($group_name) ?>;
const sitePath = '/<?= $site ?>';

let allResults = [];
let searchedOrganisms = 0;
let totalOrganisms = groupOrganisms.length;

$('#groupSearchForm').on('submit', function(e) {
    e.preventDefault();
    
    const keywords = $('#searchKeywords').val().trim();
    
    // Validate input
    if (keywords.length < 3) {
        alert('Please enter at least 3 characters to search');
        return;
    }
    
    // Check for quoted search
    const quotedSearch = /^".+"$/.test(keywords);
    
    // Hide group description and organisms sections on first search
    if ($('#searchResults').is(':hidden')) {
        $('#groupDescription').slideUp();
        $('#organismsSection').slideUp();
        $('#backToGroupBtn').fadeIn();
    }
    
    // Reset and show results section
    allResults = [];
    searchedOrganisms = 0;
    $('#searchResults').show();
    $('#resultsContainer').html('');
    $('#searchInfo').html(`Searching for: <strong>${keywords}</strong> across ${totalOrganisms} organisms in ${groupName}`);
    
    // Show progress bar
    $('#searchProgress').html(`
        <div class="search-progress-bar">
            <div class="search-progress-fill" id="progressFill" style="width: 0%">0%</div>
        </div>
        <small class="text-muted mt-2 d-block" id="progressText">Starting search...</small>
    `);
    
    // Disable search button
    $('#searchBtn').prop('disabled', true).html('<span class="loading-spinner"></span> Searching...');
    
    // Search each organism sequentially
    searchNextOrganism(keywords, quotedSearch, 0);
});

function searchNextOrganism(keywords, quotedSearch, index) {
    if (index >= totalOrganisms) {
        // All searches complete
        finishSearch();
        return;
    }
    
    const organism = groupOrganisms[index];
    $('#progressText').html(`Searching ${organism}... (${index + 1}/${totalOrganisms})`);
    
    $.ajax({
        url: sitePath + '/tools/search/annotation_search_ajax.php',
        method: 'GET',
        data: {
            search_keywords: keywords,
            organism: organism,
            group: groupName,
            quoted: quotedSearch ? '1' : '0'
        },
        dataType: 'json',
        success: function(data) {
            if (data.results && data.results.length > 0) {
                allResults = allResults.concat(data.results);
                displayOrganismResults(data);
            }
            
            searchedOrganisms++;
            const progress = Math.round((searchedOrganisms / totalOrganisms) * 100);
            $('#progressFill').css('width', progress + '%').text(progress + '%');
            
            // Search next organism
            searchNextOrganism(keywords, quotedSearch, index + 1);
        },
        error: function(xhr, status, error) {
            console.error('Search error for ' + organism + ':', error);
            console.error('Response text:', xhr.responseText.substring(0, 500));
            searchedOrganisms++;
            searchNextOrganism(keywords, quotedSearch, index + 1);
        }
    });
}

function displayOrganismResults(data) {
    const organism = data.organism;
    const results = data.results;
    const uniquenameSearch = results[0]?.uniquename_search;
    const safeOrganism = organism.replace(/[^a-zA-Z0-9]/g, '_');
    const tableId = `resultsTable_${safeOrganism}`;
    
    // Get organism display info from first result
    const genus = results[0]?.genus || '';
    const species = results[0]?.species || '';
    const commonName = results[0]?.common_name || '';
    
    // Format organism name: italicized genus species
    const organismDisplay = `<em>${genus} ${species}</em>`;
    const commonNameDisplay = commonName ? ` (${commonName})` : '';
    
    // Get organism image or use DNA icon fallback
    const imagePath = sitePath + '/images/';
    const imageFile = organism + '.jpg';
    const fallbackId = 'icon-' + safeOrganism;
    const imageHtml = `<img src="${imagePath}${imageFile}" class="organism-thumbnail" onerror="this.style.display='none'; document.getElementById('${fallbackId}').style.display='inline';" onload="document.getElementById('${fallbackId}').style.display='none';" style="margin-right: 8px;">
                       <i class="fa fa-dna" id="${fallbackId}" style="margin-right: 8px; display: none;"></i>`;
    
    const anchorId = 'results-' + safeOrganism;
    const organismUrl = sitePath + '/tools/display/organism_display.php?organism=' + encodeURIComponent(organism);
    
    let html = `
        <div class="organism-results" id="${anchorId}">
            <h5>${imageHtml}${organismDisplay}${commonNameDisplay}
                <span class="badge bg-primary">${results.length} result${results.length !== 1 ? 's' : ''}</span>
                <a href="${organismUrl}" class="btn btn-sm btn-outline-primary ms-2" style="font-size: 0.8rem;">
                    <i class="fa fa-info-circle"></i> Read More
                </a>
            </h5>
            <div class="table-responsive" style="overflow-x: auto; width: 100%;">
                <table id="${tableId}" class="table table-sm table-striped table-hover results-table" style="width:100%; font-size: 14px;">
                    <thead>
                        <tr>
                            <th></th>
                            <th data-column-index="1"></th>
                            <th data-column-index="2"></th>
                            <th data-column-index="3"></th>
                            <th data-column-index="4"></th>
                            <th data-column-index="5"></th>`;
    
    if (!uniquenameSearch) {
        html += `
                            <th data-column-index="6"></th>
                            <th data-column-index="7"></th>
                            <th data-column-index="8"></th>`;
    }
    
    html += `
                        </tr>
                        <tr>
                            <th style="width: 80px;">Select</th>
                            <th style="width: 150px;">Species</th>
                            <th style="width: 80px;">Type</th>
                            <th style="width: 180px;">Feature ID</th>
                            <th style="width: 100px;">Name</th>
                            <th style="width: 200px;">Description</th>`;
    
    if (!uniquenameSearch) {
        html += `
                            <th style="width: 200px;">Annotation Source</th>
                            <th style="width: 150px;">Annotation ID</th>
                            <th style="width: 400px;">Annotation Description</th>`;
    }
    
    html += `
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    // Add data rows
    results.forEach(function(row) {
        html += `<tr>
                <td><input type="checkbox" class="row-select"></td>
                <td><em>${row.genus} ${row.species}</em><br><small class="text-muted">${row.common_name}</small></td>
                <td>${row.feature_type}</td>
                <td><a href="${sitePath}/tools/search/parent.php?name=${encodeURIComponent(row.feature_uniquename)}" target="_blank">${row.feature_uniquename}</a></td>
                <td>${row.feature_name}</td>
                <td>${row.feature_description}</td>`;
        
        if (!uniquenameSearch) {
            html += `
                <td>${row.annotation_source}</td>
                <td>${row.annotation_accession}</td>
                <td>${row.annotation_description}</td>`;
        }
        
        html += `</tr>`;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    $('#resultsContainer').append(html);
    
    // Initialize datatable with export functionality
    initializeResultTable(`#${tableId}`, safeOrganism, uniquenameSearch);
}

function initializeResultTable(tableId, selectId, isUniquenameSearch) {
    // Populate the first row (filter row) with Select All button and filter inputs
    $(tableId + ' thead tr:eq(0) th').each(function(i) {
        const columnIndex = $(this).data('column-index');
        if (i === 0) {
            // Select All button for first column
            $(this).html('<button style="width:110px; border-radius: 4px; white-space: nowrap; border: solid 1px #808080; padding: 0;" class="btn btn_select_all" id="toggle-select-btn' + selectId + '"><span>Select All</span></button>');
        } else if (columnIndex !== undefined) {
            $(this).html('<input style="text-align:center; border: solid 1px #808080; border-radius: 4px; width: 100%; max-width: 200px;" type="text" placeholder="Filter..." class="column-search">');
        }
    });
    
    // Initialize DataTable
    const table = $(tableId).DataTable({
        dom: 'Brtlpi', // Removed 'f' to hide the global filter
        pageLength: 25,
        stateSave: false,
        orderCellsTop: false, // Use bottom (label) row for sorting, not search row
        buttons: [
            {
                extend: 'copy',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: ':visible'
                }
            },
            {
                extend: 'csv',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8] // Include Species column (1) in exports
                }
            },
            {
                extend: 'excel',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8] // Include Species column (1) in exports
                }
            },
            {
                extend: 'pdf',
                orientation: 'landscape',
                pageSize: 'LEGAL',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8] // Include Species column (1) in exports
                }
            },
            {
                extend: 'print',
                exportOptions: { 
                    rows: function (idx, data, node) {
                        return $(node).find('input.row-select').is(':checked');
                    },
                    columns: isUniquenameSearch ? [1, 2, 3, 4, 5] : [1, 2, 3, 4, 5, 6, 7, 8] // Include Species column (1) in exports
                }
            },
            'colvis'
        ],
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
        scrollX: false,
        scrollCollapse: false,
        autoWidth: false,
        fixedHeader: false,
        initComplete: function() {
            // Force remove sorting classes from Select column
            const selectHeader = $(tableId + ' thead tr:nth-child(2) th:first-child');
            selectHeader.removeClass('sorting sorting_asc sorting_desc');
            
            // Set up column filtering
            $(tableId + ' thead tr:eq(0) th').each(function(i) {
                const columnIndex = $(this).data('column-index');
                if (columnIndex !== undefined) {
                    $('input.column-search', this).on('keyup change', function() {
                        if (table.column(columnIndex).search() !== this.value) {
                            table.column(columnIndex).search(this.value).draw();
                        }
                    });
                }
            });
        }
    });
    
    // Select/Deselect all handler - works across ALL pages
    $('#toggle-select-btn' + selectId).on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        
        // Check all rows using DataTables API (includes all pages)
        const allRows = table.rows().nodes();
        const checkedCount = $(allRows).find('input.row-select:checked').length;
        const totalCount = $(allRows).find('input.row-select').length;
        const allChecked = checkedCount === totalCount;
        
        if (allChecked) {
            // Deselect all
            $(allRows).find('input.row-select').prop('checked', false);
            $btn.find('span').text('Select All');
        } else {
            // Select all (across all pages)
            $(allRows).find('input.row-select').prop('checked', true);
            $btn.find('span').text('Deselect All');
        }
    });
    
    // Individual checkbox handler - checks across all pages
    $(tableId).on('change', '.row-select', function () {
        const allRows = table.rows().nodes();
        const totalCheckboxes = $(allRows).find('input.row-select').length;
        const checkedCheckboxes = $(allRows).find('input.row-select:checked').length;
        
        if (checkedCheckboxes === totalCheckboxes) {
            $('#toggle-select-btn' + selectId + ' span').text('Deselect All');
        } else {
            $('#toggle-select-btn' + selectId + ' span').text('Select All');
        }
    });
}

function finishSearch() {
    $('#searchBtn').prop('disabled', false).html('<i class="fa fa-search"></i> Search');
    
    if (allResults.length === 0) {
        $('#searchProgress').html('<div class="alert alert-warning">No results found. Try different search terms.</div>');
    } else {
        // Build jump-to navigation
        let jumpToHtml = '<div class="alert alert-info mb-3"><strong>Jump to results for:</strong> ';
        const organismCounts = {};
        allResults.forEach(r => {
            if (!organismCounts[r.organism]) {
                organismCounts[r.organism] = 0;
            }
            organismCounts[r.organism]++;
        });
        
        Object.keys(organismCounts).forEach((org, idx) => {
            const anchorId = 'results-' + org.replace(/[^a-zA-Z0-9]/g, '_');
            const genus = allResults.find(r => r.organism === org)?.genus || '';
            const species = allResults.find(r => r.organism === org)?.species || '';
            if (idx > 0) jumpToHtml += ' | ';
            jumpToHtml += `<a href="#${anchorId}" class="jump-link"><em>${genus} ${species}</em> <span class="badge bg-secondary">${organismCounts[org]}</span></a>`;
        });
        jumpToHtml += '</div>';
        
        $('#searchProgress').html(`
            <div class="alert alert-success">
                <i class="fa fa-check-circle"></i> <strong>Search complete!</strong> Found ${allResults.length} total result${allResults.length !== 1 ? 's' : ''} across ${searchedOrganisms} organisms.
                <hr class="my-2">
                <small>
                    <strong>Filter:</strong> Use the input boxes above each column header to filter results.<br>
                    <strong>Sort:</strong> Click column headers to sort ascending/descending.<br>
                    <strong>Export:</strong> Select rows with checkboxes, then click export buttons (Copy, CSV, Excel, PDF, Print) to download selected data.<br>
                    <strong>Columns:</strong> Use "Column Visibility" button to show/hide columns.
                </small>
            </div>
            ${jumpToHtml}
        `);
    }
}
</script>

</body>
</html>

<?php
include_once __DIR__ . '/../../footer.php';
?>

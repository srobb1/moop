<?php
session_start();
include_once 'admin_access_check.php';
include_once __DIR__ . '/../site_config.php';
include_once __DIR__ . '/../tools/moop_functions.php';

// Handle AJAX fix permissions request
if (isset($_POST['action']) && $_POST['action'] === 'fix_permissions' && isset($_POST['organism'])) {
    header('Content-Type: application/json');
    
    $organism = $_POST['organism'];
    $all_organisms = get_all_organisms_info();
    
    if (!isset($all_organisms[$organism]) || !$all_organisms[$organism]['db_file']) {
        echo json_encode(['success' => false, 'message' => 'Organism or database not found']);
        exit;
    }
    
    $db_file = $all_organisms[$organism]['db_file'];
    $result = fixDatabasePermissions($db_file);
    
    echo json_encode($result);
    exit;
}

// Handle AJAX rename assembly directory request
if (isset($_POST['action']) && $_POST['action'] === 'rename_assembly' && isset($_POST['organism']) && isset($_POST['old_name']) && isset($_POST['new_name'])) {
    header('Content-Type: application/json');
    
    $organism = $_POST['organism'];
    $old_name = $_POST['old_name'];
    $new_name = $_POST['new_name'];
    
    $all_organisms = get_all_organisms_info();
    
    if (!isset($all_organisms[$organism])) {
        echo json_encode(['success' => false, 'message' => 'Organism not found']);
        exit;
    }
    
    $organism_dir = $all_organisms[$organism]['path'];
    $result = renameAssemblyDirectory($organism_dir, $old_name, $new_name);
    
    echo json_encode($result);
    exit;
}

// Handle AJAX delete assembly directory request
if (isset($_POST['action']) && $_POST['action'] === 'delete_assembly' && isset($_POST['organism']) && isset($_POST['dir_name'])) {
    header('Content-Type: application/json');
    
    $organism = $_POST['organism'];
    $dir_name = $_POST['dir_name'];
    
    $all_organisms = get_all_organisms_info();
    
    if (!isset($all_organisms[$organism])) {
        echo json_encode(['success' => false, 'message' => 'Organism not found']);
        exit;
    }
    
    $organism_dir = $all_organisms[$organism]['path'];
    $result = deleteAssemblyDirectory($organism_dir, $dir_name);
    
    echo json_encode($result);
    exit;
}

// Get all organisms
function get_all_organisms_info() {
    global $organism_data, $sequence_types;
    $organisms_info = [];
    
    if (!is_dir($organism_data)) {
        return $organisms_info;
    }
    
    $organisms = scandir($organism_data);
    foreach ($organisms as $organism) {
        if ($organism[0] === '.' || !is_dir("$organism_data/$organism")) {
            continue;
        }
        
        // Get organism.json info if exists
        $organism_json = "$organism_data/$organism/organism.json";
        $info = [];
        if (file_exists($organism_json)) {
            $json_data = json_decode(file_get_contents($organism_json), true);
            if ($json_data) {
                // Handle wrapped JSON
                if (!isset($json_data['genus']) && !isset($json_data['common_name'])) {
                    $keys = array_keys($json_data);
                    if (count($keys) > 0 && is_array($json_data[$keys[0]])) {
                        $json_data = $json_data[$keys[0]];
                    }
                }
                $info = $json_data;
            }
        }
        
        // Get assemblies
        $assemblies = [];
        $assembly_path = "$organism_data/$organism";
        $files = scandir($assembly_path);
        foreach ($files as $file) {
            if ($file[0] === '.' || !is_dir("$assembly_path/$file")) {
                continue;
            }
            $assemblies[] = $file;
        }
        
        // Check for database file
        $db_file = null;
        if (file_exists("$organism_data/$organism/genes.sqlite")) {
            $db_file = "$organism_data/$organism/genes.sqlite";
        } elseif (file_exists("$organism_data/$organism/$organism.genes.sqlite")) {
            $db_file = "$organism_data/$organism/$organism.genes.sqlite";
        }
        
        $has_db = !is_null($db_file);
        
        // Validate database integrity if database exists
        $db_validation = null;
        $assembly_validation = null;
        $fasta_validation = null;
        if ($has_db) {
            $db_validation = validateDatabaseIntegrity($db_file);
            // Also validate assembly directories
            $assembly_validation = validateAssemblyDirectories($db_file, "$organism_data/$organism");
        }
        // Validate FASTA files in assembly directories
        $fasta_validation = validateAssemblyFastaFiles("$organism_data/$organism", $sequence_types);
        
        $organisms_info[$organism] = [
            'info' => $info,
            'assemblies' => $assemblies,
            'has_db' => $has_db,
            'db_file' => $db_file,
            'db_validation' => $db_validation,
            'assembly_validation' => $assembly_validation,
            'fasta_validation' => $fasta_validation,
            'path' => "$organism_data/$organism"
        ];
    }
    
    return $organisms_info;
}

$organisms = get_all_organisms_info();

include_once '../header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Organisms</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
  <style>
    .structure-box {
      background: #f8f9fa;
      border-left: 4px solid #007bff;
      padding: 15px;
      font-family: monospace;
      font-size: 0.9em;
    }
    .file-icon { color: #6c757d; }
    .folder-icon { color: #ffc107; }
    .db-icon { color: #28a745; }
    .status-badge { font-size: 0.75rem; }
  </style>
</head>
<body class="bg-light">

<div class="container-fluid mt-5">
  <h2><i class="fa fa-dna"></i> Manage Organisms</h2>
  
  <div class="mb-3">
    <a href="index.php" class="btn btn-secondary">← Back to Admin Tools</a>
  </div>

  <!-- Information Panel -->
  <div class="card mb-4">
    <div class="card-header bg-info text-white">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> Organism Data Management</h5>
    </div>
    <div class="card-body">
      <p>Organisms and genome assemblies are managed by creating or uploading directories to the organisms data folder. Each organism follows a specific directory structure.</p>
      
      <div class="row">
        <div class="col-md-6">
          <h6 class="fw-bold">Required Structure:</h6>
          <div class="structure-box">
            <i class="fa fa-folder folder-icon"></i> <strong>Genus_species</strong> (e.g., Anoura_caudifer)<br>
            &nbsp;&nbsp;<i class="fa fa-database db-icon"></i> genes.sqlite or Genus_species.genes.sqlite<br>
            &nbsp;&nbsp;<i class="fa fa-file file-icon"></i> organism.json<br>
            &nbsp;&nbsp;<i class="fa fa-folder folder-icon"></i> <strong>assembly_name</strong> (e.g., GCA_004027475.1)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.cds.nt.fa (coding sequences)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.protein.aa.fa (proteins)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.transcript.nt.fa (transcripts)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.genome.nt.fa (optional)
          </div>
        </div>
        
        <div class="col-md-6">
          <h6 class="fw-bold">Naming Conventions:</h6>
          <ul class="mb-0">
            <li><strong>Organism Directory:</strong> Genus_species_subspecies (underscores separate components)</li>
            <li><strong>Assembly Directory:</strong> Unique assembly identifier (e.g., GCA_004027475.1, assembly_v1)</li>
            <li><strong>Database File:</strong> genes.sqlite or Genus_species.genes.sqlite</li>
            <li><strong>Organism metadata file:</strong> organism.json</li>
          </ul>
          
          <div class="mt-3">
            <h6 class="fw-bold">
              <a class="text-decoration-none" data-bs-toggle="collapse" href="#jsonExample" role="button" aria-expanded="false" aria-controls="jsonExample">
                Click to view example organism.json <i class="fa fa-chevron-down"></i>
              </a>
            </h6>
            <div class="collapse" id="jsonExample">
              <pre class="bg-light p-2 rounded" style="font-size: 0.85em;">{
  "genus": "Anoura",
  "species": "caudifer",
  "common_name": "Tailed tailless bat",
  "taxon_id": "9999",
  "images": [
    {
      "file": "Anoura_caudifer.jpg",
      "caption": "Image of Tailed tailless bat"
    }
  ],
  "text_html": {
    "p": [
      "Diet: Feeds on nectar and insects...",
      "Fun Fact: Uses echolocation..."
    ]
  }
}</pre>
              <small class="text-muted"><strong>Note:</strong> Images should be placed in <code>/moop/images/</code></small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="alert alert-warning mt-3 mb-0">
        <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Data must be uploaded directly to the server at: <code><?= htmlspecialchars($organism_data) ?></code>
      </div>
    </div>
  </div>

  <!-- Current Organisms Table -->
  <div class="card">
    <div class="card-header bg-primary text-white">
      <h5 class="mb-0"><i class="fa fa-list"></i> Current Organisms (<?= count($organisms) ?>)</h5>
    </div>
    <div class="card-body">
      <table id="organismsTable" class="table table-striped table-hover">
         <thead>
           <tr>
             <th>Organism</th>
             <th>Common Name</th>
             <th>Assemblies</th>
             <th>DB Status</th>
             <th>Status</th>
             <th>Path</th>
           </tr>
         </thead>
         <tbody>
           <?php foreach ($organisms as $organism => $data): ?>
             <tr>
               <td>
                 <strong><?= htmlspecialchars($organism) ?></strong>
                 <?php if (isset($data['info']['genus']) && isset($data['info']['species'])): ?>
                   <br><small class="text-muted"><em><?= htmlspecialchars($data['info']['genus']) ?> <?= htmlspecialchars($data['info']['species']) ?></em></small>
                 <?php endif; ?>
               </td>
               <td>
                 <?php 
                   if (isset($data['info']['common_name'])) {
                       echo htmlspecialchars($data['info']['common_name']);
                   } else {
                       echo '<span class="text-muted">-</span>';
                   }
                 ?>
               </td>
               <td>
                 <span class="badge bg-secondary"><?= count($data['assemblies']) ?> assemblies</span>
                 <?php if (!empty($data['assemblies'])): ?>
                   <div class="mt-1">
                     <?php foreach ($data['assemblies'] as $assembly): ?>
                       <?php 
                         $safe_asm_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism . '_' . $assembly);
                         $asm_fasta = $data['fasta_validation']['assemblies'][$assembly] ?? null;
                         $is_missing = isset($data['fasta_validation']['missing_files'][$assembly]);
                         
                         // Check if assembly directory name matches database
                         $has_name_mismatch = false;
                         $assembly_validation = $data['assembly_validation'];
                         if ($assembly_validation) {
                           $matching = false;
                           foreach ($assembly_validation['genomes'] as $genome) {
                             if ($assembly === $genome['genome_name'] || $assembly === $genome['genome_accession']) {
                               $matching = true;
                               break;
                             }
                           }
                           $has_name_mismatch = !$matching;
                         }
                         
                         // Determine badge style (danger/red for name mismatch priority, warning/orange for missing files)
                         $badge_class = 'bg-success';
                         $badge_text = 'Complete';
                         if ($has_name_mismatch) {
                             $badge_class = 'bg-warning';
                             $badge_text = 'Name Mismatch';
                         } elseif ($is_missing) {
                             $badge_class = 'bg-info';
                             $badge_text = 'Missing Files';
                         }
                       ?>
                       <button class="btn btn-sm d-block w-100 text-start mb-1 <?= $badge_class ?> text-white" data-bs-toggle="modal" data-bs-target="#asmModal<?= htmlspecialchars($safe_asm_id) ?>">
                         <i class="fa fa-folder"></i> <?= htmlspecialchars($assembly) ?> <span class="float-end"><?= $badge_text ?></span>
                       </button>
                     <?php endforeach; ?>
                   </div>
                 <?php endif; ?>
               </td>
               <td>
                 <?php if ($data['db_validation']): 
                     $validation = $data['db_validation'];
                     $asm_validation = $data['assembly_validation'];
                     
                     // Check if there are assembly issues
                     $has_assembly_issues = $asm_validation && (!$asm_validation['valid'] || !empty($asm_validation['mismatches']));
                     
                     if ($validation['readable'] && $validation['database_valid'] && !empty($validation['tables_present']) && !$has_assembly_issues): ?>
                       <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#dbModal<?= htmlspecialchars($organism) ?>">
                         <i class="fa fa-check-circle"></i> Ready
                       </button>
                     <?php elseif ($validation['readable'] && $validation['database_valid'] && !empty($validation['tables_present']) && $has_assembly_issues): ?>
                       <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#dbModal<?= htmlspecialchars($organism) ?>">
                         <i class="fa fa-exclamation-triangle"></i> Incomplete
                       </button>
                     <?php elseif (!$validation['readable']): ?>
                       <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#dbModal<?= htmlspecialchars($organism) ?>">
                         <i class="fa fa-lock"></i> Unreadable
                       </button>
                     <?php elseif (!$validation['database_valid']): ?>
                       <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#dbModal<?= htmlspecialchars($organism) ?>">
                         <i class="fa fa-times-circle"></i> Invalid
                       </button>
                       </button>
                     <?php else: ?>
                       <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#dbModal<?= htmlspecialchars($organism) ?>">
                         <i class="fa fa-exclamation-triangle"></i> Issues
                       </button>
                     <?php endif; ?>
                 <?php else: ?>
                   <span class="text-muted">-</span>
                 <?php endif; ?>
               </td>
               <td>
                 <?php if ($data['has_db'] && !empty($data['assemblies'])): ?>
                   <span class="badge bg-success status-badge"><i class="fa fa-check-circle"></i> Complete</span>
                 <?php elseif (!empty($data['assemblies'])): ?>
                   <span class="badge bg-warning status-badge"><i class="fa fa-exclamation-triangle"></i> No Database</span>
                 <?php else: ?>
                   <span class="badge bg-danger status-badge"><i class="fa fa-times-circle"></i> No Assemblies</span>
                 <?php endif; ?>
               </td>
               <td>
                <small class="font-monospace"><?= htmlspecialchars($data['path']) ?></small>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick Actions -->
  <div class="card mt-4 mb-5">
    <div class="card-header bg-secondary text-white">
      <h5 class="mb-0"><i class="fa fa-bolt"></i> Quick Actions</h5>
    </div>
    <div class="card-body">
      <div class="row">
        <div class="col-md-4">
          <div class="d-grid">
            <a href="manage_groups.php" class="btn btn-primary">
              <i class="fa fa-layer-group"></i> Assign to Groups
            </a>
            <small class="text-muted mt-2">Add organisms to organism groups</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-grid">
            <a href="manage_group_descriptions.php" class="btn btn-primary">
              <i class="fa fa-edit"></i> Edit Group Descriptions
            </a>
            <small class="text-muted mt-2">Manage group metadata</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-grid">
            <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#helpModal">
              <i class="fa fa-question-circle"></i> Upload Help
            </button>
            <small class="text-muted mt-2">How to add new organisms</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Database Details Modals -->
<?php foreach ($organisms as $organism => $data): ?>
  <?php if ($data['db_validation']): 
      $validation = $data['db_validation'];
      $assembly_validation = $data['assembly_validation'];
      $fasta_validation = $data['fasta_validation'];
      $org_safe = htmlspecialchars($organism);
  ?>
  <div class="modal fade" id="dbModal<?= $org_safe ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-database"></i> Database Status: <?= $org_safe ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Overall Status -->
          <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
          <div class="card mb-3">
            <div class="card-body">
              <?php if ($validation['valid']): ?>
                <span class="badge bg-success" style="font-size: 1rem;"><i class="fa fa-check-circle"></i> Database is Healthy</span>
              <?php else: ?>
                <span class="badge bg-danger" style="font-size: 1rem;"><i class="fa fa-times-circle"></i> Database has Issues</span>
                <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below before using this organism.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Database File Info -->
          <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Database File</h6>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
            <strong>Required:</strong> A valid SQLite database file (genes.sqlite or organism_name.genes.sqlite) must exist in the organism directory with read permissions for the web server.
          </div>
          <div class="card mb-3">
            <div class="card-body" style="font-size: 0.9rem;">
              <p class="mb-1"><strong>Path:</strong> <?= htmlspecialchars($data['db_file'] ?? 'N/A') ?></p>
              <p class="mb-0">
                <strong>Readable:</strong> 
                <?= $validation['readable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
              </p>
            </div>
          </div>

          <!-- Database Validity -->
          <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Database Validity</h6>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
            <strong>Required:</strong> Database must be a valid SQLite3 file with proper structure. It should contain all required tables from the schema.
          </div>
          <div class="card mb-3">
            <div class="card-body" style="font-size: 0.9rem;">
              <p class="mb-1">
                <strong>Valid SQLite:</strong> 
                <?= $validation['database_valid'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
              </p>
              <?php if (!empty($validation['errors'])): ?>
                <p class="mb-0"><strong>Errors:</strong></p>
                <ul class="mb-0">
                  <?php foreach ($validation['errors'] as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>

          <!-- Tables -->
          <h6 class="fw-bold mb-2"><i class="fa fa-table"></i> Database Tables</h6>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
            <strong>Required Tables:</strong> organism, genome, feature, annotation_source, annotation, feature_annotation. Each table should have relevant data.
          </div>
          <div class="card mb-3">
            <div class="card-body" style="font-size: 0.9rem;">
              <?php if (!empty($validation['tables_present'])): ?>
                <p class="mb-2"><strong>Present (<?= count($validation['tables_present']) ?>):</strong></p>
                <ul class="mb-2">
                  <?php foreach ($validation['tables_present'] as $table): ?>
                    <li><?= htmlspecialchars($table) ?> 
                      <?php if (isset($validation['row_counts'][$table])): ?>
                        <span class="badge bg-info"><?= $validation['row_counts'][$table] ?> rows</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
              <?php if (!empty($validation['tables_missing'])): ?>
                <p class="mb-2"><strong class="text-danger">Missing (<?= count($validation['tables_missing']) ?>):</strong></p>
                <ul class="mb-0">
                  <?php foreach ($validation['tables_missing'] as $table): ?>
                    <li><span class="text-danger"><?= htmlspecialchars($table) ?></span></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>

          <!-- Data Quality -->
          <h6 class="fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Data Quality</h6>
          <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
            <strong>Check:</strong> Database records should have valid relationships and complete data. This checks for orphaned annotations, missing accessions, and features without proper organism links.
          </div>
          <div class="card mb-3 <?= empty($validation['data_issues']) ? 'border-success' : 'border-danger border-2' ?>">
            <div class="card-body" style="font-size: 0.9rem;">
              <?php if (empty($validation['data_issues'])): ?>
                <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> No data quality issues found</p>
              <?php else: ?>
                <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Issues Found:</strong></p>
                <ul class="mb-0">
                  <?php foreach ($validation['data_issues'] as $issue): ?>
                    <li class="mb-2">
                      <span class="text-danger"><?= htmlspecialchars($issue) ?></span>
                      <br>
                      <small class="text-muted">
                        <?php
                          if (strpos($issue, 'Orphaned annotations') !== false) {
                            echo 'Annotations exist in the database but are not linked to any annotation source. These records cannot be properly accessed.';
                          } elseif (strpos($issue, 'missing accession') !== false) {
                            echo 'An accession is a unique identifier (like a UniProt ID or NCBI accession number). Annotations should have accession values for proper identification and linking to external databases. Missing accessions prevent proper data cross-referencing.';
                          } elseif (strpos($issue, 'Features without organism') !== false) {
                            echo 'Features (genes, proteins, etc.) exist in the database but are not properly linked to an organism record. They cannot be associated with the correct biological entity.';
                          }
                        ?>
                      </small>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>


          <!-- Actions -->
          <?php if (!$validation['readable']): ?>
            <div class="card border-warning">
              <div class="card-header bg-warning bg-opacity-25">
                <h6 class="mb-0"><i class="fa fa-wrench"></i> Fix Permissions</h6>
              </div>
              <div class="card-body" style="font-size: 0.9rem;">
                <p class="mb-2">The database file is not readable by the web server. Click the button below to attempt an automatic fix.</p>
                <button class="btn btn-warning btn-sm" onclick="fixDatabasePermissions(event, '<?= $org_safe ?>')">
                  <i class="fa fa-wrench"></i> Fix Permissions
                </button>
                <div id="fixResult<?= $org_safe ?>" class="mt-3" style="display:none;"></div>
              </div>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
<?php endforeach; ?>

<!-- Assembly Detail Modals -->
<?php foreach ($organisms as $organism => $data): ?>
  <?php if (!empty($data['assemblies']) && $data['fasta_validation']): ?>
    <?php 
      $assembly_validation = $data['assembly_validation'];
    ?>
    <?php foreach ($data['assemblies'] as $assembly): ?>
      <?php 
        $safe_asm_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism . '_' . $assembly);
        $asm_fasta = $data['fasta_validation']['assemblies'][$assembly] ?? null;
        $is_missing = isset($data['fasta_validation']['missing_files'][$assembly]);
        $modal_id = 'asmModal' . $safe_asm_id;
        
        // Find if this assembly has database validation info
        $has_db_mismatch = false;
        $db_mismatch_messages = [];
        $matching_genome = null;
        
        if ($assembly_validation) {
          // Check if assembly name matches any genome_name or genome_accession
          foreach ($assembly_validation['genomes'] as $genome) {
            if ($assembly === $genome['genome_name'] || $assembly === $genome['genome_accession']) {
              $matching_genome = $genome;
              break;
            }
          }
          
          // If no match found, it's a mismatch
          if (!$matching_genome) {
            $has_db_mismatch = true;
            $db_mismatch_messages[] = "Assembly directory '$assembly' does not match any genome_name or genome_accession in the database";
          }
        }
      ?>
      <div class="modal fade" id="<?= $modal_id ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fa fa-folder"></i> Assembly: <?= htmlspecialchars($assembly) ?></h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <!-- Overall Status -->
              <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
              <div class="card mb-3">
                <div class="card-body">
                  <?php if (!$has_db_mismatch && !$is_missing): ?>
                    <span class="badge bg-success" style="font-size: 1rem;"><i class="fa fa-check-circle"></i> Assembly is Complete</span>
                  <?php else: ?>
                    <span class="badge bg-danger" style="font-size: 1rem;"><i class="fa fa-times-circle"></i> Assembly has Issues</span>
                    <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Assembly Overview -->
              <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Assembly Information</h6>
              <div class="card mb-3">
                <div class="card-body" style="font-size: 0.9rem;">
                  <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($assembly) ?></p>
                  <p class="mb-1"><strong>Organism:</strong> <?= htmlspecialchars($organism) ?></p>
                  <p class="mb-0"><strong>Path:</strong> <?= htmlspecialchars($data['path'] . '/' . $assembly) ?></p>
                </div>
              </div>

              <!-- Directory Name Validation (from Database) -->
              <?php if ($assembly_validation): ?>
                <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Directory Matching</h6>
                <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
                  <strong>Required:</strong> Assembly directory name must match either the <code>genome_name</code> or <code>genome_accession</code> from the database.
                </div>
                <div class="card mb-3 <?= $has_db_mismatch ? 'border-danger border-2' : 'border-success' ?>">
                  <div class="card-body" style="font-size: 0.9rem;">
                    <?php 
                      if ($matching_genome) {
                        echo '<p class="mb-2"><strong>The assembly directory name "' . htmlspecialchars($assembly) . '" matches:</strong></p>';
                        echo '<ul class="mb-0">';
                        
                        // Show check only on the field that matched
                        if ($assembly === $matching_genome['genome_name']) {
                          echo '  <li><span class="badge bg-success"><i class="fa fa-check"></i></span> DB genome_name: ' . htmlspecialchars($matching_genome['genome_name']) . '</li>';
                          echo '  <li>DB genome_accession: ' . htmlspecialchars($matching_genome['genome_accession']) . '</li>';
                        } else {
                          echo '  <li>DB genome_name: ' . htmlspecialchars($matching_genome['genome_name']) . '</li>';
                          echo '  <li><span class="badge bg-success"><i class="fa fa-check"></i></span> DB genome_accession: ' . htmlspecialchars($matching_genome['genome_accession']) . '</li>';
                        }
                        
                        echo '</ul>';
                      } else {
                        echo '<p class="text-danger"><i class="fa fa-exclamation-circle"></i> No matching genome record found in database.</p>';
                        if (!empty($db_mismatch_messages)) {
                          echo '<p class="mb-0"><small class="text-muted">' . implode('<br>', array_map('htmlspecialchars', $db_mismatch_messages)) . '</small></p>';
                        }
                      }
                    ?>
                  </div>
                </div>

                <!-- Rename Assembly Directory Tool -->
                <?php if ($has_db_mismatch): ?>
                  <h6 class="fw-bold mb-2"><i class="fa fa-tools"></i> Rename Assembly Directory</h6>
                  <div class="card border-warning">
                    <div class="card-header bg-warning bg-opacity-25">
                      <h6 class="mb-0"><i class="fa fa-exclamation-circle"></i> Action Needed: Rename existing directory to match database</h6>
                    </div>
                    <div class="card-body" style="font-size: 0.9rem;">
                      <p class="mb-3">If you have an assembly directory with the wrong name, you can rename it to match the database records.</p>
                      
                      <div class="row mb-3">
                        <div class="col-md-4">
                          <label for="oldDirName<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Current Directory Name</label>
                          <select class="form-select form-select-sm" id="oldDirName<?= htmlspecialchars($safe_asm_id) ?>">
                            <option value="">-- Select directory to rename --</option>
                            <?php 
                            // Get list of directories in organism folder
                            $organism_path = $data['path'];
                            if (is_dir($organism_path)) {
                              $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                              foreach ($dirs as $dir) {
                                $full_path = "$organism_path/$dir";
                                if (is_dir($full_path)) {
                                  echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
                                }
                              }
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-4">
                          <label for="newDirName<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Rename To</label>
                          <select class="form-select form-select-sm" id="newDirName<?= htmlspecialchars($safe_asm_id) ?>">
                            <option value="">-- Select new name --</option>
                            <?php 
                            // Show genome_name and genome_accession options
                            foreach ($assembly_validation['genomes'] as $genome) {
                              echo '<optgroup label="Genome ' . htmlspecialchars($genome['genome_id']) . '">';
                              if (!empty($genome['genome_name'])) {
                                echo '<option value="' . htmlspecialchars($genome['genome_name']) . '">name: ' . htmlspecialchars($genome['genome_name']) . '</option>';
                              }
                              if (!empty($genome['genome_accession'])) {
                                echo '<option value="' . htmlspecialchars($genome['genome_accession']) . '">accession: ' . htmlspecialchars($genome['genome_accession']) . '</option>';
                              }
                              echo '</optgroup>';
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                          <button class="btn btn-info btn-sm w-100" onclick="renameAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
                            <i class="fa fa-exchange-alt"></i> Rename
                          </button>
                        </div>
                      </div>
                      <div id="renameResult<?= htmlspecialchars($safe_asm_id) ?>" style="display:none;"></div>
                      
                      <hr class="my-3">
                      
                      <h6 class="fw-bold mb-3"><i class="fa fa-trash-alt"></i> Delete Directory</h6>
                      <p class="mb-3" style="font-size: 0.85rem;">If you no longer need this assembly directory, you can delete it permanently. This action cannot be undone.</p>
                      
                      <div class="row mb-3">
                        <div class="col-md-6">
                          <label for="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Directory to Delete</label>
                          <select class="form-select form-select-sm" id="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>">
                            <option value="">-- Select directory to delete --</option>
                            <?php 
                            // Get list of directories in organism folder
                            $organism_path = $data['path'];
                            if (is_dir($organism_path)) {
                              $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                              foreach ($dirs as $dir) {
                                $full_path = "$organism_path/$dir";
                                if (is_dir($full_path)) {
                                  echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
                                }
                              }
                            }
                            ?>
                          </select>
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                          <button class="btn btn-danger btn-sm w-100" onclick="deleteAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
                            <i class="fa fa-trash-alt"></i> Delete Directory
                          </button>
                        </div>
                      </div>
                      <div id="deleteResult<?= htmlspecialchars($safe_asm_id) ?>" style="display:none;"></div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <!-- FASTA Files Status -->
              <h6 class="fw-bold mb-2"><i class="fa fa-dna"></i> FASTA Files</h6>
              <div class="alert alert-info" style="font-size: 0.85rem; margin-bottom: 12px;">
                <strong>Required:</strong> Each assembly directory should contain FASTA files matching the configured sequence type patterns.
              </div>
              <div class="card mb-3 <?= $is_missing ? 'border-danger border-2' : 'border-success' ?>">
                <div class="card-body" style="font-size: 0.9rem;">
                  <?php if ($asm_fasta): ?>
                    <ul class="mb-0" style="list-style: none; padding-left: 0;">
                      <?php foreach ($asm_fasta['fasta_files'] as $type => $file_info): ?>
                        <li class="mb-2 pb-2 border-bottom" style="<?= $file_info['found'] ? '' : 'background-color: #fff3cd;' ?>">
                          <?php if ($file_info['found']): ?>
                            <span class="badge bg-success"><i class="fa fa-check"></i></span>
                            <strong><?= htmlspecialchars($type) ?>:</strong>
                            <?= htmlspecialchars($file_info['file']) ?>
                          <?php else: ?>
                            <span class="badge bg-danger"><i class="fa fa-times"></i></span>
                            <strong><?= htmlspecialchars($type) ?>:</strong>
                            <small class="text-muted">Missing pattern: *<?= htmlspecialchars($file_info['pattern']) ?></small>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <div class="alert alert-warning mb-0">No FASTA file information available</div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Status Summary -->
              <div class="alert <?= ($is_missing || $has_db_mismatch) ? 'alert-danger' : 'alert-success' ?>">
                <?php if ($has_db_mismatch || $is_missing): ?>
                  <i class="fa fa-exclamation-circle"></i> <strong>Issues Found:</strong>
                  <ul class="mb-0 mt-2">
                    <?php if ($has_db_mismatch): ?>
                      <li>Directory name does not match any genome record in the database</li>
                    <?php endif; ?>
                    <?php if ($is_missing): ?>
                      <li>Missing required FASTA files</li>
                    <?php endif; ?>
                  </ul>
                <?php else: ?>
                  <i class="fa fa-check-circle"></i> <strong>Complete:</strong> All checks passed.
                <?php endif; ?>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>

<!-- Fix Permissions Script -->
<script>
function fixDatabasePermissions(event, organism) {
    event.preventDefault();
    
    const resultDiv = document.getElementById('fixResult' + organism);
    const button = event.target.closest('button');
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Fixing...';
    resultDiv.style.display = 'none';
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=fix_permissions&organism=' + encodeURIComponent(organism)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Success!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info" style="margin-top: 10px; font-size: 0.85rem;">';
            html += '<strong>Run this command on the server:</strong><br>';
            html += '<code style="word-break: break-all;">' + data.command + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the fix.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Fixed!';
            button.classList.remove('btn-warning');
            button.classList.add('btn-success');
        } else {
            button.innerHTML = '<i class="fa fa-wrench"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-wrench"></i> Fix Permissions';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
    });
}

function renameAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    // Use safeAsmId if provided (for assembly modal), otherwise use organism (for db modal)
    const elementId = safeAsmId || organism;
    
    const oldDir = document.getElementById('oldDirName' + elementId).value;
    const newDir = document.getElementById('newDirName' + elementId).value;
    const resultDiv = document.getElementById('renameResult' + elementId);
    const button = event.target;
    
    if (!oldDir || !newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select both current and new directory names</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    if (oldDir === newDir) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Current and new names are the same</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Renaming...';
    resultDiv.style.display = 'none';
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=rename_assembly&organism=' + encodeURIComponent(organism) + 
              '&old_name=' + encodeURIComponent(oldDir) + 
              '&new_name=' + encodeURIComponent(newDir)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Success!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info" style="margin-top: 10px; font-size: 0.85rem;">';
            html += '<strong>Run this command on the server:</strong><br>';
            html += '<code style="word-break: break-all;">' + data.command + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the fix.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Renamed!';
            button.classList.remove('btn-info');
            button.classList.add('btn-success');
            document.getElementById('oldDirName' + elementId).value = '';
            document.getElementById('newDirName' + elementId).value = '';
        } else {
            button.innerHTML = '<i class="fa fa-exchange-alt"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-exchange-alt"></i> Rename';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
    });
}

function deleteAssemblyDirectory(event, organism, safeAsmId) {
    event.preventDefault();
    
    const dirToDelete = document.getElementById('dirToDelete' + safeAsmId).value;
    const resultDiv = document.getElementById('deleteResult' + safeAsmId);
    const button = event.target;
    
    if (!dirToDelete) {
        resultDiv.innerHTML = '<div class="alert alert-warning">Please select a directory to delete</div>';
        resultDiv.style.display = 'block';
        return;
    }
    
    // Ask for confirmation - this is serious!
    if (!confirm('⚠️  CAUTION: You are about to permanently delete the directory "' + dirToDelete + '". This action CANNOT be undone!\n\nAre you absolutely sure you want to continue?')) {
        return;
    }
    
    button.disabled = true;
    button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';
    resultDiv.style.display = 'none';
    
    fetch('manage_organisms.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: 'action=delete_assembly&organism=' + encodeURIComponent(organism) + 
              '&dir_name=' + encodeURIComponent(dirToDelete)
    })
    .then(response => response.json())
    .then(data => {
        button.disabled = false;
        
        let alertClass = data.success ? 'alert-success' : 'alert-danger';
        let html = '<div class="alert ' + alertClass + '">';
        html += '<strong>' + (data.success ? '✓ Deleted!' : '✗ Failed!') + '</strong><br>';
        html += '<p>' + data.message + '</p>';
        
        if (data.command) {
            html += '<div class="alert alert-info" style="margin-top: 10px; font-size: 0.85rem;">';
            html += '<strong>Web server lacks permissions. Run this command on the server:</strong><br>';
            html += '<code style="word-break: break-all;">' + data.command + '</code><br>';
            html += '<small class="mt-2 d-block text-muted">After running the command, refresh this page to verify the deletion.</small>';
            html += '</div>';
        }
        
        html += '</div>';
        
        if (data.success) {
            button.innerHTML = '<i class="fa fa-check"></i> Deleted!';
            button.classList.remove('btn-danger');
            button.classList.add('btn-success');
            document.getElementById('dirToDelete' + safeAsmId).value = '';
        } else {
            button.innerHTML = '<i class="fa fa-trash-alt"></i> Try Again';
        }
        
        resultDiv.innerHTML = html;
        resultDiv.style.display = 'block';
    })
    .catch(error => {
        button.disabled = false;
        button.innerHTML = '<i class="fa fa-trash-alt"></i> Delete Directory';
        resultDiv.innerHTML = '<div class="alert alert-danger">Error: ' + error + '</div>';
        resultDiv.style.display = 'block';
    });
}
</script>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-question-circle"></i> Adding New Organisms</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <h6 class="fw-bold">Step-by-Step Guide:</h6>
        <ol>
          <li><strong>Create organism directory:</strong> <code>mkdir <?= htmlspecialchars($organism_data) ?>/Genus_species</code></li>
          <li><strong>Add database file:</strong> Upload or create <code>genes.sqlite</code></li>
          <li><strong>Create organism.json:</strong> Add metadata about the organism</li>
          <li><strong>Create assembly directory:</strong> <code>mkdir Genus_species/assembly_name</code></li>
          <li><strong>Upload FASTA files:</strong> Add CDS, protein, transcript, and genome files to the assembly directory</li>
          <li><strong>Assign to groups:</strong> Use "Manage Groups" to make the organism accessible</li>
        </ol>
        
        <h6 class="fw-bold mt-4">Required Files in Assembly Directory:</h6>
        <ul>
          <li><code>*.cds.nt.fa</code> - Coding sequences (nucleotide)</li>
          <li><code>*.protein.aa.fa</code> - Protein sequences (amino acid)</li>
          <li><code>*.transcript.nt.fa</code> - Transcript sequences (nucleotide)</li>
          <li><code>*.genome.nt.fa</code> - Genome assembly (optional)</li>
        </ul>
        
        <h6 class="fw-bold mt-4">Additional Notes:</h6>
        <ul>
          <li><strong>Images:</strong> Place organism images in <code>/moop/images/</code></li>
          <li><strong>Viewing:</strong> Organisms are accessible via <code>/tools/display/organism_display.php?organism=Name</code></li>
          <li><strong>Documentation:</strong> See <code><?= htmlspecialchars($organism_data) ?>/ORGANISM_DISPLAY_README.md</code> for detailed specifications</li>
        </ul>
        
        <div class="alert alert-info mt-3">
          <i class="fa fa-lightbulb"></i> <strong>Tip:</strong> After uploading, this page will automatically detect and display the new organism. Then use "Assign to Groups" to control access.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#organismsTable').DataTable({
        pageLength: 25,
        order: [[0, 'asc']],
        columnDefs: [
            { orderable: false, targets: [5] }
        ]
    });
});
</script>

</body>
</html>

<?php
include_once '../footer.php';
?>

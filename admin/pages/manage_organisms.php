<?php
/**
 * MANAGE ORGANISMS - Content File
 */
?>

<div class="container mt-5">
  <!-- Back to Admin Dashboard Link -->
  <div class="mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>

  <?php
  ?>
  
  <h2><i class="fa fa-dna"></i> Manage Organisms</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutOrganismManagement">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About Organism Management <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutOrganismManagement">
      <div class="card-body">
        <p><strong>Purpose:</strong> View and manage all organism data on your system. Each organism has assemblies (genome versions), FASTA sequence files, databases, and metadata.</p>
        
        <p><strong>Why This Matters:</strong> Organisms are the core of the system. Every organism needs:</p>
        <ul>
          <li>A database file (organism.sqlite) with gene/protein data</li>
          <li>FASTA files organized in assembly directories</li>
          <li>Metadata describing the organism (genus, species, common name, taxonomy ID)</li>
          <li>Assignment to groups for user access control</li>
          <li>Inclusion in the taxonomy tree for homepage discovery</li>
        </ul>
        
        <p><strong>Status Checklist (8 Requirements):</strong> The system tracks 8 dimensions of organism readiness:</p>
        <ul>
          <li>Has assemblies - At least one genome version exists</li>
          <li>Has FASTA files - Required sequence files are present</li>
          <li>Has BLAST indexes - Files are searchable via BLAST</li>
          <li>Has database file - SQLite database exists</li>
          <li>Database is readable - Web server can access it</li>
          <li>In organism groups - Assembly is assigned to user groups</li>
          <li>In taxonomy tree - Visible on homepage organism selector</li>
          <li>Metadata complete - All organism information is filled in</li>
        </ul>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>View all organisms and their status at a glance</li>
          <li>Check database validity and readability</li>
          <li>Manage metadata (images, descriptions)</li>
          <li>Handle assembly directories and FASTA files</li>
          <li>Verify BLAST indexes are present</li>
          <li>See which groups each organism belongs to</li>
          <li>Track overall setup completion with the 8-point checklist</li>
        </ul>
      </div>
    </div>
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
            &nbsp;&nbsp;<i class="fa fa-database db-icon"></i> organism.sqlite<br>
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
            <li><strong>Database File:</strong> organism.sqlite</li>
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
    "genus": "Lasiurus",
    "species": "cinereus",
    "common_name": "Hoary bat",
    "taxon_id": "257879",
    "images": [
        {
            "file": "Lasiurus_cinereus.jpg",
            "caption": "Image of a Hoary bat"
        }
    ],
    "html_p": [
        {
            "text": "&lt;u&gt;Diet:&lt;/u&gt; Hoary bats feed primarily on moths...",
            "style": "",
            "class": "fs-5"
        },
        {
            "text": "&lt;u&gt;Fun Fact:&lt;/u&gt; Hoary bats produce short, quiet micro calls...",
            "style": "",
            "class": "fs-5"
        }
    ]
}</pre>
              <small class="text-muted"><strong>Note:</strong> Required fields: genus, species, common_name, taxon_id. Optional fields: images, html_p. Images should be placed in <code>/moop/images/</code></small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="alert alert-warning mt-3 mb-0">
        <i class="fa fa-exclamation-triangle"></i> <strong>Important:</strong> Data must be uploaded directly to the server at: <code><?= htmlspecialchars($organism_data) ?></code>
      </div>
    </div>
  </div>

  <!-- Legend Box -->
  <div class="card mb-4">
    <div class="card-header bg-light" style="cursor: pointer;" id="legendHeader" role="button">
      <h6 class="mb-0">
        <i class="fa fa-book"></i> <strong>Legend & Status Guide</strong>
        <span class="ms-2">
          <span class="badge bg-warning" style="width: 12px; height: 12px; display: inline-block; padding: 0;"></span>
          <span class="badge bg-danger" style="width: 12px; height: 12px; display: inline-block; padding: 0;"></span>
          <span class="badge bg-info" style="width: 12px; height: 12px; display: inline-block; padding: 0;"></span>
        </span>
        <i class="fa fa-chevron-down float-end" id="legendChevron"></i>
      </h6>
    </div>
    <div id="legendContent">
      <div class="card-body">
        <!-- Assemblies Legend -->
        <div class="mb-4">
          <h6 class="fw-bold mb-2"><i class="fa fa-folder"></i> Assemblies Status</h6>
          <p class="mb-2">
            <span class="badge bg-success"><i class="fa fa-check-circle"></i> Complete</span> - Assembly directory exists with valid FASTA files
            <br><span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> Name Mismatch</span> - Directory name doesn't match database genome name
            <br><span class="badge bg-secondary"><i class="fa fa-rocket"></i> Missing BLAST Indexes</span> - FASTA files present but BLAST indexes need to be generated
            <br><span class="badge bg-info"><i class="fa fa-times-circle"></i> Missing Files</span> - Assembly missing required FASTA files
          </p>
          <p class="small text-muted"><i class="fa fa-info-circle"></i> <strong>Tip:</strong> Click an assembly button for detailed information and available tools.</p>
        </div>

        <!-- Database Status Legend -->
        <div class="mb-4">
          <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Status</h6>
          <p class="mb-2">
            <button class="btn btn-sm btn-outline-success"><i class="fa fa-check-circle"></i> Ready</button> - Database exists, is readable, and valid
            <br><button class="btn btn-sm btn-outline-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete</button> - Database valid but has assembly issues
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-lock"></i> Unreadable</button> - Database file exists but web server cannot read it
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-times-circle"></i> Invalid</button> - Database file is corrupted or invalid
          </p>
          <p class="small text-muted"><i class="fa fa-info-circle"></i> <strong>Tip:</strong> Click the database status button to view detailed validation information and troubleshooting options.</p>
        </div>

        <!-- Metadata Status Legend -->
        <div class="mb-3">
          <h6 class="fw-bold mb-2"><i class="fa fa-file-code"></i> Metadata Status</h6>
          <p class="mb-2">
            <button class="btn btn-sm btn-outline-success"><i class="fa fa-check-circle"></i> Complete</button> - organism.json exists with all required fields and is writable
            <br><button class="btn btn-sm btn-outline-warning"><i class="fa fa-lock"></i> Not Writable</button> - File has all required data but is not writable by web server
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-times-circle"></i> Missing</button> - organism.json file does not exist
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-lock"></i> Unreadable</button> - File exists but cannot be read
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-times-circle"></i> Invalid JSON</button> - File exists but contains invalid JSON
            <br><button class="btn btn-sm btn-outline-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete</button> - JSON valid but missing required fields
          </p>
          <p class="small text-muted"><i class="fa fa-info-circle"></i> <strong>Tip:</strong> Click the metadata status button to edit metadata, add images, and write organism descriptions.</p>
        </div>

        <!-- Overall Status Legend -->
        <div class="mb-0">
          <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
          <p class="mb-2">
            <button class="btn btn-sm btn-outline-success"><i class="fa fa-check-circle"></i> Complete <span class="badge bg-success">8</span></button> - All 8 checks passed
            <br><button class="btn btn-sm btn-outline-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete <span class="badge bg-success">X</span></button> - Some checks passed (see modal for details)
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-times-circle"></i> Critical <span class="badge bg-danger">0</span></button> - No checks passed (no database or no assemblies)
          </p>
          <p class="small text-muted"><i class="fa fa-info-circle"></i> <strong>Tip:</strong> Click the status button to see the detailed checklist of all 8 setup requirements.</p>
          <p class="small text-muted"><strong>Checklist includes:</strong> Assemblies • FASTA files • BLAST indexes • Database file • Database readable • Assemblies in groups • Organism in tree • Metadata complete</p>
        </div>
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
             <th>Tree Status</th>
             <th>Assemblies</th>
             <th>DB Status</th>
             <th>Metadata Status</th>
             <th>Status</th>
           </tr>
         </thead>
         <tbody>
           <?php foreach ($organisms as $organism => $data): ?>
             <tr>
               <td>
               <strong>
                 <a href="../tools/organism.php?organism=<?= urlencode($organism) ?>" target="_blank" title="View organism page">
                   <?= htmlspecialchars($organism) ?>
                   <i class="fa fa-external-link-alt" style="font-size: 0.85em; margin-left: 0.25em;"></i>
                 </a>
               </strong>
                 <?php if (isset($data['info']['genus']) && isset($data['info']['species'])): ?>
                   <br><small class="text-muted"><em><?= htmlspecialchars($data['info']['genus']) ?> <?= htmlspecialchars($data['info']['species']) ?></em></small>
                 <?php endif; ?>
                 <br><button class="btn btn-sm btn-outline-secondary" type="button" onclick="togglePath(this, '<?= htmlspecialchars($data['path']) ?>', '<?= htmlspecialchars($organism) ?>')">
                   <i class="fa fa-folder"></i> View Path
                 </button>
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
                 <?php 
                   $in_taxonomy_tree = isAssemblyInTaxonomyTree($organism, '', $taxonomy_tree_file);
                 ?>
                 <?php if ($in_taxonomy_tree): ?>
                   <a href="manage_taxonomy_tree.php" target="_blank" class="btn btn-sm btn-outline-success" title="Click to manage taxonomy tree">
                     <i class="fa fa-check-circle"></i> Complete
                   </a>
                 <?php else: ?>
                   <a href="manage_taxonomy_tree.php" target="_blank" class="btn btn-sm btn-outline-warning" title="Click to add to taxonomy tree">
                     <i class="fa fa-times-circle"></i> Missing
                   </a>
                 <?php endif; ?>
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
                         
                         // Check if BLAST indexes are missing for FASTA files
                         $has_missing_blast_indexes = false;
                         $assembly_path = $data['path'] . '/' . $assembly;
                         $blast_validation = validateBlastIndexFiles($assembly_path, $sequence_types);
                         if (!empty($blast_validation['databases'])) {
                           foreach ($blast_validation['databases'] as $db) {
                             if (!$db['has_indexes']) {
                               $has_missing_blast_indexes = true;
                               break;
                             }
                           }
                         }
                         
                         // Determine badge style
                         // Priority: name mismatch > missing blast indexes > missing fasta files
                         $badge_class = 'bg-success';
                         if ($has_name_mismatch) {
                             $badge_class = 'bg-warning';
                         } elseif ($has_missing_blast_indexes) {
                             $badge_class = 'bg-secondary';
                         } elseif ($is_missing) {
                             $badge_class = 'bg-info';
                         }
                       ?>
                       <button class="btn btn-sm d-block w-100 text-start mb-1 <?= $badge_class ?> text-white" data-bs-toggle="modal" data-bs-target="#asmModal<?= htmlspecialchars($safe_asm_id) ?>">
                         <i class="fa fa-folder"></i> <?= htmlspecialchars($assembly) ?>
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
                 <?php 
                   $json_val = $data['json_validation'];
                   if ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields'] && $json_val['writable']): ?>
                     <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-check-circle"></i> Complete
                     </button>
                   <?php elseif ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields'] && !$json_val['writable']): ?>
                     <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-lock"></i> Not Writable
                     </button>
                   <?php elseif (!$json_val['exists']): ?>
                     <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-times-circle"></i> Missing
                     </button>
                   <?php elseif (!$json_val['readable']): ?>
                     <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-lock"></i> Unreadable
                     </button>
                   <?php elseif (!$json_val['valid_json']): ?>
                     <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-times-circle"></i> Invalid JSON
                     </button>
                   <?php elseif (!$json_val['has_required_fields']): ?>
                     <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-exclamation-triangle"></i> Incomplete
                     </button>
                   <?php else: ?>
                     <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#metadataModal<?= htmlspecialchars($organism) ?>">
                       <i class="fa fa-exclamation-triangle"></i> Issues
                     </button>
                   <?php endif; ?>
               </td>
               <td>
                 <?php 
                   // Get comprehensive status
                   $status = getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
                   $pass_count = $status['pass_count'];
                   $total_count = $status['total_count'];
                   $safe_org_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism);
                 ?>
                 <?php if ($status['all_pass']): ?>
                   <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#statusModal<?= $safe_org_id ?>">
                     <i class="fa fa-check-circle"></i> Complete <span class="badge bg-success">8</span>
                   </button>
                 <?php elseif ($pass_count > 0): ?>
                   <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#statusModal<?= $safe_org_id ?>">
                     <i class="fa fa-exclamation-triangle"></i> Incomplete <span class="badge bg-success"><?= $pass_count ?></span>
                   </button>
                 <?php else: ?>
                   <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#statusModal<?= $safe_org_id ?>">
                     <i class="fa fa-times-circle"></i> Critical <span class="badge bg-danger">0</span>
                   </button>
                 <?php endif; ?>
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
            <a href="manage_taxonomy_tree.php" target="_blank" class="btn btn-primary">
              <i class="fa fa-project-diagram"></i> Manage Taxonomy Tree
            </a>
            <small class="text-muted mt-2">Build the organism selector</small>
          </div>
        </div>
        <div class="col-md-4">
          <div class="d-grid">
            <a href="manage_groups.php" target="_blank" class="btn btn-primary">
              <i class="fa fa-layer-group"></i> Manage Groups & Descriptions
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
                <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Database is Healthy</span>
              <?php else: ?>
                <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Database has Issues</span>
                <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below before using this organism.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Database File Info -->
          <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Database File</h6>
          <div class="alert alert-info small mb-3">
            <strong>Required:</strong> A valid SQLite database file named <code>organism.sqlite</code> must exist in the organism directory with read permissions for the web server.
          </div>
          <div class="card mb-3">
            <div class="card-body small">
              <p class="mb-1"><strong>Path:</strong> <?= htmlspecialchars($data['db_file'] ?? 'N/A') ?></p>
              <p class="mb-0">
                <strong>Readable:</strong> 
                <?= $validation['readable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
              </p>
            </div>
          </div>

          <!-- Database Validity -->
          <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Database Validity</h6>
          <div class="alert alert-info small mb-3">
            <strong>Required:</strong> Database must be a valid SQLite3 file with proper structure. It should contain all required tables from the schema.
          </div>
          <div class="card mb-3">
            <div class="card-body small">
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
          <div class="alert alert-info small mb-3">
            <strong>Required Tables:</strong> organism, genome, feature, annotation_source, annotation, feature_annotation. Each table should have relevant data.
          </div>
          <div class="card mb-3">
            <div class="card-body small">
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

          <!-- Feature Types and Counts -->
          <h6 class="fw-bold mb-2"><i class="fa fa-sitemap"></i> Feature Types and Counts</h6>
          <div class="alert alert-info small mb-3">
            <strong>Summary:</strong> Shows the count of each feature type in the database.
          </div>
          <div class="card mb-3">
            <div class="card-body small">
              <?php if (!empty($validation['feature_counts'])): ?>
                <p class="mb-2"><strong>Features (<?= count($validation['feature_counts']) ?>):</strong></p>
                <ul class="mb-0">
                  <?php foreach ($validation['feature_counts'] as $feature_type => $count): ?>
                    <li><?= htmlspecialchars($feature_type) ?> 
                      <span class="badge bg-info"><?= number_format($count) ?> features</span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <p class="mb-0 text-muted"><i class="fa fa-info-circle"></i> No feature data available</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- Data Quality -->
          <h6 class="fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Data Quality</h6>
          <div class="alert alert-info small mb-3">
            <strong>Check:</strong> Database records should have valid relationships and complete data. This checks for orphaned annotations, missing accessions, and features without proper organism links.
          </div>
          <div class="card mb-3 <?= empty($validation['data_issues']) ? 'border-success' : 'border-danger border-2' ?>">
            <div class="card-body small">
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


          <!-- Assembly Validation (if issues detected) -->
          <?php if ($assembly_validation): ?>
            <h6 class="fw-bold mb-2"><i class="fa fa-folder"></i> Assembly Validation</h6>
            <div class="alert alert-info small mb-3">
              <strong>Required:</strong> For each genome record in the database, at least one directory must exist in the organism folder with a name matching either the <code>genome_name</code> or <code>genome_accession</code> value from the database. This links the filesystem assembly directories to the database genome records.
            </div>
            <div class="card mb-3 <?= $assembly_validation['valid'] ? 'border-success' : 'border-danger border-2' ?>">
              <div class="card-body small">
                <?php if ($assembly_validation['valid'] && empty($assembly_validation['mismatches'])): ?>
                  <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> All assembly directories match database records</p>
                <?php else: ?>
                  <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Assembly Issues Found:</strong></p>
                  <p class="mb-2 text-danger"><small>One or more genome records in the database do not have corresponding directories in the organism folder. Each genome must have a matching directory.</small></p>
                  <ul class="mb-0">
                    <?php if (!empty($assembly_validation['mismatches'])): ?>
                      <?php foreach ($assembly_validation['mismatches'] as $mismatch): ?>
                        <li class="mb-2">
                          <span class="text-danger"><strong><?= htmlspecialchars($mismatch['type'] === 'missing_directory' ? 'Missing Directory' : 'Name Mismatch') ?>:</strong></span>
                          <br>
                          <?php if ($mismatch['type'] === 'missing_directory'): ?>
                            <small class="text-muted">No directory found matching genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>". The genome record in the database has no corresponding assembly directory.</small>
                          <?php else: ?>
                            <small class="text-muted">Directory "<?= htmlspecialchars($mismatch['found_directory']) ?>" exists but doesn't match genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>". You can rename the directory to fix this.</small>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Actions -->
          <?php if (!$validation['readable']): ?>
            <div class="card border-warning">
              <div class="card-header bg-warning bg-opacity-25">
                <h6 class="mb-0"><i class="fa fa-wrench"></i> Fix Permissions</h6>
              </div>
              <div class="card-body small">
                <p class="mb-2">The database file is not readable by the web server. Click the button below to attempt an automatic fix.</p>
                <button class="btn btn-warning btn-sm" onclick="fixDatabasePermissions(event, '<?= $org_safe ?>')">
                  <i class="fa fa-wrench"></i> Fix Permissions
                </button>
                <div id="fixResult<?= $org_safe ?>" class="mt-3" class="d-none"></div>
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

<!-- Metadata Modals -->
<?php foreach ($organisms as $organism => $data): ?>
  <?php 
    $json_val = $data['json_validation'];
    $org_safe = htmlspecialchars($organism);
  ?>
  <div class="modal fade" id="metadataModal<?= $org_safe ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-file-code"></i> Organism Metadata: <?= $org_safe ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Validation Status -->
          <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Validation Status</h6>
          <div class="card mb-3">
            <div class="card-body">
              <?php if ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields']): ?>
                <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Metadata is Complete</span>
              <?php elseif (!$json_val['exists']): ?>
                <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Metadata File Missing</span>
                <p class="mt-2 mb-0 text-muted small">The organism.json file does not exist. Click "Create Metadata File" below to create one.</p>
              <?php else: ?>
                <span class="badge bg-warning h6"><i class="fa fa-exclamation-triangle"></i> Metadata has Issues</span>
                <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
              <?php endif; ?>
            </div>
          </div>

          <!-- File Status -->
          <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> File Status</h6>
          <div class="card mb-3">
            <div class="card-body small">
              <p class="mb-2">
                <?php if ($json_val['exists']): ?>
                  <strong>Exists:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
                <?php else: ?>
                  <strong>Exists:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No</span>
                <?php endif; ?>
              </p>
              <p class="mb-2">
                <?php if ($json_val['readable']): ?>
                  <strong>Readable:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
                <?php elseif ($json_val['exists']): ?>
                  <strong>Readable:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No (Permission denied)</span>
                <?php endif; ?>
              </p>
              <p class="mb-2">
                <?php if ($json_val['readable']): ?>
                  <?php if ($json_val['writable']): ?>
                    <strong>Writable:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
                  <?php else: ?>
                    <strong>Writable:</strong> <span class="badge bg-warning"><i class="fa fa-lock"></i> No (Read-only)</span>
                  <?php endif; ?>
                <?php endif; ?>
              </p>
              <p class="mb-0">
                <?php if ($json_val['valid_json']): ?>
                  <strong>JSON Valid:</strong> <span class="badge bg-success"><i class="fa fa-check"></i> Yes</span>
                <?php elseif ($json_val['readable']): ?>
                  <strong>JSON Valid:</strong> <span class="badge bg-danger"><i class="fa fa-times"></i> No (Invalid JSON)</span>
                <?php endif; ?>
              </p>
            </div>
          </div>

          <!-- Permission Fix Alert (using new unified system) -->
          <?php echo generatePermissionAlert(
              $data['path'] . '/organism.json',
              'Metadata File Permission Issue',
              'The organism.json file has permission issues.',
              'file',
              $organism
          ); ?>

          <!-- Required Fields -->
          <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Required Fields</h6>
          <div class="alert alert-info small mb-3">
            <strong>Required:</strong> All fields must be present and non-empty: genus, species, common_name, taxon_id
          </div>
          <div class="card mb-3">
            <div class="card-body small">
              <?php if (!empty($json_val['errors'])): ?>
                <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Errors:</strong></p>
                <ul class="mb-0">
                  <?php foreach ($json_val['errors'] as $error): ?>
                    <li class="text-danger"><?= htmlspecialchars($error) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?>
                <ul class="mb-0" class="list-unstyled">
                  <?php foreach ($json_val['required_fields'] as $field): ?>
                    <li class="mb-1">
                      <span class="badge bg-success"><i class="fa fa-check"></i></span> <strong><?= htmlspecialchars($field) ?></strong>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>

          <!-- Editor Section -->
          <h6 class="fw-bold mb-2"><i class="fa fa-edit"></i> Metadata Editor</h6>
          <form id="metadataForm<?= htmlspecialchars($organism) ?>" class="metadata-form">
            <input type="hidden" name="organism" value="<?= $org_safe ?>">
            <input type="hidden" name="images_json" id="images-json-<?= htmlspecialchars($organism) ?>">
            <input type="hidden" name="html_p_json" id="html-p-json-<?= htmlspecialchars($organism) ?>">
            
            <!-- Basic Fields -->
            <div class="mb-3">
              <label for="genus<?= htmlspecialchars($organism) ?>" class="form-label">Genus <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="genus<?= htmlspecialchars($organism) ?>" name="genus" 
                     value="<?= htmlspecialchars($data['info']['genus'] ?? '') ?>" required>
              <small class="text-muted">e.g., Anoura</small>
            </div>

            <div class="mb-3">
              <label for="species<?= htmlspecialchars($organism) ?>" class="form-label">Species <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="species<?= htmlspecialchars($organism) ?>" name="species" 
                     value="<?= htmlspecialchars($data['info']['species'] ?? '') ?>" required>
              <small class="text-muted">e.g., caudifer</small>
            </div>

            <div class="mb-3">
              <label for="common_name<?= htmlspecialchars($organism) ?>" class="form-label">Common Name <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="common_name<?= htmlspecialchars($organism) ?>" name="common_name" 
                     value="<?= htmlspecialchars($data['info']['common_name'] ?? '') ?>" required>
              <small class="text-muted">e.g., Tailed Tailless Bat</small>
            </div>

            <div class="mb-3">
              <label for="taxon_id<?= htmlspecialchars($organism) ?>" class="form-label">Taxon ID <span class="text-danger">*</span></label>
              <input type="text" class="form-control" id="taxon_id<?= htmlspecialchars($organism) ?>" name="taxon_id" 
                     value="<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>" required>
              <small class="text-muted">NCBI taxonomy ID, e.g., 27642</small>
            </div>

            <hr class="my-4">

            <!-- Feature Types Section -->
            <h5 class="mb-3"><i class="fa fa-sitemap"></i> Feature Types</h5>
            <div class="alert alert-info small mb-3">
              <strong>Note:</strong> Define which feature types are parents (typically genes) and which are children (transcripts, proteins, etc.)
            </div>
            
            <input type="hidden" name="parents_json" id="parents-json-<?= htmlspecialchars($organism) ?>">
            <input type="hidden" name="children_json" id="children-json-<?= htmlspecialchars($organism) ?>">
            
            <div class="row">
              <div class="col-md-6">
                <label class="form-label">Parent Features</label>
                <div id="parents-<?= htmlspecialchars($organism) ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
                  <?php 
                    $parents = $data['info']['feature_types']['parents'] ?? ['gene'];
                    foreach ($parents as $feature): 
                  ?>
                    <span class="badge bg-primary me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                      <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                    </span>
                  <?php endforeach; ?>
                </div>
                <input type="text" class="form-control mt-2" id="parent-feature-input-<?= htmlspecialchars($organism) ?>" placeholder="e.g., gene, pseudogene">
                <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'parent')">
                  <i class="fa fa-plus"></i> Add Parent Feature
                </button>
              </div>
              <div class="col-md-6">
                <label class="form-label">Child Features</label>
                <div id="children-<?= htmlspecialchars($organism) ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
                  <?php 
                    $children = $data['info']['feature_types']['children'] ?? ['mRNA', 'transcript'];
                    foreach ($children as $feature): 
                  ?>
                    <span class="badge bg-info me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                      <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                    </span>
                  <?php endforeach; ?>
                </div>
                <input type="text" class="form-control mt-2" id="child-feature-input-<?= htmlspecialchars($organism) ?>" placeholder="e.g., mRNA, transcript, protein">
                <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'child')">
                  <i class="fa fa-plus"></i> Add Child Feature
                </button>
              </div>
            </div>

            <hr class="my-4">

            <!-- Images Section -->
            <h5 class="mb-3"><i class="fa fa-image"></i> Images</h5>
            <div class="alert alert-info small mb-3">
              <strong>Note:</strong> If no images are provided here, the image from 
              <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>/" target="_blank">
                NCBI Taxonomy (ID: <?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>)
              </a>
              will be used as the default.
            </div>
            <div id="images-container-<?= htmlspecialchars($organism) ?>">
              <?php 
                $images = $data['info']['images'] ?? [['file' => '', 'caption' => '']];
                foreach ($images as $idx => $image): 
              ?>
                <div class="image-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
                  <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataImage('<?= $org_safe ?>', <?= $idx ?>)" style="float: right;">Remove</button>
                  <div class="form-group mb-3">
                    <label>Image File</label>
                    <div class="input-group">
                      <input type="text" class="form-control image-file" value="<?= htmlspecialchars($image['file'] ?? '') ?>" placeholder="e.g., organism_image.jpg">
                      <button type="button" class="btn btn-outline-secondary upload-image-btn">Upload</button>
                    </div>
                    <input type="file" class="image-upload-input" style="display:none;" accept="image/*">
                    <small class="form-text text-muted">Or upload a photo directly</small>
                  </div>
                  <div class="form-group">
                    <label>Caption (HTML allowed)</label>
                    <textarea class="form-control image-caption" rows="2"><?= htmlspecialchars($image['caption'] ?? '') ?></textarea>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-primary mb-4" onclick="addMetadataImage('<?= $org_safe ?>')">
              <i class="fa fa-plus"></i> Add Image
            </button>

            <!-- HTML Paragraphs Section -->
            <h5 class="mb-3"><i class="fa fa-paragraph"></i> HTML Paragraphs</h5>
            <div id="paragraphs-container-<?= htmlspecialchars($organism) ?>">
              <?php 
                $paragraphs = $data['info']['html_p'] ?? [['text' => '', 'style' => '', 'class' => '']];
                foreach ($paragraphs as $idx => $para): 
              ?>
                <div class="paragraph-item" data-index="<?= $idx ?>" style="border: 1px solid #dee2e6; padding: 15px; margin-bottom: 10px; border-radius: 5px; background: #f8f9fa;">
                  <button type="button" class="btn btn-sm btn-danger remove-btn" onclick="removeMetadataParagraph('<?= $org_safe ?>', <?= $idx ?>)" style="float: right;">Remove</button>
                  <div class="form-group mb-3">
                    <label>Text (HTML allowed)</label>
                    <textarea class="form-control para-text" rows="4"><?= htmlspecialchars($para['text'] ?? '') ?></textarea>
                  </div>
                  <div class="row">
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>CSS Style</label>
                        <input type="text" class="form-control para-style" value="<?= htmlspecialchars($para['style'] ?? '') ?>" placeholder="e.g., color: red;">
                      </div>
                    </div>
                    <div class="col-md-6">
                      <div class="form-group">
                        <label>CSS Class</label>
                        <input type="text" class="form-control para-class" value="<?= htmlspecialchars($para['class'] ?? '') ?>" placeholder="e.g., lead">
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-primary mb-4" onclick="addMetadataParagraph('<?= $org_safe ?>')">
              <i class="fa fa-plus"></i> Add Paragraph
            </button>

            <div id="saveResult<?= htmlspecialchars($organism) ?>"></div>

            <button type="button" class="btn btn-success" onclick="saveMetadata(event, '<?= $org_safe ?>')">
              <i class="fa fa-save"></i> Save Metadata
            </button>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
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
        $assembly_path = $data['path'] . '/' . $assembly;
        
        // Get BLAST index status
        $blast_validation = validateBlastIndexFiles($assembly_path, $sequence_types);
        
        // Get group membership
        $assembly_groups = getAssemblyGroups($organism, $assembly, $groups_data);
        
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
      <div class="modal fade" id="<?= $modal_id ?>" data-assembly-name="<?= htmlspecialchars($assembly) ?>" tabindex="-1">
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
                    <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Assembly is Complete</span>
                  <?php else: ?>
                    <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Assembly has Issues</span>
                    <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Assembly Overview -->
              <h6 class="fw-bold mb-2"><i class="fa fa-info-circle"></i> Assembly Information</h6>
              <div class="card mb-3">
                <div class="card-body small">
                  <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($assembly) ?></p>
                  <p class="mb-1"><strong>Organism:</strong> <?= htmlspecialchars($organism) ?></p>
                  <p class="mb-0"><strong>Path:</strong> <?= htmlspecialchars($data['path'] . '/' . $assembly) ?></p>
                </div>
              </div>

              <!-- Directory Name Validation (from Database) -->
              <?php if ($assembly_validation): ?>
                <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Directory Matching</h6>
                <div class="alert alert-info small mb-3">
                  <strong>Required:</strong> Assembly directory name must match either the <code>genome_name</code> or <code>genome_accession</code> from the database.
                </div>
                <div class="card mb-3 <?= $has_db_mismatch ? 'border-danger border-2' : 'border-success' ?>">
                  <div class="card-body small">
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
                    <div class="card-body small">
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
                      <div id="renameResult<?= htmlspecialchars($safe_asm_id) ?>" class="d-none"></div>
                      
                      <hr class="my-3">
                      
                      <h6 class="fw-bold mb-3"><i class="fa fa-trash-alt"></i> Delete Directory</h6>
                      <p class="mb-3" class="small">If you no longer need this assembly directory, you can delete it permanently. This action cannot be undone.</p>
                      
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
                      <div id="deleteResult<?= htmlspecialchars($safe_asm_id) ?>" class="d-none"></div>
                    </div>
                  </div>
                <?php endif; ?>
              <?php endif; ?>

              <!-- FASTA Files Status -->
              <h6 class="fw-bold mb-2"><i class="fa fa-dna"></i> FASTA Files</h6>
              <div class="alert alert-info small mb-3">
                <strong>Required:</strong> Each assembly directory should contain FASTA files matching the configured sequence type patterns.
              </div>
              <div class="card mb-3 <?= $is_missing ? 'border-danger border-2' : 'border-success' ?>">
                <div class="card-body small">
                  <?php if ($asm_fasta): ?>
                    <ul class="mb-0" class="list-unstyled">
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

              <!-- BLAST Index Status -->
              <h6 class="fw-bold mb-2"><i class="fa fa-rocket"></i> BLAST Database Indexes</h6>
              <div class="alert alert-info small mb-3">
                <strong>Required:</strong> Each FASTA file needs BLAST index files (.nhr, .nin, .nsq for nucleotide; .phr, .pin, .psq for protein) to be searchable.
              </div>
              <div class="card mb-3 <?= $blast_validation['missing_count'] > 0 ? 'border-warning border-2' : 'border-success' ?>">
                <div class="card-body small">
                  <?php if (!empty($blast_validation['databases'])): ?>
                    <ul class="mb-0">
                      <?php foreach ($blast_validation['databases'] as $db): ?>
                        <li class="mb-2 pb-2 border-bottom">
                          <?php if ($db['has_indexes']): ?>
                            <span class="badge bg-success"><i class="fa fa-check"></i></span>
                            <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                            <br><small class="text-muted">Indexes: ✓ Present</small>
                          <?php else: ?>
                            <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
                            <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                            <br><small class="text-danger">Missing indexes: <?= htmlspecialchars(implode(', ', $db['missing_indexes'])) ?></small>
                            <br><small class="text-muted">This FASTA file cannot be used for BLAST searches until indexes are created.</small>
                            <div class="mt-2 p-2 bg-light border rounded small">
                              <strong class="d-block mb-2">To generate BLAST indexes, run on the server:</strong>
                              <?php 
                                $assembly_fullpath = $organism_data . '/' . $organism . '/' . $assembly;
                                $fasta_full_path = $assembly_fullpath . '/' . $db['fasta'];
                                $db_full_path = preg_replace('/\.(fa|fasta)$/', '', $fasta_full_path);
                                $is_protein = strpos($db['fasta'], 'protein') !== false;
                                $db_type = $is_protein ? 'prot' : 'nucl';
                                $cd_cmd = "cd " . htmlspecialchars($assembly_fullpath);
                                $makeblastdb_cmd = "makeblastdb -in " . htmlspecialchars($db['fasta']) . " -dbtype " . htmlspecialchars($db_type) ;
                              ?>
                              <code class="d-block" style="word-break: break-all; white-space: normal;">
                                <?= $cd_cmd ?> && \<br><?= $makeblastdb_cmd ?>
                              </code>
                            </div>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <div class="alert alert-warning mb-0">No FASTA files found for BLAST indexing</div>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Groups Status -->
              <h6 class="fw-bold mb-2"><i class="fa fa-sitemap"></i> Group Membership</h6>
              <div class="card mb-3">
                <div class="card-body small">
                  <?php if (!empty($assembly_groups)): ?>
                    <p class="mb-2"><strong>This assembly is in <?= count($assembly_groups) ?> group(s):</strong></p>
                    <ul class="mb-3">
                      <?php foreach ($assembly_groups as $group): ?>
                        <li><span class="badge bg-info"><?= htmlspecialchars($group) ?></span></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php else: ?>
                    <p class="text-muted mb-3"><i class="fa fa-info-circle"></i> This assembly is not currently assigned to any groups.</p>
                  <?php endif; ?>
                  <a href="manage_groups.php" target="_blank" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-edit"></i> Manage Groups
                  </a>
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
              <button type="button" class="btn btn-danger" onclick="deleteCurrentAssemblyDirectory(event, '<?= htmlspecialchars($organism) ?>', '<?= htmlspecialchars($safe_asm_id) ?>')">
                <i class="fa fa-trash-alt"></i> Delete Assembly Directory
              </button>
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php endforeach; ?>


<!-- Backwards compatible with existing Organism Management Scripts -->


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
          <li><strong>Add database file:</strong> Upload or create <code>organism.sqlite</code></li>
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
          <li><strong>Viewing:</strong> Organisms are accessible via <code>/tools/organism_display.php?organism=Name</code></li>
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

  <!-- Back to Admin Dashboard Link (Bottom) -->
  <div class="mt-5 mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>
</div>

<!-- Organism Status Modals -->
<?php foreach ($organisms as $organism => $data): ?>
  <?php 
    $status = getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
    $safe_org_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism);
    $checks = $status['checks'];
    $pass_count = $status['pass_count'];
  ?>
  <div class="modal fade" id="statusModal<?= $safe_org_id ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="fa fa-star"></i> Status: <?= htmlspecialchars($organism) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="card mb-3">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fa fa-list-check"></i> <strong>Setup Checklist</strong></h6>
                <span class="badge bg-success fs-6"><?= $pass_count ?>/8 Complete</span>
              </div>
              
              <div class="list-group">
                <div class="list-group-item <?= $checks['has_assemblies'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['has_assemblies']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Has assemblies</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['has_fasta'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['has_fasta']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Has FASTA files</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['has_blast_indexes'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['has_blast_indexes']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Has BLAST indexes</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['has_database'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['has_database']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Has database file</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['database_readable'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['database_readable']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Database is readable</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['assemblies_in_groups'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['assemblies_in_groups']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Assemblies in groups</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['in_taxonomy_tree'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['in_taxonomy_tree']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>In taxonomy tree</strong></span>
                  </div>
                </div>
                
                <div class="list-group-item <?= $checks['metadata_complete'] ? '' : 'bg-light' ?>">
                  <div class="d-flex align-items-center">
                    <?php if ($checks['metadata_complete']): ?>
                      <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                    <?php else: ?>
                      <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                    <?php endif; ?>
                    <span><strong>Metadata complete</strong></span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<!-- Back to Admin Dashboard Link (Bottom) -->
<div class="container mt-5 mb-4">
  <a href="admin.php" class="btn btn-outline-secondary btn-sm">
    <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
  </a>
</div>

<?php
?>

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
        
        <p><strong>Status Checklist (10 Requirements):</strong> The system tracks 10 dimensions of organism readiness:</p>
        <ul>
          <li>Has assemblies</li>
          <li>Has FASTA files</li>
          <li>Has BLAST indexes</li>
          <li>Has FAI index</li>
          <li>Has database file</li>
          <li>Database is valid</li>
          <li>Assembly &amp; gene set dirs match DB</li>
          <li>In organism groups</li>
          <li>In taxonomy tree</li>
          <li>Metadata complete</li>
        </ul>
        
        <p><strong>Performance Note:</strong> This page always loads instantly from a pre-built cache.
        Click <strong>Refresh Cache</strong> to rebuild it in the background — the page reloads automatically
        when the scan finishes. You never need to wait for a scan during page load.</p>
        
        <p class="mb-0"><strong>What You Can Do:</strong></p>
        <ul class="mb-0">
          <li>View all organisms and their status at a glance</li>
          <li>Check database validity and readability</li>
          <li>Manage metadata (images, descriptions)</li>
          <li>Handle assembly directories and FASTA files</li>
          <li>Verify BLAST indexes are present</li>
          <li>Verify genome FAI index is present</li>
          <li>See which groups each organism belongs to</li>
          <li>Track overall setup completion with the 10-point checklist</li>
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
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> genome.fa (reference genome — shared)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> genome.fa.fai (samtools FAI index)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-folder folder-icon"></i> <strong>gene_set_name</strong> (e.g., v1, OGS1.0)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.cds.nt.fa (coding sequences)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.protein.aa.fa (proteins)<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i class="fa fa-file file-icon"></i> *.transcript.nt.fa (transcripts)
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
  
  <?php if (!empty($duplicate_taxon_ids)): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h5><i class="fa fa-exclamation-triangle"></i> Duplicate Taxon IDs Detected</h5>
      <p><strong>Problem:</strong> Multiple organisms share the same NCBI Taxon ID. This causes organisms to be missing from the taxonomy tree because they have identical lineages.</p>
      <p class="mb-2"><strong>Duplicates found:</strong></p>
      <ul class="mb-2">
        <?php foreach ($duplicate_taxon_ids as $taxon_id => $org_names): ?>
          <li>
            <strong>Taxon ID <?= htmlspecialchars($taxon_id) ?>:</strong>
            <?= implode(', ', array_map('htmlspecialchars', $org_names)) ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="mb-0"><strong>Solution:</strong> Verify the correct taxon IDs at <a href="https://www.ncbi.nlm.nih.gov/taxonomy" target="_blank" class="alert-link">NCBI Taxonomy Browser</a> and update the organism.json files using the metadata editor below.</p>
    </div>
  <?php endif; ?>

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
            <br><span class="badge bg-secondary"><i class="fa fa-dna"></i> Missing FAI Index</span> - <code>genome.fa.fai</code> missing; SVG sequence viewer unavailable
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
            <button class="btn btn-sm btn-outline-success"><i class="fa fa-check-circle"></i> Complete <span class="badge bg-success">10</span></button> - All 10 checks passed
            <br><button class="btn btn-sm btn-outline-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete <span class="badge bg-warning text-dark">X</span></button> - Some checks passed (see modal for details)
            <br><button class="btn btn-sm btn-outline-danger"><i class="fa fa-times-circle"></i> Critical <span class="badge bg-danger">0</span></button> - No checks passed (no database or no assemblies)
          </p>
          <p class="small text-muted"><i class="fa fa-info-circle"></i> <strong>Tip:</strong> Click the status button to see the detailed checklist of all 10 setup requirements.</p>
          <p class="small text-muted"><strong>Checklist includes:</strong> Assemblies • FASTA files • BLAST indexes • FAI index • Database file • Database readable • Assemblies in groups • Organism in tree • Metadata complete</p>
        </div>
      </div>
    </div>
  </div>


  <?php if (!empty($stale_organisms)): ?>
  <div class="alert alert-warning d-flex align-items-center justify-content-between gap-3 mb-4" id="staleBanner">
    <div>
      <i class="fa fa-exclamation-triangle me-2"></i>
      <strong><?= count($stale_organisms) === count($organisms) ? 'All organisms may be out of date' : count($stale_organisms) . ' organism' . (count($stale_organisms) > 1 ? 's' : '') . ' may be out of date' ?></strong>
      — <?= htmlspecialchars($cache_stale_reason) ?>.
      Rows marked <span class="badge bg-warning text-dark"><i class="fa fa-clock"></i> Stale</span> may show outdated status.
    </div>
    <button class="btn btn-warning btn-sm fw-bold flex-shrink-0" onclick="rescanOrganisms(this)" id="rescanBtnBanner">
      <i class="fa fa-sync-alt"></i> Update Cache
    </button>
  </div>
  <?php elseif (empty($organisms)): ?>
  <div class="alert alert-info mb-4">
    <i class="fa fa-info-circle me-2"></i>
    No cache found. Click <strong>Refresh Cache</strong> to scan all organisms.
  </div>
  <?php endif; ?>

  <?php
  // Pre-compute filter counts for the status filter bar
  $filter_counts = ['needs-attention' => 0, 'blast' => 0, 'fai' => 0,
                    'groups' => 0, 'tree' => 0, 'metadata' => 0, 'stale' => 0];
  foreach ($organisms as $_org => $_d) {
      $_c = $_d['overall_status']['checks'];
      if (!$_d['overall_status']['all_pass'])   $filter_counts['needs-attention']++;
      if (!$_c['has_blast_indexes'])            $filter_counts['blast']++;
      if (!$_c['has_fai_index'])                $filter_counts['fai']++;
      if (!$_c['assemblies_in_groups'])         $filter_counts['groups']++;
      if (!$_c['in_taxonomy_tree'])             $filter_counts['tree']++;
      if (!$_c['metadata_complete'])            $filter_counts['metadata']++;
      if (in_array($_org, $stale_organisms ?? [])) $filter_counts['stale']++;
  }
  unset($_org, $_d, $_c);
  ?>

  <!-- Current Organisms Table -->
  <div class="card">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">
        <i class="fa fa-list"></i> Current Organisms (<span id="organismCount"><?= count($organisms) ?></span>)
        <?php if ($cache_generated): ?>
          <small class="fw-normal ms-2 opacity-75" style="font-size:0.75rem;">
            cached <span id="cacheAge" data-generated="<?= htmlspecialchars($cache_generated) ?>"></span>
          </small>
        <?php else: ?>
          <small class="fw-normal ms-2 opacity-75" style="font-size:0.75rem;" id="cacheAge" data-generated="">no cache</small>
        <?php endif; ?>
      </h5>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span id="refreshStatus" class="text-white-50 small" style="display:none;"></span>
        <button id="rescanBtn" class="btn btn-sm btn-light" onclick="rescanOrganisms(this)" title="Rescan only organisms whose files changed since last cache">
          <i class="fa fa-sync-alt"></i> Refresh Cache
        </button>
        <button id="forceRescanBtn" class="btn btn-sm btn-outline-warning" onclick="forceRescanOrganisms()" title="Rescan all organisms regardless of cache state — use when the cache seems wrong">
          <i class="fa fa-redo"></i> Force Full Rescan
        </button>
        <span class="text-white-50 opacity-50">|</span>
        <span id="syncTaxonomyStatus" class="text-white-50 small" style="display:none;"></span>
        <button id="syncTaxonomyBtn" class="btn btn-sm btn-outline-info"
                onclick="syncNcbiTaxonomy(this, document.getElementById('syncTaxonomyStatus'))"
                title="Download NCBI taxonomy dump and populate lineage cache — eliminates per-organism API calls">
          <i class="fa fa-download"></i> Sync NCBI Taxonomy
        </button>
        <?php if ($lineage_cache_generated): ?>
          <small class="text-white-50" style="font-size:0.75rem;">
            synced <span id="taxonomySyncAge" data-generated="<?= htmlspecialchars($lineage_cache_generated) ?>"></span>
          </small>
        <?php endif; ?>
      </div>
    </div>
    <div class="card-body">
      <!-- Status filter bar -->
      <div class="mb-3 d-flex flex-wrap gap-2 align-items-center" id="statusFilterBar">
        <span class="text-muted small fw-bold me-1">Filter:</span>
        <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All (<?= count($organisms) ?>)</button>
        <?php if ($filter_counts['needs-attention'] > 0): ?>
          <button class="btn btn-sm btn-outline-warning" data-filter="needs-attention">Needs Attention (<?= $filter_counts['needs-attention'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['blast'] > 0): ?>
          <button class="btn btn-sm btn-outline-secondary" data-filter="blast">Missing BLAST (<?= $filter_counts['blast'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['fai'] > 0): ?>
          <button class="btn btn-sm btn-outline-secondary" data-filter="fai">Missing FAI (<?= $filter_counts['fai'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['groups'] > 0): ?>
          <button class="btn btn-sm btn-outline-danger" data-filter="groups">Not in Groups (<?= $filter_counts['groups'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['tree'] > 0): ?>
          <button class="btn btn-sm btn-outline-danger" data-filter="tree">Not in Tree (<?= $filter_counts['tree'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['metadata'] > 0): ?>
          <button class="btn btn-sm btn-outline-danger" data-filter="metadata">Metadata Incomplete (<?= $filter_counts['metadata'] ?>)</button>
        <?php endif; ?>
        <?php if ($filter_counts['stale'] > 0): ?>
          <button class="btn btn-sm btn-outline-warning" data-filter="stale">Stale (<?= $filter_counts['stale'] ?>)</button>
        <?php endif; ?>
      </div>

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
             <?php
               $is_stale   = in_array($organism, $stale_organisms ?? []);
               $row_status_info = $data['overall_status'];
               $row_checks = $row_status_info['checks'];
               $row_issue_map = [
                   'has_blast_indexes'    => 'blast',
                   'has_fai_index'        => 'fai',
                   'assemblies_in_groups' => 'groups',
                   'in_taxonomy_tree'     => 'tree',
                   'metadata_complete'    => 'metadata',
                   'has_assemblies'       => 'no-assemblies',
                   'has_fasta'            => 'no-fasta',
                   'has_database'         => 'no-database',
                   'database_valid'       => 'db-invalid',
                   'directories_match_db' => 'dir-mismatch',
               ];
               $row_issues = [];
               foreach ($row_issue_map as $chk => $lbl) {
                   if (isset($row_checks[$chk]) && !$row_checks[$chk]) $row_issues[] = $lbl;
               }
               if ($is_stale) $row_issues[] = 'stale';
               $row_status = $row_status_info['all_pass'] ? 'complete'
                           : ($row_status_info['pass_count'] > 0 ? 'incomplete' : 'critical');
             ?>
             <tr data-status="<?= $row_status ?>"
                 data-issues="<?= implode(' ', $row_issues) ?>"
                 <?= $is_stale ? 'class="table-warning"' : '' ?>>
               <td>
               <strong>
                 <a href="../tools/organism.php?organism=<?= urlencode($organism) ?>" target="_blank" title="View organism page">
                   <?= htmlspecialchars($organism) ?>
                   <i class="fa fa-external-link-alt" style="font-size: 0.85em; margin-left: 0.25em;"></i>
                 </a>
               </strong>
               <?php if ($is_stale): ?>
                 <span class="badge bg-warning text-dark ms-1" title="Files changed since last cache refresh"><i class="fa fa-clock"></i> Stale</span>
               <?php endif; ?>
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
                   $in_taxonomy_tree = $data['in_taxonomy_tree'] ?? isAssemblyInTaxonomyTree($organism, '', $taxonomy_tree_file);
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
                         
                         // Check if BLAST indexes are missing for FASTA files.
                         // BLAST indexes now live in gene_set subdirs; aggregate across all gene_sets.
                         $assembly_path = $data['path'] . '/' . $assembly;
                         $has_missing_blast_indexes = false;
                         if (isset($data['blast_validation'][$assembly])) {
                           $blast_validation = $data['blast_validation'][$assembly];
                           foreach ($blast_validation['databases'] ?? [] as $db) {
                             if (!$db['has_indexes']) { $has_missing_blast_indexes = true; break; }
                           }
                         } else {
                           foreach (glob($assembly_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
                             $bv = validateBlastIndexFiles($gs_dir, $sequence_types);
                             foreach ($bv['databases'] ?? [] as $db) {
                               if (!$db['has_indexes']) { $has_missing_blast_indexes = true; break 2; }
                             }
                           }
                         }

                         // Check if FAI index is missing
                         $has_missing_fai = false;
                         $fai_info_badge  = $data['fai_validation'][$assembly] ?? [
                           'genome_fa_exists' => file_exists($assembly_path . '/genome.fa'),
                           'fai_exists'       => file_exists($assembly_path . '/genome.fa.fai'),
                         ];
                         if ($fai_info_badge['genome_fa_exists'] && !$fai_info_badge['fai_exists']) {
                           $has_missing_fai = true;
                         }
                         
                         // Determine badge style
                         // Priority: name mismatch > missing blast indexes > missing fai > missing fasta files
                         $badge_class = 'bg-success';
                         if ($has_name_mismatch) {
                             $badge_class = 'bg-warning';
                         } elseif ($has_missing_blast_indexes) {
                             $badge_class = 'bg-secondary';
                         } elseif ($has_missing_fai) {
                             $badge_class = 'bg-secondary';
                         } elseif ($is_missing) {
                             $badge_class = 'bg-info';
                         }
                       ?>
                       <button class="btn btn-sm d-block w-100 text-start mb-1 <?= $badge_class ?> text-white" onclick="openOrganismModal('asm', <?= htmlspecialchars(json_encode($organism)) ?>, <?= htmlspecialchars(json_encode($assembly)) ?>)">
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
                       <button class="btn btn-sm btn-outline-success" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)">
                         <i class="fa fa-check-circle"></i> Ready
                       </button>
                     <?php elseif ($validation['readable'] && $validation['database_valid'] && !empty($validation['tables_present']) && $has_assembly_issues): ?>
                       <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)">
                         <i class="fa fa-exclamation-triangle"></i> Incomplete
                       </button>
                     <?php elseif (!$validation['readable']): ?>
                       <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)">
                         <i class="fa fa-lock"></i> Unreadable
                       </button>
                     <?php elseif (!$validation['database_valid']): ?>
                       <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)">
                         <i class="fa fa-times-circle"></i> Invalid
                       </button>
                       </button>
                     <?php else: ?>
                       <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)">
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
                     <button class="btn btn-sm btn-outline-success" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-check-circle"></i> Complete
                     </button>
                   <?php elseif ($json_val['exists'] && $json_val['readable'] && $json_val['valid_json'] && $json_val['has_required_fields'] && !$json_val['writable']): ?>
                     <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-lock"></i> Not Writable
                     </button>
                   <?php elseif (!$json_val['exists']): ?>
                     <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-times-circle"></i> Missing
                     </button>
                   <?php elseif (!$json_val['readable']): ?>
                     <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-lock"></i> Unreadable
                     </button>
                   <?php elseif (!$json_val['valid_json']): ?>
                     <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-times-circle"></i> Invalid JSON
                     </button>
                   <?php elseif (!$json_val['has_required_fields']): ?>
                     <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-exclamation-triangle"></i> Incomplete
                     </button>
                   <?php else: ?>
                     <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)">
                       <i class="fa fa-exclamation-triangle"></i> Issues
                     </button>
                   <?php endif; ?>
               </td>
               <td>
                 <?php
                   // Get comprehensive status (pre-computed in cache, fallback to live)
                   $status = $data['overall_status'] ?? getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
                   $pass_count = $status['pass_count'];
                   $total_count = $status['total_count'];
                   $safe_org_id = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism);
                 ?>
                 <?php if ($status['all_pass']): ?>
                   <button class="btn btn-sm btn-outline-success" onclick="openOrganismModal('status', <?= htmlspecialchars(json_encode($organism)) ?>)">
                     <i class="fa fa-check-circle"></i> Complete <span class="badge bg-success"><?= $total_count ?></span>
                   </button>
                 <?php elseif ($pass_count > 0): ?>
                   <button class="btn btn-sm btn-outline-warning" onclick="openOrganismModal('status', <?= htmlspecialchars(json_encode($organism)) ?>)">
                     <i class="fa fa-exclamation-triangle"></i> Incomplete <span class="badge bg-warning text-dark"><?= $pass_count ?></span>
                   </button>
                 <?php else: ?>
                   <button class="btn btn-sm btn-outline-danger" onclick="openOrganismModal('status', <?= htmlspecialchars(json_encode($organism)) ?>)">
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


<!-- Dynamic modal container for on-demand organism modals -->
<div id="dynamicModal" class="modal fade" tabindex="-1" aria-modal="true" role="dialog"></div>

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
          <li><strong>Create gene set directory:</strong> <code>mkdir Genus_species/assembly_name/gene_set_name</code> (e.g., <code>v1</code>, <code>OGS1.0</code>)</li>
          <li><strong>Upload FASTA files:</strong> Add CDS, protein, and transcript files to the gene set directory; place <code>genome.fa</code> in the assembly directory</li>
          <li><strong>Generate genome FAI index:</strong> Required for the SVG gene model sequence viewer:
            <pre class="bg-light p-2 mt-1 mb-0 rounded small">cd <?= htmlspecialchars($organism_data) ?>/Genus_species/assembly_name
samtools faidx genome.fa</pre>
          </li>
          <li><strong>Build BLAST indexes:</strong> Use the Organism Checklist → BLAST step, or run <code>makeblastdb</code> manually inside each gene set directory</li>
          <li><strong>Assign to groups:</strong> Use "Manage Groups" to make the organism accessible</li>
        </ol>
        
        <h6 class="fw-bold mt-4">Required Files:</h6>
        <p class="mb-1"><strong>Assembly directory</strong> (<code>assembly_name/</code>):</p>
        <ul>
          <li><code>genome.fa</code> - Reference genome assembly (shared across gene sets)</li>
          <li><code>genome.fa.fai</code> - samtools FAI index (generated via <code>samtools faidx genome.fa</code>)</li>
        </ul>
        <p class="mb-1"><strong>Gene set directory</strong> (<code>assembly_name/gene_set_name/</code>):</p>
        <ul>
          <li><code>*.cds.nt.fa</code> - Coding sequences (nucleotide)</li>
          <li><code>*.protein.aa.fa</code> - Protein sequences (amino acid)</li>
          <li><code>*.transcript.nt.fa</code> - Transcript sequences (nucleotide)</li>
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

<!-- Back to Admin Dashboard Link (Bottom) -->
<div class="container mt-5 mb-4">
  <a href="admin.php" class="btn btn-outline-secondary btn-sm">
    <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
  </a>
</div>

<?php
?>

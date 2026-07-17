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

        <p><strong>After an <code>rsync</code>, <code>cp -p</code>, <code>tar</code> extract, or restore-from-backup:</strong>
        those tools preserve the original file timestamps. The change detection now also compares file
        <em>size</em>, so a re-synced organism is normally caught automatically — but if the checklist ever
        looks out of date after such a copy, click <strong>Force Rescan</strong> to rebuild the cache from
        scratch regardless of timestamps.</p>

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
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10 d-flex align-items-center" role="button"
         data-bs-toggle="collapse" data-bs-target="#orgDataMgmtBody"
         aria-expanded="false" aria-controls="orgDataMgmtBody" style="cursor:pointer;">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> Organism Data Management</h5>
      <i class="fa fa-chevron-down ms-auto"></i>
    </div>
    <div class="card-body collapse" id="orgDataMgmtBody">
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
      <p><strong>Problem:</strong> Multiple organisms share the same NCBI Taxon ID. Each taxon ID maps to one lineage entry, so when two organisms share one, only the first will appear in the homepage organism selector. The Taxon ID is also used to fetch classification data (kingdom, phylum, class, etc.) and organism images — duplicates cause incorrect or missing data for the affected organisms.</p>
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
  <!-- Data Health Issues (shared partial — identical card on the Admin Dashboard) -->
  <?php include __DIR__ . '/_data_health_card.php'; ?>

  <?php if (!empty($stale_organisms)): ?>
  <!-- The "cache out of date" warning + Update Cache button live in the Data Health card
       above; this stays only to explain the per-row Stale badges on the table below. -->
  <div class="alert alert-secondary d-flex align-items-center gap-2 mb-4 py-2" id="staleBanner">
    <i class="fa fa-clock text-warning"></i>
    <small>
      Rows marked <span class="badge bg-warning text-dark"><i class="fa fa-clock"></i> Stale</span>
      changed since the cache was built and may show outdated status — refresh via <strong>Update Cache</strong> in the notice above.
    </small>
  </div>
  <?php elseif (empty($organisms)): ?>
  <div class="alert alert-info mb-4">
    <i class="fa fa-info-circle me-2"></i>
    No cache found. Click <strong>Refresh Cache</strong> to scan all organisms.
  </div>
  <?php endif; ?>

  <?php
  // Per-organism FASTA gaps, read from the cached fasta_validation. Returns whether
  // any assembly is missing genome.fa, and whether any is missing a non-genome
  // sequence type (transcript/cds/protein — "other FASTA"). Reused by the filter
  // counts below and the per-row data-issues tokens.
  $fasta_gaps = function (array $data): array {
      $missing_genome = false;
      $missing_other  = false;
      foreach ($data['fasta_validation']['assemblies'] ?? [] as $asm) {
          foreach ($asm['fasta_files'] ?? [] as $type => $info) {
              if (!empty($info['found'])) continue;
              if ($type === 'genome') $missing_genome = true;
              else                    $missing_other  = true;
          }
      }
      return ['genome_fa' => $missing_genome, 'other_fasta' => $missing_other];
  };

  // Pre-compute filter counts for the status filter bar
  $filter_counts = ['needs-attention' => 0, 'blast' => 0, 'fai' => 0,
                    'missing-genome-fa' => 0, 'missing-other-fasta' => 0,
                    'groups' => 0, 'tree' => 0, 'metadata' => 0, 'stale' => 0];
  foreach ($organisms as $_org => $_d) {
      $_c = $_d['overall_status']['checks'];
      $_g = $fasta_gaps($_d);
      if (!$_d['overall_status']['all_pass'])   $filter_counts['needs-attention']++;
      if (!$_c['has_blast_indexes'])            $filter_counts['blast']++;
      if (!$_c['has_fai_index'])                $filter_counts['fai']++;
      if ($_g['genome_fa'])                     $filter_counts['missing-genome-fa']++;
      if ($_g['other_fasta'])                   $filter_counts['missing-other-fasta']++;
      if (!$_c['assemblies_in_groups'])         $filter_counts['groups']++;
      if (!$_c['in_taxonomy_tree'])             $filter_counts['tree']++;
      if (!$_c['metadata_complete'])            $filter_counts['metadata']++;
      if (in_array($_org, $stale_organisms ?? [])) $filter_counts['stale']++;
  }
  unset($_org, $_d, $_c, $_g);
  ?>

  <!-- Legend & Status Guide — compact, collapsed by default, sits directly above the table -->
  <div class="card mb-2">
    <div class="card-header bg-light d-flex align-items-center py-2" role="button"
         data-bs-toggle="collapse" data-bs-target="#legendContent"
         aria-expanded="false" aria-controls="legendContent" style="cursor:pointer;">
      <i class="fa fa-book text-muted me-2"></i>
      <span class="fw-semibold">Legend &amp; Status Guide</span>
      <span class="ms-2 d-inline-flex gap-1">
        <span class="badge bg-success rounded-circle p-1">&nbsp;</span>
        <span class="badge bg-warning rounded-circle p-1">&nbsp;</span>
        <span class="badge bg-danger rounded-circle p-1">&nbsp;</span>
      </span>
      <i class="fa fa-chevron-down ms-auto text-muted"></i>
    </div>
    <div class="collapse" id="legendContent">
      <div class="card-body py-3">
        <div class="row g-3 small">
          <div class="col-md-6 col-xl-3">
            <h6 class="fw-bold mb-2"><i class="fa fa-folder text-muted"></i> Assemblies</h6>
            <div class="d-flex flex-column gap-1">
              <div><span class="badge bg-success"><i class="fa fa-check-circle"></i> Complete</span> <span class="text-muted">dir + valid FASTA</span></div>
              <div><span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> Name Mismatch</span> <span class="text-muted">dir ≠ DB genome name</span></div>
              <div><span class="badge bg-secondary"><i class="fa fa-rocket"></i> Missing BLAST</span> <span class="text-muted">FASTA present, no indexes</span></div>
              <div><span class="badge bg-secondary"><i class="fa fa-dna"></i> Missing FAI</span> <span class="text-muted"><code>genome.fa.fai</code> absent</span></div>
              <div><span class="badge bg-info"><i class="fa fa-times-circle"></i> Missing Files</span> <span class="text-muted">required FASTA absent</span></div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <h6 class="fw-bold mb-2"><i class="fa fa-database text-muted"></i> Database</h6>
            <div class="d-flex flex-column gap-1">
              <div><span class="badge bg-success"><i class="fa fa-check-circle"></i> Ready</span> <span class="text-muted">exists, readable, valid</span></div>
              <div><span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete</span> <span class="text-muted">valid, assembly issues</span></div>
              <div><span class="badge bg-danger"><i class="fa fa-lock"></i> Unreadable</span> <span class="text-muted">server can't read file</span></div>
              <div><span class="badge bg-danger"><i class="fa fa-times-circle"></i> Invalid</span> <span class="text-muted">corrupted / bad schema</span></div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <h6 class="fw-bold mb-2"><i class="fa fa-file-code text-muted"></i> Metadata</h6>
            <div class="d-flex flex-column gap-1">
              <div><span class="badge bg-success"><i class="fa fa-check-circle"></i> Complete</span> <span class="text-muted">all fields, writable</span></div>
              <div><span class="badge bg-warning"><i class="fa fa-lock"></i> Not Writable</span> <span class="text-muted">complete but read-only</span></div>
              <div><span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete</span> <span class="text-muted">valid, missing fields</span></div>
              <div><span class="badge bg-danger"><i class="fa fa-times-circle"></i> Missing / Invalid</span> <span class="text-muted">absent or bad JSON</span></div>
            </div>
          </div>
          <div class="col-md-6 col-xl-3">
            <h6 class="fw-bold mb-2"><i class="fa fa-star text-muted"></i> Overall</h6>
            <div class="d-flex flex-column gap-1">
              <div><span class="badge bg-success"><i class="fa fa-check-circle"></i> Complete <span class="badge bg-light text-success">10</span></span> <span class="text-muted">all 10 checks pass</span></div>
              <div><span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> Incomplete <span class="badge bg-light text-dark">X</span></span> <span class="text-muted">some pass</span></div>
              <div><span class="badge bg-danger"><i class="fa fa-times-circle"></i> Critical <span class="badge bg-light text-danger">0</span></span> <span class="text-muted">none pass</span></div>
            </div>
          </div>
        </div>
        <p class="small text-muted mb-0 mt-3">
          <i class="fa fa-info-circle"></i> Click any status badge in a row for details and fixes.
          <strong>10-point checklist:</strong> Assemblies • FASTA files • BLAST indexes • FAI index • Database file • Database readable • Assemblies in groups • Organism in tree • Metadata complete
        </p>
      </div>
    </div>
  </div>

  <!-- Current Organisms Table -->
  <div class="card border-primary">
    <div class="card-header bg-primary bg-opacity-10">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <h5 class="mb-0">
            <i class="fa fa-list"></i> Current Organisms (<span id="organismCount"><?= count($organisms) ?></span>)
          </h5>
          <small class="fw-normal text-muted" style="font-size:0.72rem;">
            <?php if ($cache_generated): ?>
              cached <span id="cacheAge" data-generated="<?= htmlspecialchars($cache_generated) ?>"></span>
            <?php else: ?>
              <span id="cacheAge" data-generated="">no cache</span>
            <?php endif; ?>
            <?php if ($lineage_cache_generated): ?>
              &nbsp;&middot;&nbsp; NCBI taxonomy synced <span id="taxonomySyncAge" data-generated="<?= htmlspecialchars($lineage_cache_generated) ?>"></span>
            <?php endif; ?>
          </small>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <span id="refreshStatus" class="text-muted small" style="display:none;"></span>
          <span id="syncTaxonomyStatus" class="text-muted small" style="display:none;"></span>
          <button id="rescanBtn" class="btn btn-sm btn-primary" onclick="rescanOrganisms(this)" title="Rescan only organisms whose files changed since last cache">
            <i class="fa fa-sync-alt"></i> Refresh Cache
          </button>
          <button id="forceRescanBtn" class="btn btn-sm btn-outline-secondary" onclick="forceRescanOrganisms()" title="Rescan all organisms regardless of cache state — use when the cache seems wrong">
            <i class="fa fa-redo"></i> Force Full Rescan
          </button>
          <button id="syncTaxonomyBtn" class="btn btn-sm btn-outline-secondary"
                  onclick="syncNcbiTaxonomy(this, document.getElementById('syncTaxonomyStatus'))"
                  title="Download NCBI taxonomy dump and populate lineage cache — eliminates per-organism API calls">
            <i class="fa fa-download"></i> Sync NCBI Taxonomy
          </button>
          <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#taskHelpPanel"
                  title="When to run each task">
            <i class="fa fa-info-circle"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Task help panel — collapsed by default -->
    <div class="collapse" id="taskHelpPanel">
      <div class="border-bottom px-3 py-2" style="background:#f0f4ff; font-size:0.85rem; line-height:1.5;">
        <div class="row g-3">
          <div class="col-md-4">
            <div class="d-flex gap-2">
              <span class="mt-1 text-primary"><i class="fa fa-sync-alt fa-fw"></i></span>
              <div>
                <strong>Refresh Cache</strong><br>
                <span class="text-muted">Run after any change to organism files — editing <code>organism.json</code>, uploading a new GFF, rebuilding BLAST indexes, or adding a new organism directory. Scans only what changed; fast even with many organisms.</span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex gap-2">
              <span class="mt-1 text-warning"><i class="fa fa-redo fa-fw"></i></span>
              <div>
                <strong>Force Full Rescan</strong><br>
                <span class="text-muted">Use when the cache looks wrong and a normal refresh didn't fix it — for example after bulk-moving organism directories, restoring from backup, or if organism counts seem off. Slower: re-scans everything regardless of timestamps.</span>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="d-flex gap-2">
              <span class="mt-1 text-info"><i class="fa fa-download fa-fw"></i></span>
              <div>
                <strong>Sync NCBI Taxonomy</strong><br>
                <span class="text-muted">Run when adding an organism that has a <code>taxon_id</code> in its <code>organism.json</code>. Downloads the NCBI taxonomy dump (~60 MB) on first run; subsequent runs reuse the local copy and only re-download if NCBI has updated it. Housekeeping checks for updates monthly in the background.</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card-body">
      <!-- Status filter bar -->
      <div class="mb-3 d-flex flex-wrap gap-2 align-items-center" id="statusFilterBar">
        <span class="text-muted small fw-bold me-1">Filter:</span>
        <button class="btn btn-sm btn-outline-secondary active" data-filter="all">All (<?= count($organisms) ?>)</button>
        <?php
          // Every filter is shown, always, with its count — even 0. A 0-count filter is
          // disabled + muted so the full set of data-completeness lenses stays visible at
          // a glance without cluttering the bar with clickable dead ends.
          $filter_defs = [
            ['needs-attention',     'Needs Attention',      'warning',   'Any organism that is not fully complete'],
            ['missing-genome-fa',   'Missing genome FASTA', 'info',      'No reference genome (genome.fa). Often expected — transcriptome/proteome-only organisms have none.'],
            ['missing-other-fasta', 'Missing other FASTA',  'info',      'A gene-set FASTA (protein / transcript / CDS) is missing'],
            ['blast',               'Missing BLAST',        'secondary', 'One or more BLAST indexes are missing'],
            ['fai',                 'Missing FAI',          'secondary', 'genome.fa is present but its .fai index is missing'],
            ['groups',              'Not in Groups',        'danger',    'Assembly is not in any group — invisible to users'],
            ['tree',                'Not in Tree',          'danger',    'Not in the taxonomy tree — hidden from the homepage organism selector'],
            ['metadata',            'Metadata Incomplete',  'danger',    'organism.json is missing required fields or is not writable'],
            ['stale',               'Stale',                'warning',   'Files changed since the last cache refresh'],
          ];
          foreach ($filter_defs as [$key, $label, $style, $title]):
            $n = $filter_counts[$key] ?? 0;
        ?>
          <button class="btn btn-sm btn-outline-<?= $n === 0 ? 'secondary' : $style ?>" data-filter="<?= $key ?>"
                  title="<?= htmlspecialchars($title) ?>"<?= $n === 0 ? ' disabled' : '' ?>>
            <?= htmlspecialchars($label) ?> (<?= $n ?>)
          </button>
        <?php endforeach; ?>
      </div>

      <table id="organismsTable" class="table table-striped table-hover">
         <thead>
           <tr>
             <th>Organism</th>
             <th>Common Name</th>
             <th>Groups</th>
             <th>Assemblies</th>
             <th>Health</th>
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
               $row_gaps = $fasta_gaps($data);
               if ($row_gaps['genome_fa'])   $row_issues[] = 'missing-genome-fa';
               if ($row_gaps['other_fasta']) $row_issues[] = 'missing-other-fasta';
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
                 <br>
                 <button class="btn btn-sm btn-outline-secondary" type="button" onclick="togglePath(this, '<?= htmlspecialchars($data['path']) ?>', '<?= htmlspecialchars($organism) ?>')">
                   <i class="fa fa-folder"></i> View Path
                 </button>
                 <button class="btn btn-sm btn-outline-secondary" type="button"
                         onclick="rescanSingleOrganism(this, <?= htmlspecialchars(json_encode($organism)) ?>)"
                         title="Force rescan this organism only">
                   <i class="fa fa-sync-alt"></i> Rescan
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
               <?php
                 $safe_groups_id = 'groups-' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism);
                 $in_groups = $row_checks['assemblies_in_groups'] ?? false;
                 $org_group_list = $organism_groups_lookup[$organism] ?? [];
               ?>
               <td id="<?= $safe_groups_id ?>">
                 <?php if (!empty($org_group_list)): ?>
                   <div class="d-flex flex-wrap gap-1">
                     <?php foreach ($org_group_list as $og): ?>
                       <span class="badge bg-secondary"><?= htmlspecialchars($og) ?></span>
                     <?php endforeach; ?>
                   </div>
                 <?php endif; ?>
                 <?php if (!$in_groups): ?>
                   <button class="btn btn-sm btn-outline-primary mt-1"
                           onclick="openQuickAddGroupModal(<?= htmlspecialchars(json_encode($organism)) ?>, <?= htmlspecialchars(json_encode($safe_groups_id)) ?>)">
                     <i class="fa fa-plus"></i> Add to Group
                   </button>
                 <?php endif; ?>
               </td>
               <td>
                 <span class="badge bg-secondary"><?= count($data['assemblies']) ?> <?= count($data['assemblies']) === 1 ? 'assembly' : 'assemblies' ?></span>
                 <?php if (!empty($row_gaps['genome_fa'])): ?>
                   <span class="badge rounded-pill bg-light text-secondary border" title="No reference genome (genome.fa) — transcriptome / proteome data only. A valid data shape, not an error."><i class="fa fa-dna"></i> Transcriptome only</span>
                 <?php endif; ?>
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
                 <?php
                   // ── Consolidated Health cell (replaces the old Tree/DB/Metadata/Status columns) ──
                   $status = $data['overall_status'] ?? getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
                   $hstate = $status['all_pass']       ? ['success', 'check-circle',        'Complete']
                           : ($status['pass_count'] > 0 ? ['warning', 'exclamation-triangle', 'Incomplete']
                                                        : ['danger',  'times-circle',         'Critical']);
                   // DB inspector tint (mirrors the old DB Status column's states)
                   $db_class = 'secondary';
                   if ($data['db_validation']) {
                       $v = $data['db_validation']; $ai = $data['assembly_validation'];
                       $db_bad_dirs = $ai && (!$ai['valid'] || !empty($ai['mismatches']));
                       if ($v['readable'] && $v['database_valid'] && !empty($v['tables_present']) && !$db_bad_dirs) $db_class = 'success';
                       elseif (!$v['readable'] || !$v['database_valid'])                                          $db_class = 'danger';
                       else                                                                                       $db_class = 'warning';
                   }
                   // Metadata inspector tint
                   $jv = $data['json_validation'];
                   $meta_ok = $jv['exists'] && $jv['readable'] && $jv['valid_json'] && $jv['has_required_fields'] && $jv['writable'];
                   $meta_class = $meta_ok ? 'success' : ((!$jv['exists'] || !$jv['readable'] || !$jv['valid_json']) ? 'danger' : 'warning');
                   // Specific problems to spell out. The neutral "Transcriptome only" tag lives in
                   // the Assemblies cell, so 'missing-genome-fa' is intentionally NOT listed here.
                   $issue_labels = [
                     'no-assemblies'       => ['No assemblies',          'danger'],
                     'no-fasta'            => ['No FASTA',               'danger'],
                     'no-database'         => ['No database',            'danger'],
                     'db-invalid'          => ['DB invalid',             'danger'],
                     'dir-mismatch'        => ['Dir / DB mismatch',      'warning'],
                     'blast'               => ['No BLAST index',         'warning'],
                     'fai'                 => ['No FAI index',           'warning'],
                     'groups'              => ['Not in any group',       'warning'],
                     'tree'                => ['Not in taxonomy tree',   'warning'],
                     'metadata'            => ['Metadata incomplete',    'warning'],
                     'missing-other-fasta' => ['Missing gene-set FASTA', 'warning'],
                     'stale'               => ['Stale — rescan',         'warning'],
                   ];
                   $visible_issues = array_values(array_filter($row_issues, fn($i) => isset($issue_labels[$i])));
                 ?>
                 <button class="btn btn-sm btn-outline-<?= $hstate[0] ?>" onclick="openOrganismModal('status', <?= htmlspecialchars(json_encode($organism)) ?>)" title="Open the setup checklist">
                   <i class="fa fa-<?= $hstate[1] ?>"></i> <?= $hstate[2] ?>
                 </button>
                 <?php if ($visible_issues): ?>
                   <div class="mt-1 d-flex flex-wrap gap-1">
                     <?php foreach ($visible_issues as $iss): [$lbl, $sev] = $issue_labels[$iss]; ?>
                       <span class="badge bg-<?= $sev === 'danger' ? 'danger' : 'warning text-dark' ?>"><?= htmlspecialchars($lbl) ?></span>
                     <?php endforeach; ?>
                   </div>
                 <?php endif; ?>
                 <div class="mt-1 d-flex gap-1">
                   <button class="btn btn-sm btn-outline-<?= $db_class ?> px-2 py-0" onclick="openOrganismModal('db', <?= htmlspecialchars(json_encode($organism)) ?>)" title="Database details &amp; row counts"><i class="fa fa-database"></i></button>
                   <button class="btn btn-sm btn-outline-<?= $meta_class ?> px-2 py-0" onclick="openOrganismModal('metadata', <?= htmlspecialchars(json_encode($organism)) ?>)" title="Metadata &amp; editor"><i class="fa fa-file-alt"></i></button>
                 </div>
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
        <div class="col-md-6">
          <div class="d-grid">
            <a href="manage_groups.php" target="_blank" class="btn btn-primary">
              <i class="fa fa-layer-group"></i> Manage Groups & Descriptions
            </a>
            <small class="text-muted mt-2">Manage group metadata</small>
          </div>
        </div>
        <div class="col-md-6">
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

<!-- Quick Add Group Modal -->
<div class="modal fade" id="quickAddGroupModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-layer-group"></i> Add to Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="text-muted small mb-2">Organism: <strong id="quickAddOrgName"></strong></p>
        <label class="form-label fw-bold" for="quickAddGroupName">Group Name</label>
        <input type="text" class="form-control" id="quickAddGroupName"
               list="quickAddGroupList" placeholder="e.g. Bats, Public, Insects"
               onkeydown="if(event.key==='Enter') submitQuickAddGroup()">
        <datalist id="quickAddGroupList"></datalist>
        <div id="quickAddResult" class="mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" id="quickAddSubmitBtn" onclick="submitQuickAddGroup()">
          <i class="fa fa-plus"></i> Add Group
        </button>
      </div>
    </div>
  </div>
</div>

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

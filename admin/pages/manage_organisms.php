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
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutOrganismManagement">
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
          <li>Track overall setup completion with the setup checklist</li>
        </ul>
      </div>
    </div>
  </div>

  <!-- Information Panel -->
  <div class="card mb-4">
    <div class="card-header adm-head d-flex align-items-center" role="button"
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
  // The issue tokens one organism carries -- keys of moop_organism_issue_defs(), which
  // gives each its label and severity. Worked out ONCE per row, above the table: the
  // filter counts and the row's data-issues used to be two separate hand-written
  // computations of the same thing.
  $db_issue_tags = moop_db_content_issue_tags();
  $db_content_codes = moop_db_content_issue_codes();  // also tints the Health cell's DB icon
  $org_issues = function (string $organism, array $data) use ($db_issue_tags, $stale_organisms): array {
      $checks = $data['overall_status']['checks'];
      $issues = [];
      foreach (moop_organism_check_defs() as $chk => $def) {
          if (isset($checks[$chk]) && !$checks[$chk]) $issues[] = $def['issue'];
      }
      // The generic 'database_valid' check collapses every data problem into one
      // "DB invalid" badge. When the validator named a specific cause, say that
      // instead: an organism whose database is structurally perfect and merely
      // EMPTY is not invalid, and that badge sends the admin hunting the wrong
      // problem. Keyed on data_issue_codes, never on the prose (CLAUDE.md 9b).
      $codes = $data['db_validation']['data_issue_codes'] ?? [];
      $named = [];
      foreach ($codes as $code) {
          if (isset($db_issue_tags[$code])) $named[] = $db_issue_tags[$code];
      }
      // Only drop the generic badge when EVERY issue has a specific name -- an
      // unmapped referential issue must keep its 'DB invalid' or it vanishes.
      if ($named && !array_diff($codes, array_keys($db_issue_tags))) {
          $issues = array_values(array_diff($issues, ['db-invalid']));
      }
      $issues = array_merge($issues, $named);
      if ($named) $issues[] = 'db-content';
      // No genome: an assembly that HAS gene-set data but no genome.fa. An empty
      // assembly is not "no genome" -- it is a placeholder, and gene-set-files covers it.
      foreach ($data['fasta_validation']['assemblies'] ?? [] as $asm) {
          if (!empty($asm['gaps']['gene_sets']) && empty($asm['gaps']['has_genome'])) { $issues[] = 'no-genome'; break; }
      }
      if (in_array($organism, $stale_organisms ?? [], true)) $issues[] = 'stale';
      return $issues;
  };

  // One assembly's state for the Assemblies tree, split by LEVEL so each problem shows on
  // the line that owns it: name / genome / .fai on the assembly line, FASTA and BLAST
  // on each gene-set line. Read from the organism cache. Missing types are LABELS
  // ("CDS", "mRNA") -- the site's own names, never the file pattern.
  $asm_state = function (array $data, string $assembly) use ($sequence_types): array {
      $path  = $data['path'] . '/' . $assembly;
      $gaps  = $data['fasta_validation']['assemblies'][$assembly]['gaps']
             ?? assembly_fasta_gaps($path, $sequence_types);
      $label = fn($t) => $sequence_types[$t]['label'] ?? $t;

      // Directory name vs the database's genome records.
      $matched = null;
      $av = $data['assembly_validation'];
      foreach ($av['genomes'] ?? [] as $g) {
          if ($assembly === $g['genome_name'] || $assembly === $g['genome_accession']) { $matched = $g; break; }
      }

      $fai = $data['fai_validation'][$assembly] ?? [
          'genome_fa_exists' => file_exists("$path/" . genome_fasta_filename()),
          'fai_exists'       => file_exists("$path/" . genome_fasta_filename() . '.fai'),
      ];

      // BLAST: each index entry names its FASTA's path, and so its gene set.
      $dbs = $data['blast_validation'][$assembly]['databases'] ?? null;
      if ($dbs === null) {
          $dbs = [];
          foreach (array_keys($gaps['gene_sets']) as $gs) {
              $dbs = array_merge($dbs, validateBlastIndexFiles("$path/$gs", $sequence_types)['databases'] ?? []);
          }
      }
      $unindexed = [];
      foreach ($dbs as $db) {
          if (!$db['has_indexes']) $unindexed[basename(dirname($db['fasta_path']))][] = $label($db['type']);
      }

      $gene_sets = [];
      foreach ($gaps['gene_sets'] as $gs => $missing) {
          $gene_sets[$gs] = ['missing' => array_map($label, $missing), 'unindexed' => $unindexed[$gs] ?? []];
      }
      return [
          'display'    => $matched ? assembly_label($matched['genome_name'] ?? '', $matched['genome_accession'] ?? '') : $assembly,
          'mismatch'   => $av && $matched === null,
          'has_genome' => $gaps['has_genome'],
          'no_fai'     => $fai['genome_fa_exists'] && !$fai['fai_exists'],
          'gene_sets'  => $gene_sets,
      ];
  };

  // Gene-set types a complete gene set holds, for the ✓ tooltip.
  $gs_type_labels = [];
  foreach ($sequence_types as $__t => $__cfg) if ($__t !== 'genome') $gs_type_labels[] = $__cfg['label'] ?? $__t;

  // Per row, once: issue tokens and severity. The filter bar counts and the table rows
  // below both read these, so a count cannot disagree with what its filter shows.
  $issue_defs    = moop_organism_issue_defs();
  $row_tokens    = [];
  $row_severity  = [];
  $filter_counts = ['critical' => 0, 'incomplete' => 0];
  foreach ($organisms as $_org => $_d) {
      $row_tokens[$_org]   = $org_issues($_org, $_d);
      $row_severity[$_org] = moop_organism_severity($_d['overall_status']);
      if (isset($filter_counts[$row_severity[$_org]])) $filter_counts[$row_severity[$_org]]++;
      foreach ($row_tokens[$_org] as $_t) $filter_counts[$_t] = ($filter_counts[$_t] ?? 0) + 1;
  }
  unset($_org, $_d, $_t);
  ?>

  <!-- Current Organisms Table -->
  <div class="card">
    <div class="card-header adm-head">
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
        <button class="btn btn-sm org-filter sev-all active" data-filter="all">All <span class="org-filter-n"><?= count($organisms) ?></span></button>
        <?php
          // Severity first: Critical / Incomplete match the Health button on each row.
          // Then one button per specific issue, colored by ITS severity from
          // moop_organism_issue_defs() -- red always means critical, amber incomplete,
          // grey informational. Issues no organism has are left out rather than shown
          // disabled: with ~15 kinds of issue, a bar of greyed-out zeros was noise.
          foreach (['critical' => 'Critical', 'incomplete' => 'Incomplete'] as $sev => $label):
            $n = $filter_counts[$sev] ?? 0;
        ?>
          <button class="btn btn-sm org-filter sev-<?= $sev ?>" data-filter="<?= $sev ?>"
                  title="Organisms whose Health is <?= $label ?>"<?= $n === 0 ? ' disabled' : '' ?>>
            <?= $label ?> <span class="org-filter-n"><?= $n ?></span>
          </button>
        <?php endforeach; ?>
        <span class="vr mx-1"></span>
        <?php foreach (['critical', 'incomplete', 'info'] as $sev):
                foreach ($issue_defs as $key => $def):
                  if (!$def['filter'] || $def['severity'] !== $sev || empty($filter_counts[$key])) continue; ?>
          <button class="btn btn-sm org-filter sev-<?= $sev ?>" data-filter="<?= $key ?>"
                  title="<?= htmlspecialchars($def['title']) ?>">
            <?= htmlspecialchars($def['label']) ?> <span class="org-filter-n"><?= $filter_counts[$key] ?></span>
          </button>
        <?php endforeach; endforeach; ?>
      </div>

      <!-- Key. The table is meant to read without it -- each line says what is missing in
           words -- so this is a one-line reminder, not a guide. It replaced a four-column
           legend that described solid badges the table no longer drew. -->
      <div class="org-key small d-flex flex-wrap align-items-center gap-2 mb-3">
        <span><i class="fa fa-folder"></i> assembly</span>
        <span><span class="text-muted">└</span> <i class="fa fa-stream"></i> gene set</span>
        <span class="vr"></span>
        <span><i class="fa fa-check text-success"></i> nothing missing</span>
        <span><span class="badge rounded-pill border text-dark bg-warning-subtle">No CDS</span> chips name what is missing</span>
        <span class="vr"></span>
        <span>Color: <span class="text-danger fw-semibold">red</span> can't serve data ·
          <span class="text-warning-emphasis fw-semibold">amber</span> incomplete ·
          <span class="fw-semibold">gray</span> just so you know</span>
        <span class="vr"></span>
        <span>Click a line for details</span>
      </div>

      <table id="organismsTable" class="table table-striped table-hover">
         <thead>
           <tr>
             <th>Organism</th>
             <th>Groups</th>
             <th>Assemblies</th>
             <th>Health</th>
           </tr>
         </thead>
         <tbody>
           <?php foreach ($organisms as $organism => $data): ?>
             <?php
               $is_stale   = in_array($organism, $stale_organisms ?? []);
               $row_checks = $data['overall_status']['checks'];
               $row_issues = $row_tokens[$organism];
               $row_status = $row_severity[$organism];
             ?>
             <tr data-status="<?= $row_status ?>"
                 data-issues="<?= implode(' ', $row_issues) ?>"
                 <?= $is_stale ? 'class="table-warning"' : '' ?>>
               <td>
                 <?php
                   // Display the pretty scientific name as the link; fall back to the directory
                   // name (underscores → spaces) if genus/species metadata is missing.
                   $pretty_name = (!empty($data['info']['genus']) && !empty($data['info']['species']))
                                ? $data['info']['genus'] . ' ' . $data['info']['species']
                                : str_replace('_', ' ', $organism);
                 ?>
                 <a href="../tools/organism.php?organism=<?= urlencode($organism) ?>" target="_blank"
                    class="fw-semibold fst-italic" title="<?= htmlspecialchars($organism) ?> — view organism page">
                   <?= htmlspecialchars($pretty_name) ?>
                   <i class="fa fa-external-link-alt" style="font-size: 0.85em; margin-left: 0.25em;"></i>
                 </a>
                 <?php if ($is_stale): ?>
                   <span class="badge bg-warning text-dark ms-1" title="Files changed since last cache refresh"><i class="fa fa-clock"></i> Stale</span>
                 <?php endif; ?>
                 <?php if (!empty($data['info']['common_name'])): ?>
                   <br><small class="text-muted"><?= htmlspecialchars($data['info']['common_name']) ?></small>
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
                 <?php
                   // Assembly → gene set tree. Each line carries only its own level's state:
                   // a ✓ when nothing is missing, chips naming what is. Every chip is amber
                   // because every one is an Incomplete-level problem -- color means SEVERITY
                   // on this whole page (moop_severity_color()), and the chip's text says what
                   // kind. A healthy organism reads as plain text and ticks.
                 ?>
                 <?php if (!empty($data['assemblies'])): ?>
                   <div class="asm-tree">
                     <?php foreach ($data['assemblies'] as $assembly):
                       $s = $asm_state($data, $assembly);
                       $asm_chips = [];
                       if ($s['mismatch'])   $asm_chips[] = ['Name not in DB', 'The directory name matches no genome record in the database'];
                       if (!$s['gene_sets']) $asm_chips[] = ['No gene set',    'No gene-set directory with FASTA files -- no genes will show for this assembly'];
                       if ($s['no_fai'])     $asm_chips[] = ['Genome not indexed (.fai)', 'genome.fa is present but genome.fa.fai is not -- the sequence viewer needs it'];
                       $asm_js = htmlspecialchars(json_encode($organism)) . ', ' . htmlspecialchars(json_encode($assembly));
                     ?>
                       <button type="button" class="asm-line" onclick="openOrganismModal('asm', <?= $asm_js ?>)" title="Open assembly details">
                         <i class="fa fa-folder text-muted"></i> <span class="fw-semibold"><?= htmlspecialchars($s['display']) ?></span>
                         <?php foreach ($asm_chips as [$txt, $tip]): ?>
                           <span class="badge rounded-pill border text-dark bg-warning-subtle" title="<?= htmlspecialchars($tip) ?>"><?= htmlspecialchars($txt) ?></span>
                         <?php endforeach; ?>
                         <?php if (!$asm_chips): ?>
                           <i class="fa fa-check text-success" title="<?= $s['has_genome'] ? 'Genome FASTA and its .fai index present; name matches the database' : 'Name matches the database' ?>"></i>
                         <?php endif; ?>
                         <?php if (!$s['has_genome'] && $s['gene_sets']): ?>
                           <small class="text-muted" title="No genome.fa -- transcriptome / proteome data only. A valid data shape, not an error.">no genome</small>
                         <?php endif; ?>
                       </button>
                       <?php foreach ($s['gene_sets'] as $gs => $g): ?>
                         <button type="button" class="gs-line" onclick="openOrganismModal('asm', <?= $asm_js ?>, <?= htmlspecialchars(json_encode($gs)) ?>)" title="Open this gene set's details">
                           <span class="text-muted">└</span> <i class="fa fa-stream text-muted"></i> <?= htmlspecialchars($gs) ?>
                           <?php foreach ($g['missing'] as $t): ?>
                             <span class="badge rounded-pill border text-dark bg-warning-subtle" title="<?= htmlspecialchars($t) ?> FASTA file is missing">No <?= htmlspecialchars($t) ?></span>
                           <?php endforeach; ?>
                           <?php foreach ($g['unindexed'] as $t): ?>
                             <span class="badge rounded-pill border text-dark bg-warning-subtle" title="<?= htmlspecialchars($t) ?> FASTA is present but has no BLAST index"><?= htmlspecialchars($t) ?> not BLAST-indexed</span>
                           <?php endforeach; ?>
                           <?php if (!$g['missing'] && !$g['unindexed']): ?>
                             <i class="fa fa-check text-success" title="<?= htmlspecialchars(implode(', ', $gs_type_labels)) ?> FASTA present and BLAST-indexed"></i>
                           <?php endif; ?>
                         </button>
                       <?php endforeach; ?>
                     <?php endforeach; ?>
                   </div>
                 <?php endif; ?>
               </td>
               <td>
                 <?php
                   // ── Consolidated Health cell (replaces the old Tree/DB/Metadata/Status columns) ──
                   // Graded by IMPACT, not by how many checks passed -- see
                   // moop_organism_severity(). $row_status is the one value computed above
                   // the table, so this badge, the row's data-status and the filter counts
                   // cannot disagree.
                   $hstate = [
                       'complete'   => ['success', 'check-circle',         'Complete'],
                       'critical'   => ['danger',  'times-circle',         'Critical'],
                       'incomplete' => ['warning', 'exclamation-triangle', 'Incomplete'],
                   ][$row_status];
                   // DB inspector tint (mirrors the old DB Status column's states)
                   $db_class = 'secondary';
                   if ($data['db_validation']) {
                       $v = $data['db_validation']; $ai = $data['assembly_validation'];
                       $db_bad_dirs = $ai && (!$ai['valid'] || !empty($ai['mismatches']));
                       // This tested database_valid -- "does it open as SQLite" -- and so
                       // ignored data_issues entirely: an organism with zero features, or
                       // with orphaned annotations, still lit up green. Content problems
                       // that make the organism unusable are red; everything else warns.
                       $db_unusable = (bool)array_intersect($v['data_issue_codes'] ?? [], $db_content_codes);
                       if (!$v['readable'] || !$v['database_valid'] || $db_unusable)              $db_class = 'danger';
                       elseif (empty($v['tables_present']) || !empty($v['data_issues']) || $db_bad_dirs) $db_class = 'warning';
                       else                                                                       $db_class = 'success';
                   }
                   // Metadata inspector tint
                   $jv = $data['json_validation'];
                   $meta_ok = $jv['exists'] && $jv['readable'] && $jv['valid_json'] && $jv['has_required_fields'] && $jv['writable'];
                   $meta_class = $meta_ok ? 'success' : ((!$jv['exists'] || !$jv['readable'] || !$jv['valid_json']) ? 'danger' : 'warning');
                   // Organism-level problems only ('health' in moop_organism_issue_defs()).
                   // Everything per assembly or per gene set -- FASTA, BLAST, .fai, directory
                   // names, no genome -- is on its own line in the Assemblies tree beside this
                   // cell; repeating it here showed the same fact twice in two wordings.
                   // In moop_organism_issue_defs() order, so critical badges come first.
                   $visible_issues = array_values(array_filter(array_intersect(array_keys($issue_defs), $row_issues),
                                                               fn($i) => !empty($issue_defs[$i]['health'])));
                 ?>
                 <button class="btn btn-sm btn-outline-<?= $hstate[0] ?>" onclick="openOrganismModal('status', <?= htmlspecialchars(json_encode($organism)) ?>)" title="Open the setup checklist">
                   <i class="fa fa-<?= $hstate[1] ?>"></i> <?= $hstate[2] ?>
                 </button>
                 <?php if ($visible_issues): ?>
                   <div class="mt-1 d-flex flex-wrap gap-1">
                     <?php foreach ($visible_issues as $iss): $def = $issue_defs[$iss]; $c = moop_severity_color($def['severity']); ?>
                       <span class="badge bg-<?= $c ?><?= $c === 'warning' ? ' text-dark' : '' ?>" title="<?= htmlspecialchars($def['title']) ?>"><?= htmlspecialchars($def['label']) ?></span>
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
    <div class="card-header adm-head">
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

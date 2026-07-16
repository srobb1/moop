<?php
/**
 * ORGANISM CHECKLIST - Content File
 * 
 * Pure display content - no HTML structure, scripts, or styling.
 * 
 * Layout system (layout.php) handles:
 * - HTML structure (<!DOCTYPE>, <html>, <head>, <body>)
 * - All CSS and resources
 * - All scripts
 * - Navbar and footer
 * 
 * This file has access to variables passed from organism_checklist.php:
 * - $config (ConfigManager instance)
 * - $site (site name)
 * - $organism_data (path to organism data directory)
 */
?>

<div class="container mt-5">
  <!-- Back to Admin Dashboard Link -->
  <div class="mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>

  <h2><i class="fa fa-clipboard-list"></i> New Organism Setup Checklist</h2>

  <!-- About Section -->
  <div class="card mb-4 border-info">
    <div class="card-header bg-info bg-opacity-10" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutChecklist" role="button" tabindex="0">
      <h5 class="mb-0"><i class="fa fa-info-circle"></i> About This Checklist <i class="fa fa-chevron-down float-end"></i></h5>
    </div>
    <div class="collapse" id="aboutChecklist">
      <div class="card-body">
        <p><strong>Purpose:</strong> Step-by-step guide for adding a new organism to your system with quick links to relevant admin tools.</p>
        
        <p><strong>Why This Matters:</strong> Adding a new organism requires several coordinated steps across the system. This checklist helps you:</p>
        <ul>
          <li>Follow the correct sequence of operations</li>
          <li>Remember all necessary configuration steps</li>
          <li>Avoid common setup mistakes</li>
          <li>Quickly navigate to relevant admin pages</li>
          <li>Verify each step is complete before moving on</li>
        </ul>
        
        <p><strong>Built-in Checks & Actions:</strong> Each step includes automated checks to verify configuration and quick action buttons to fix easy-to-address issues:</p>
        <ul>
          <li><strong>Automated Checks:</strong> File permissions, missing configuration files, group assignments, and homepage selector status</li>
          <li><strong>Quick Fix Actions:</strong> Generate missing organism.json files, assign organisms to default groups, and trigger a cache refresh</li>
          <li><strong>Detailed Management:</strong> Links to full management pages for complex customizations</li>
        </ul>
        
        <p class="mb-0"><strong>Note:</strong> Most organism data files are uploaded directly to the server. Use the links below to manage access, configuration, and visibility.</p>
      </div>
    </div>
  </div>

  <!-- Checklist Steps -->
  <div class="mt-5">
    <h3 class="mb-3"><i class="fa fa-list-check"></i> Setup Steps</h3>

    <?php
    // Load all organisms in system once (used by all steps)
    $organisms_in_system = [];
    if (is_dir($organism_data)) {
        foreach (scandir($organism_data) as $item) {
            if ($item !== '.' && $item !== '..' && is_dir("$organism_data/$item")) {
                $organisms_in_system[] = $item;
            }
        }
    }
    sort($organisms_in_system);
    ?>

    <!-- Step 1: Copy Files -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 1</span>
          Copy Files to Organism Directory
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Create a new directory in <code><?= htmlspecialchars($organism_data) ?></code> with the organism name (e.g., <code>Anoura_caudifer</code>)</li>
          <li>Upload or copy the SQLite database file: <code>organism.sqlite</code></li>
          <li>Copy an existing <code>organism.json</code> metadata file into this directory or create one in Step 3</li>
          <li>Create assembly subdirectory (e.g., <code>GCA_004027475.1_v1</code>)</li>
          <li>Place FASTA files in the assembly directory following naming conventions (see below)</li>
          <li>Copy BLAST index files or generate them in Step 3</li>
        </ul>

        <!-- Directory Structure -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#directoryStructureStep1" role="button" tabindex="0">
            <i class="fa fa-folder"></i> Expected Directory Structure <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="directoryStructureStep1">
            <?php
            // FASTA names are rendered from the LIVE config, never hardcoded — they are
            // admin-editable in Manage Site Configuration, so a literal list here would
            // start lying the moment someone changed one. 'genome' sits at the assembly
            // level (shared across gene sets); the rest live in the gene set.
            $seq_types    = $config->getSequenceTypes();
            $genome_cfg   = $seq_types['genome'] ?? null;
            $geneset_seqs = array_diff_key($seq_types, ['genome' => 1]);
            ?>
            <div class="structure-box bg-light p-3 border rounded mb-3">
              <div class="mb-2">
                <code><strong>Genus_species</strong></code> (e.g., Anoura_caudifer)<br/>
                <div class="ms-3">
                  <code>├─ organism.sqlite</code> (gene database)<br/>
                  <code>├─ organism.json</code> (organism metadata)<br/>
                  <code>└─ <strong>assembly_name</strong></code> (e.g., GCA_004027475.1)<br/>
                  <div class="ms-3">
                    <?php if ($genome_cfg): ?>
                    <code>├─ <?= htmlspecialchars($genome_cfg['pattern']) ?></code> (reference genome — shared across gene sets)<br/>
                    <code>├─ <?= htmlspecialchars($genome_cfg['pattern']) ?>.fai</code> <span class="text-muted">— generated</span><br/>
                    <?php endif; ?>
                    <code>├─ genome.json</code> (assembly metadata)<br/>
                    <code>└─ <strong>gene_set_name</strong></code> (e.g., NV2, OGS1.0)<br/>
                    <div class="ms-3">
                      <code>├─ <?= htmlspecialchars(genes_gff_filename()) ?></code> (annotations — GFF3)<br/>
                      <code>├─ geneset.json</code> (gene-set metadata)<br/>
                      <?php foreach ($geneset_seqs as $st): ?>
                      <code>├─ <?= htmlspecialchars($st['pattern']) ?></code> (<?= htmlspecialchars($st['label'] ?? '') ?> sequences)<br/>
                      <?php endforeach; ?>
                      <code>├─ BLAST index files</code> <span class="text-muted">— generated (Step 3)</span><br/>
                      <code>└─ feature_coords.tsv</code> <span class="text-muted">— generated at registration</span><br/>
                    </div>
                  </div>
                </div>
                <div class="mt-2">
                  <code><strong>data/genomes/</strong></code> (JBrowse2 genome FASTA files)<br/>
                  <code><strong>data/tracks/</strong></code> (JBrowse2 track data files — protected by JWT auth)<br/>
                </div>
              </div>
            </div>

            <p class="mb-2"><strong>File Naming Notes:</strong></p>
            <ul class="mb-0">
              <li>Organism directory: Use underscores to separate genus, species, and optional subspecies</li>
              <li>Database file: Must be named <code>organism.sqlite</code></li>
              <li>Metadata file: Must be named <code>organism.json</code></li>
              <li><strong>Use the FASTA names exactly as shown — no prefix.</strong>
                  <code><?= htmlspecialchars($geneset_seqs['transcript']['pattern'] ?? 'transcript.nt.fa') ?></code>,
                  not <code>myorg.<?= htmlspecialchars($geneset_seqs['transcript']['pattern'] ?? 'transcript.nt.fa') ?></code>.
                  BLAST resolves the exact filename, so a prefixed file is <strong>silently skipped</strong>:
                  sequence extraction and search still find it, but no BLAST database is built for that
                  type and nothing reports an error.</li>
              <li>The names above come from your live configuration and are <strong>editable</strong> in
                  <a href="manage_site_config.php" target="_blank">Manage Site Configuration</a>, where you
                  can also turn types on and off. This list follows whatever you set there.</li>
              <li>Assembly directory: Use a unique identifier (typically from NCBI: GCA_xxxxxxx.x)</li>
              <li>Items marked <span class="text-muted">generated</span> are created for you — do not copy
                  them in.</li>
            </ul>
          </div>
        </div>

        <!-- organism.json Format -->
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#jsonFormatStep1" role="button" tabindex="0">
            <i class="fa fa-file-code"></i> organism.json Format <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="jsonFormatStep1">
            <p class="mb-2">The <code>organism.json</code> file contains metadata about your organism. You have two options to create it:</p>
            
            <div class="alert alert-info mb-3">
              <i class="fa fa-lightbulb"></i> <strong>Easy Option:</strong> Use the <strong>Status</strong> button in <a href="manage_organisms.php" target="_blank"><strong>Manage Organisms</strong></a> (Step 3) to create and edit the organism.json file through an interactive interface. No manual file editing needed!
            </div>

            <p class="mb-2"><strong>Required Fields:</strong></p>
            <ul class="mb-3">
              <li><code>genus</code> - Organism genus (required)</li>
              <li><code>species</code> - Organism species (required)</li>
              <li><code>common_name</code> - User-friendly name (required)</li>
              <li><code>taxon_id</code> - NCBI taxonomy ID (required)</li>
            </ul>

            <p class="mb-2"><strong>Optional Fields:</strong></p>
            <ul class="mb-3">
              <li><code>images</code> - Array of image objects with filename and caption</li>
              <li><code>html_p</code> - Array of HTML content paragraphs</li>
            </ul>

            <p class="mb-2"><strong>Example organism.json:</strong></p>
            <pre class="bg-light p-3 rounded code-example">{
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
    }
  ]
}</pre>
            <small class="text-muted"><strong>Note:</strong> Images should be placed in <code>/moop/images/</code>. Complete and review this metadata in <strong>Step 3</strong>.</small>
          </div>
        </div>

        <!-- organism.sqlite Status Check -->
        <div class="mt-4">
          <?php
          // Check for missing SQLite databases
          $missing_sqlite = [];
          foreach ($organisms_in_system as $org) {
              $sqlite_path = "$organism_data/$org/organism.sqlite";
              if (!file_exists($sqlite_path)) {
                  $missing_sqlite[] = $org;
              }
          }
          ?>
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#sqliteStatusStep1" role="button" tabindex="0">
            <i class="fa fa-database"></i> Database File Status 
            <?php if (!empty($missing_sqlite)): ?>
              <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> <?= count($missing_sqlite) ?> Missing</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="fa fa-check"></i> OK</span>
            <?php endif; ?>
            <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse show" id="sqliteStatusStep1">

            <?php if (!empty($missing_sqlite)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.sqlite files:</strong>
                <p class="mb-3 mt-2">The following organisms don't have an <code>organism.sqlite</code> database file:</p>
                <div class="bg-light p-2 rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                  <ul class="mb-0">
                    <?php foreach ($missing_sqlite as $org): ?>
                      <li><code><?= htmlspecialchars($org) ?>/organism.sqlite</code></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                
                <p class="mb-2"><strong>What to do:</strong></p>
                <ul class="mb-2">
                  <li>Create your own <code>organism.sqlite</code> file with gene/feature data</li>
                  <li>Or use an empty placeholder database to get started</li>
                  <li>See the <a href="/moop/help.php?topic=organism-data-organization#database-schema" target="_blank"><strong>Database Schema section</strong></a> in the organism data organization help for detailed information on file format and structure</li>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- organism.json Status Check -->
        <div class="mt-4">
          <?php
          // Check for missing organism.json files
          $missing_json = [];
          foreach ($organisms_in_system as $org) {
              $json_path = "$organism_data/$org/organism.json";
              if (!file_exists($json_path)) {
                  $missing_json[] = $org;
              }
          }
          ?>
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#jsonStatusStep1" role="button" tabindex="0">
            <i class="fa fa-file-code"></i> organism.json File Status 
            <?php if (!empty($missing_json)): ?>
              <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> <?= count($missing_json) ?> Missing</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="fa fa-check"></i> OK</span>
            <?php endif; ?>
            <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="jsonStatusStep1">

            <?php if (!empty($missing_json)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.json files:</strong>
                <p class="mb-3 mt-2">The following organisms don't have an <code>organism.json</code> metadata file:</p>
                <div class="bg-light p-2 rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                  <ul class="mb-0">
                    <?php foreach ($missing_json as $org): ?>
                      <li><code><?= htmlspecialchars($org) ?>/organism.json</code></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                
                <p class="mb-2"><strong>What to do:</strong></p>
                <ul class="mb-2">
                  <li>Create an <code>organism.json</code> file with basic metadata</li>
                  <li>Use the auto-generate function in Step 3 to fetch NCBI data automatically</li>
                  <li>Or manually create the file with the format shown above</li>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- BLAST Index Status Check -->
        <div class="mt-4">
          <?php
          include_once __DIR__ . '/../../lib/blast_functions.php';
          
          // Check for missing BLAST indexes.
          // BLAST indexes now live in gene_set subdirs under each assembly dir.
          $blast_issues = [];
          foreach ($organisms_in_system as $org) {
              $org_dir = "$organism_data/$org";
              if (!is_dir($org_dir)) continue;
              foreach (scandir($org_dir) as $asm) {
                  if ($asm === '.' || $asm === '..' || !is_dir("$org_dir/$asm")) continue;
                  $asm_path = "$org_dir/$asm";
                  foreach (glob($asm_path . '/*', GLOB_ONLYDIR) ?: [] as $gs_dir) {
                      $blast_validation = validateBlastIndexFiles($gs_dir, $sequence_types);
                      foreach ($blast_validation['databases'] ?? [] as $db) {
                          if (!$db['has_indexes']) {
                              $blast_issues[] = [
                                  'organism' => $org,
                                  'assembly' => $asm,
                                  'gene_set' => basename($gs_dir),
                                  'fasta'    => $db['fasta'],
                                  'missing'  => $db['missing_indexes'],
                              ];
                          }
                      }
                  }
              }
          }
          ?>
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#blastIndexStatusStep1" role="button" tabindex="0">
            <i class="fa fa-rocket"></i> BLAST Index Files Status 
            <?php if (!empty($blast_issues)): ?>
              <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> <?= count($blast_issues) ?> Missing</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="fa fa-check"></i> OK</span>
            <?php endif; ?>
            <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="blastIndexStatusStep1">

            <?php if (!empty($blast_issues)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Missing BLAST Index Files:</strong>
                <p class="mb-3 mt-2">BLAST index files are required for FASTA files to be searchable. The following need indexes:</p>
                <div class="bg-light p-3 rounded mb-3" style="max-height: 400px; overflow-y: auto;">
                  <ul class="mb-0 small">
                    <?php foreach ($blast_issues as $issue): ?>
                      <?php 
                        $organism_data_base = $config->getPath('organism_data');
                        $gs_fullpath = $organism_data_base . '/' . $issue['organism'] . '/' . $issue['assembly'] . '/' . ($issue['gene_set'] ?? 'v1');
                        $perm_check = checkAssemblyCanGenerateBlast($gs_fullpath, [$issue['fasta']]);
                        $can_generate = $perm_check['writable'];

                        // Calculate commands upfront so they're available everywhere
                        $is_protein = strpos($issue['fasta'], 'protein') !== false;
                        $db_type = $is_protein ? 'prot' : 'nucl';
                        $cd_cmd = "cd " . htmlspecialchars($gs_fullpath);
                        $makeblastdb_cmd = "makeblastdb -in " . htmlspecialchars($issue['fasta']) . " -dbtype " . htmlspecialchars($db_type) . " -parse_seqids";
                      ?>
                      <li class="mb-3 pb-3 border-bottom">
                        <strong><?= htmlspecialchars($issue['organism']) ?>/<?= htmlspecialchars($issue['assembly']) ?>/<?= htmlspecialchars($issue['gene_set'] ?? 'v1') ?>/<?= htmlspecialchars($issue['fasta']) ?></strong><br>
                        <small class="text-danger">Missing: <?= htmlspecialchars(implode(', ', $issue['missing'])) ?></small>
                        <?php if (!$can_generate): ?>
                          <div class="alert alert-danger mt-2 mb-0 py-1 px-2 small">
                            <i class="fa fa-lock"></i> <strong>Cannot generate:</strong> <?= htmlspecialchars($perm_check['message']) ?>
                          </div>
                        <?php endif; ?>
                        <div class="mt-2 p-2 bg-white border rounded small">
                          <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>To generate BLAST indexes, run on the server:</strong>
                            <div class="btn-group btn-group-sm" role="group">
                              <button type="button" class="btn btn-outline-primary copy-cmd-btn" data-cmd-text="<?= htmlspecialchars($cd_cmd . ' && ' . $makeblastdb_cmd) ?>" title="Copy command">
                                <i class="fa fa-copy"></i> Copy
                              </button>
                              <?php if ($can_generate): ?>
                                <button type="button" class="btn btn-outline-success generate-blast-btn" data-organism="<?= htmlspecialchars($issue['organism']) ?>" data-assembly="<?= htmlspecialchars($issue['assembly']) ?>" data-gene-set="<?= htmlspecialchars($issue['gene_set'] ?? 'v1') ?>" data-fasta="<?= htmlspecialchars($issue['fasta']) ?>" title="Generate now">
                                  <i class="fa fa-play"></i> Generate
                                </button>
                              <?php endif; ?>
                            </div>
                          </div>
                          <code class="d-block" style="word-break: break-all; white-space: normal;">
                            <?= $cd_cmd ?> && \<br><?= $makeblastdb_cmd ?>
                          </code>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- FAI Index Status Check -->
        <div class="mt-4">
          <?php
          // Check for missing genome.fa.fai indexes
          $fai_issues = [];
          foreach ($organisms_in_system as $org) {
              $org_dir = "$organism_data/$org";
              if (is_dir($org_dir)) {
                  foreach (scandir($org_dir) as $item) {
                      if ($item === '.' || $item === '..') continue;
                      $asm_dir = "$org_dir/$item";
                      if (!is_dir($asm_dir)) continue;
                      $fasta   = "$asm_dir/genome.fa";
                      $fai     = "$fasta.fai";
                      if (file_exists($fasta) && !file_exists($fai)) {
                          $fai_issues[] = [
                              'organism' => $org,
                              'assembly' => $item,
                              'fasta'    => $fasta,
                              'writable' => is_writable($asm_dir),
                          ];
                      }
                  }
              }
          }
          ?>
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#faiIndexStatusStep1" role="button" tabindex="0">
            <i class="fa fa-dna"></i> Genome FAI Index Status
            <?php if (!empty($fai_issues)): ?>
              <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> <?= count($fai_issues) ?> Missing</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="fa fa-check"></i> OK</span>
            <?php endif; ?>
            <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse" id="faiIndexStatusStep1">
            <?php if (!empty($fai_issues)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Missing FAI Index Files:</strong>
                <p class="mb-3 mt-2">A <code>genome.fa.fai</code> index is required for the SVG gene model sequence viewer. The following assemblies need one:</p>
                <div class="bg-light p-3 rounded mb-3" style="max-height: 400px; overflow-y: auto;">
                  <ul class="mb-0 small">
                    <?php foreach ($fai_issues as $issue): ?>
                      <?php
                        $faidx_cmd = 'samtools faidx ' . escapeshellarg($issue['fasta']);
                        $cd_cmd    = 'cd ' . escapeshellarg(dirname($issue['fasta']));
                      ?>
                      <li class="mb-3 pb-3 border-bottom">
                        <strong><?= htmlspecialchars($issue['organism']) ?>/<?= htmlspecialchars($issue['assembly']) ?>/genome.fa</strong>
                        <?php if (!$issue['writable']): ?>
                          <div class="alert alert-danger mt-2 mb-0 py-1 px-2 small">
                            <i class="fa fa-lock"></i> <strong>Cannot generate:</strong> assembly directory is not writable by the web server
                          </div>
                        <?php endif; ?>
                        <div class="mt-2 p-2 bg-white border rounded small">
                          <div class="d-flex justify-content-between align-items-start mb-2">
                            <strong>To generate the FAI index, run on the server:</strong>
                            <div class="btn-group btn-group-sm" role="group">
                              <button type="button" class="btn btn-outline-primary copy-cmd-btn"
                                data-cmd-text="<?= htmlspecialchars($cd_cmd . ' && ' . $faidx_cmd) ?>"
                                title="Copy command">
                                <i class="fa fa-copy"></i> Copy
                              </button>
                              <?php if ($issue['writable']): ?>
                                <button type="button" class="btn btn-outline-success generate-fai-btn"
                                  data-organism="<?= htmlspecialchars($issue['organism']) ?>"
                                  data-assembly="<?= htmlspecialchars($issue['assembly']) ?>"
                                  title="Generate now">
                                  <i class="fa fa-play"></i> Generate
                                </button>
                              <?php endif; ?>
                            </div>
                          </div>
                          <code class="d-block" style="word-break: break-all; white-space: normal;">
                            <?= htmlspecialchars($cd_cmd) ?> && \<br><?= htmlspecialchars($faidx_cmd) ?>
                          </code>
                        </div>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
              </div>
            <?php else: ?>
              <p class="text-muted small">All assemblies with a <code>genome.fa</code> have a corresponding <code>genome.fa.fai</code> index.</p>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Step 2: Check File Permissions -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 2</span>
          Check File Permissions
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Verify the web server can read all organism files and databases</li>
          <li>Check that directories have execute permissions for traversal</li>
          <li>Ensure all FASTA files are readable by the web server user</li>
          <li>Verify assembly directories are writable (needed for BLAST index generation)</li>
        </ul>

        <p class="mb-3"><em><i class="fa fa-info-circle"></i> This step performs a quick check of organism directories. For a comprehensive check including assembly subdirectories and all FASTA files, use the <strong>Manage Filesystem Permissions</strong> page below.</em></p>

        <?php
        // Quick permission check on organism directories
        $permission_issues = [];
        $web_server_info = getWebServerUser();
        $expected_group = $web_server_info['group'];

        foreach ($organisms_in_system as $org) {
            $org_dir = "$organism_data/$org";

            if (!file_exists($org_dir)) {
                $permission_issues[] = [
                    'organism' => $org,
                    'path' => $org_dir,
                    'issues' => ['Directory does not exist']
                ];
                continue;
            }

            // Get full permission with leading digits (e.g., 2775, 0755)
            $perms_full = substr(sprintf('%o', fileperms($org_dir)), -4);
            $perms = ltrim($perms_full, '0') ?: '0';

            $group = 'unknown';
            if (function_exists('posix_getgrgid')) {
                $gr = posix_getgrgid(filegroup($org_dir));
                if ($gr) { $group = $gr['name']; }
            } elseif ($org_dir) {
                $stat_out = [];
                @exec("stat -c '%G' " . escapeshellarg($org_dir) . " 2>/dev/null", $stat_out);
                if (!empty($stat_out[0]) && $stat_out[0] !== 'UNKNOWN') { $group = $stat_out[0]; }
            }

            $issues = [];

            // Judge by IMPACT, not by an exact mode string — same rule the dashboard and
            // the Filesystem Permissions page use (lib/permission_check.php). The old test
            // here was `$perms !== '2775'`, which is wrong in both directions: it flags an
            // apache-owned 2755 directory the web CAN write, and it would pass a 2775
            // directory whose SELinux label blocks writes anyway.
            //
            // What actually matters: can the web server CREATE files here? makeblastdb and
            // samtools faidx write new files, and creating a file needs write on the parent
            // directory, not on the file.
            if (!is_writable($org_dir)) {
                $issues[] = "The web server ($expected_group) cannot write here — "
                          . "Build BLAST Index and index generation will fail ($perms)";
            }

            // SGID keeps new files in the web group. Without it, files created here inherit
            // the creator's group and the next tool along cannot read them.
            if (!($perms_full[0] === '2')) {
                $issues[] = "Missing the SGID bit ($perms) — new files here will not inherit "
                          . "the $expected_group group";
            }

            // Check group matches the web server group
            if ($group !== $expected_group) {
                $issues[] = "Group is $group, should be $expected_group";
            }

            if (!empty($issues)) {
                $permission_issues[] = [
                    'organism' => $org,
                    'path' => $org_dir,
                    'perms' => $perms,
                    'group' => $group,
                    'issues' => $issues
                ];
            }
        }
        ?>
        
        <?php if (!empty($permission_issues)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Permission Issues Found in Organism Directories:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($permission_issues as $item): ?>
                <li>
                  <strong><?= htmlspecialchars($item['organism']) ?></strong><br>
                  Current: <?= $item['perms'] ?? 'unknown' ?> (group: <?= htmlspecialchars($item['group'] ?? 'unknown') ?>) | Required: 2775 (group: <?= htmlspecialchars($expected_group) ?>)
                  <?php if (!empty($item['issues'])): ?>
                    <ul class="mt-1 mb-0">
                      <?php foreach ($item['issues'] as $issue): ?>
                        <li><?= htmlspecialchars($issue) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php // NOT `chmod -R 2775`: the -R applies 2775 to FILES too, making every
                  // genome, database and BLAST index executable. That command — handed out
                  // right here — is where 234 executable index files on this box came from.
                  // Directories need the traverse bit; data files never do. Split them. ?>
            <div class="mt-3 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.85em;">
              <strong>To fix all organism directories, run:</strong><br>
              sudo chgrp -R <?= htmlspecialchars($expected_group) ?> <?= escapeshellarg($organism_data) ?><br>
              sudo find <?= escapeshellarg($organism_data) ?> -type d -exec chmod 2775 {} +<br>
              sudo find <?= escapeshellarg($organism_data) ?> -type f -exec chmod a-x {} +
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> Organism directories have proper permissions (2775, <?= htmlspecialchars($expected_group) ?> group)!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Detailed Check:</strong> <a href="manage_filesystem_permissions.php" class="btn btn-primary"><i class="fa fa-lock"></i> Manage Filesystem Permissions</a></p>
        <small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i> The full page checks organism directories, assembly subdirectories, FASTA files, and other critical paths</small>
      </div>
    </div>

    <!-- Step 3: Check Organism Status -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 3</span>
          Check Organism Status & Configure Metadata
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Verify assemblies and FASTA files are detected correctly</li>
          <li>Complete organism metadata:
            <ul class="mt-2">
              <li><strong>Genus</strong> and <strong>Species</strong> (required)</li>
              <li><strong>Common Name</strong> (required) - user-friendly name</li>
              <li><strong>Taxon ID</strong> (required) - NCBI taxonomy identifier</li>
              <li><strong>Images</strong> (optional) - organism photos for display</li>
              <li><strong>Descriptions</strong> (optional) - additional HTML content about the organism</li>
            </ul>
          </li>
          <li>Check that FASTA files are properly formatted for BLAST (if indexes are missing, you may need to rebuild BLAST indexes)</li>
        </ul>

        <?php
        // Load all organisms in system (used by Steps 3, 4, and 5)
        $organisms_in_system = [];
        if (is_dir($organism_data)) {
            foreach (scandir($organism_data) as $item) {
                if ($item !== '.' && $item !== '..' && is_dir("$organism_data/$item")) {
                    $organisms_in_system[] = $item;
                }
            }
        }
        sort($organisms_in_system);
        ?>

        <?php
        // Check for organisms missing organism.json
        $organisms_missing_json = [];
        foreach ($organisms_in_system as $org) {
            $json_file = "$organism_data/$org/organism.json";
            if (!file_exists($json_file)) {
                $organisms_missing_json[] = $org;
            }
        }
        ?>
        
        <?php if (!empty($organisms_missing_json)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.json:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($organisms_missing_json as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <p class="mt-3"><strong>Quick Action:</strong></p>
          <button type="button" class="btn btn-primary" id="generateJsonBtn">
            <i class="fa fa-magic"></i> Auto-Generate Missing Files
          </button>
          <small class="text-muted d-block mt-2">
            <i class="fa fa-info-circle"></i> This will fetch organism data from NCBI and generate organism.json files
          </small>
          <div id="generateJsonStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms have organism.json files!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_organisms.php" class="btn btn-success"><i class="fa fa-dna"></i> Manage Organisms</a></p>
      </div>
    </div>

    <!-- Step 4: Verify Homepage Selector -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 4</span>
          Verify Organism Appears in Homepage Selector
        </h5>
      </div>
      <div class="card-body">
        <p>MOOP's homepage shows a taxonomic hierarchy that lets visitors browse and select organisms. This hierarchy is built automatically from each organism's <code>taxon_id</code> field in <code>organism.json</code> using locally-cached NCBI lineage data.</p>
        <p><strong>How it works:</strong></p>
        <ul>
          <li>Each organism needs a valid <code>taxon_id</code> (NCBI Taxonomy ID) in its <code>organism.json</code></li>
          <li>The organism cache rebuilds the selector from that ID. It refreshes <strong>automatically</strong>
              in the background when the underlying data changes (housekeeping compares fingerprints, so an
              unchanged site costs nothing) — you can also force it from <strong>Manage Organisms</strong>
              if you want it now.</li>
          <li>NCBI lineage data is synced monthly in the background — no manual steps needed</li>
          <li>If two organisms share the same taxon ID, only one will appear in the selector</li>
        </ul>

        <?php
        $tree_file = dirname($organism_data) . '/metadata/taxonomy_tree_config.json';
        $organisms_not_in_tree = [];
        foreach ($organisms_in_system as $org) {
            if (!isAssemblyInTaxonomyTree($org, '', $tree_file)) {
                $organisms_not_in_tree[] = $org;
            }
        }
        ?>

        <?php if (!empty($organisms_not_in_tree)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Not yet in selector:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($organisms_not_in_tree as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <p class="mt-3"><strong>Fix:</strong> Run a cache refresh to rebuild the selector.</p>
          <button type="button" class="btn btn-primary" id="generateTreeBtn">
            <i class="fa fa-sync-alt"></i> Refresh Cache Now
          </button>
          <div id="generateTreeStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms are in the homepage selector.
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Step 5: Assign to Groups -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 5</span>
          Assign to Groups & Control Access
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>Assign the organism and its assemblies to user groups</li>
          <li><strong>Public Group:</strong> Makes the organism assembly accessible to anyone.</li>
          <li><strong>Other Groups:</strong> Groups are used to make an organism and assembly easy for users to locate</li>
          <li>You can create new groups and customize them with:
            <ul class="mt-2">
              <li>Group descriptions</li>
              <li>Group images and branding</li>
              <li>HTML descriptions</li>
            </ul>
          </li>
        </ul>

        <?php
        // Load organism_assembly_groups.json
        $groups_file = dirname($organism_data) . '/metadata/organism_assembly_groups.json';
        $group_assignments = [];
        
        if (file_exists($groups_file)) {
            $group_data = loadJsonFile($groups_file, []);
            if (is_array($group_data)) {
                foreach ($group_data as $item) {
                    $org = $item['organism'] ?? '';
                    if (!empty($org)) {
                        $group_assignments[$org] = $item['groups'] ?? [];
                    }
                }
            }
        }
        
        // Check which organisms in the system don't have group assignments
        $unassigned_organisms = [];
        foreach ($organisms_in_system as $org) {
            if (empty($group_assignments[$org])) {
                $unassigned_organisms[] = $org;
            }
        }
        ?>
        
        <?php if (!empty($unassigned_organisms)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Not Assigned to Groups:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($unassigned_organisms as $org): ?>
                <li><?= htmlspecialchars($org) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <p class="mt-3"><strong>Quick Action:</strong></p>
          <button type="button" class="btn btn-primary" id="assignToNewGroupBtn">
            <i class="fa fa-plus-circle"></i> Add to "New" Group
          </button>
          <small class="text-muted d-block mt-2">
            <i class="fa fa-info-circle"></i> This will add all unassigned organisms to the "New" group
          </small>
          <div id="assignToGroupStatus" style="display: none; margin-top: 1rem;"></div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All organisms are assigned to groups!
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_groups.php" class="btn btn-warning"><i class="fa fa-layer-group"></i> Manage Groups</a></p>
      </div>
    </div>

    <!-- Step 6: Manage User Access -->
    <div class="card mb-3 border-primary">
      <div class="card-header bg-primary bg-opacity-10">
        <h5 class="mb-0">
          <span class="badge bg-primary me-2">Step 6</span>
          Manage User Access (If Non-Public Assembly)
        </h5>
      </div>
      <div class="card-body">
        <p><strong>What to do:</strong></p>
        <ul>
          <li>If the organism assembly is NOT in the public group, assign access to specific users</li>
          <li>Users in the all-access IP range can view all organisms and assemblies. (See the Auto-Login IP Ranges section in the <a href="manage_site_config.php">Manage Site Configuration</a>)</li>
          <li>Create user accounts for collaborators who need access to specific assemblies</li>
          <li>Assign users to specific assemblies</li>
          <li>Users must be authenticated to view non-public organisms/assemblies</li>
        </ul>

        <p class="mt-3"><strong>Go to:</strong> <a href="manage_users.php" class="btn btn-danger"><i class="fa fa-users"></i> Manage Users</a></p>
        <p class="mt-3"><strong>Configure IP ranges:</strong> <a href="manage_site_config.php" class="btn btn-secondary"><i class="fa fa-cog"></i> Manage Site Configuration</a></p>
      </div>
    </div>
  </div>


  <!-- Navigation -->


<style>
.structure-box {
  font-family: monospace;
  font-size: 0.95em;
  line-height: 1.6;
}

.card-header {
  cursor: pointer;
}

.btn-outline-primary:hover {
  text-decoration: none;
}
</style>

<script>
// Auto-generate organism.json files
async function generateOrganismJson() {
  const btn = document.getElementById('generateJsonBtn');
  const statusDiv = document.getElementById('generateJsonStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Generating organism.json files from NCBI (this may take a minute)...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('api/generate_organism_json.php', {
      method: 'POST'
    });
    
    if (response.ok) {
      const data = await response.json();
      let message = `<strong>Success!</strong> Generated ${data.count} organism.json file${data.count !== 1 ? 's' : ''}. `;
      
      if (data.errors && data.errors.length > 0) {
        message += `<strong>Errors:</strong> <ul class="mb-0 mt-2">`;
        data.errors.forEach(err => {
          message += `<li>${err}</li>`;
        });
        message += `</ul>`;
        statusDiv.innerHTML = `<div class="alert alert-warning"><i class="fa fa-check-circle"></i> ${message}</div>`;
      } else {
        message += 'Reloading...';
        statusDiv.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> ${message}</div>`;
      }
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      const errorText = await response.text();
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Server returned ' + response.status + '. Response: ' + errorText.substring(0, 200) + '</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Define function inline (will also be in admin-utilities.js but this ensures it's available)
async function generateTreeFromChecklist() {
  const btn = document.getElementById('generateTreeBtn');
  const statusDiv = document.getElementById('generateTreeStatus');

  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Triggering cache refresh (rebuilds taxonomy tree automatically)...</div>';
  statusDiv.style.display = 'block';

  try {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(sitePath + '/admin/api/refresh_organism_cache.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfToken },
      body: 'action=start&force=1'
    });
    const data = await response.json();
    if (data.success || data.status === 'started' || data.status === 'running') {
      statusDiv.innerHTML = '<div class="alert alert-success"><i class="fa fa-check-circle"></i> Cache refresh started — taxonomy tree will be rebuilt. Reloading in 5s...</div>';
      setTimeout(() => location.reload(), 5000);
    } else {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Unknown error') + '</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
  const genJsonBtn = document.getElementById('generateJsonBtn');
  if (genJsonBtn) {
    genJsonBtn.addEventListener('click', generateOrganismJson);
  }
  
  const btn = document.getElementById('generateTreeBtn');
  if (btn) {
    btn.addEventListener('click', generateTreeFromChecklist);
  }
  
  const assignBtn = document.getElementById('assignToNewGroupBtn');
  if (assignBtn) {
    assignBtn.addEventListener('click', assignOrganismsToNewGroup);
  }
});

// Assign unassigned organisms to "New" group
async function assignOrganismsToNewGroup() {
  const btn = document.getElementById('assignToNewGroupBtn');
  const statusDiv = document.getElementById('assignToGroupStatus');
  
  // Disable button and show loading
  btn.disabled = true;
  statusDiv.innerHTML = '<div class="alert alert-info"><i class="fa fa-spinner fa-spin"></i> Adding organisms to "New" group...</div>';
  statusDiv.style.display = 'block';
  
  try {
    const response = await fetch('api/assign_organisms_to_group.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: 'group=New'
    });
    
    if (response.ok) {
      const data = await response.json();
      statusDiv.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> <strong>Success!</strong> Added ${data.count} organism-assembly entries to "New" group. Reloading...</div>`;
      
      // Reload the page after a short delay
      setTimeout(() => {
        location.reload();
      }, 2000);
    } else {
      statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> Failed to add organisms to group. Please try again or use the full management page.</div>';
      btn.disabled = false;
    }
  } catch (error) {
    statusDiv.innerHTML = '<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + error.message + '</div>';
    btn.disabled = false;
  }
}

// Copy BLAST command to clipboard
document.addEventListener('DOMContentLoaded', function() {
  const copyBtns = document.querySelectorAll('.copy-cmd-btn');
  copyBtns.forEach(btn => {
    btn.addEventListener('click', function() {
      const cmdText = this.getAttribute('data-cmd-text');
      
      navigator.clipboard.writeText(cmdText).then(() => {
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fa fa-check"></i> Copied!';
        this.classList.remove('btn-outline-primary');
        this.classList.add('btn-success');
        
        setTimeout(() => {
          this.innerHTML = originalText;
          this.classList.remove('btn-success');
          this.classList.add('btn-outline-primary');
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy command. Please copy manually.');
      });
    });
  });

  // Generate BLAST indexes
  const generateBtns = document.querySelectorAll('.generate-blast-btn');
  generateBtns.forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const organism = this.getAttribute('data-organism');
      const assembly = this.getAttribute('data-assembly');
      const geneSet  = this.getAttribute('data-gene-set') || 'v1';
      const fasta = this.getAttribute('data-fasta');

      if (!confirm(`Generate BLAST indexes for ${organism}/${assembly}/${geneSet}/${fasta}?\n\nThis may take a few minutes.`)) {
        return;
      }

      const originalText = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

      fetch('api/generate_blast_indexes.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
          organism: organism,
          assembly: assembly,
          gene_set: geneSet,
          fasta_file: fasta
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          this.innerHTML = '<i class="fa fa-check"></i> Success!';
          this.classList.remove('btn-outline-success');
          this.classList.add('btn-success');
          
          // Show success message
          const successDiv = document.createElement('div');
          successDiv.className = 'alert alert-success mt-2';
          successDiv.innerHTML = '<i class="fa fa-check-circle"></i> BLAST indexes generated successfully! Reloading...';
          this.closest('li').appendChild(successDiv);
          
          // Reload page after 3 seconds
          setTimeout(() => {
            location.reload();
          }, 3000);
        } else {
          this.disabled = false;
          this.innerHTML = originalText;
          const errorDiv = document.createElement('div');
          errorDiv.className = 'alert alert-danger mt-2';
          errorDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Failed to generate indexes');
          this.closest('li').appendChild(errorDiv);
        }
      })
      .catch(err => {
        this.disabled = false;
        this.innerHTML = originalText;
        const errorDiv = document.createElement('div');
        errorDiv.className = 'alert alert-danger mt-2';
        errorDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + err.message;
        this.closest('li').appendChild(errorDiv);
      });
    });
  });
  // Generate FAI indexes
  document.querySelectorAll('.generate-fai-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      const organism = this.getAttribute('data-organism');
      const assembly = this.getAttribute('data-assembly');

      if (!confirm(`Generate FAI index for ${organism}/${assembly}/genome.fa?\n\nThis runs: samtools faidx`)) {
        return;
      }

      const originalText = this.innerHTML;
      this.disabled = true;
      this.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';

      fetch('api/generate_fai_index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ organism, assembly })
      })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          this.innerHTML = '<i class="fa fa-check"></i> Success!';
          this.classList.remove('btn-outline-success');
          this.classList.add('btn-success');
          const ok = document.createElement('div');
          ok.className = 'alert alert-success mt-2';
          ok.innerHTML = '<i class="fa fa-check-circle"></i> FAI index generated successfully! Reloading...';
          this.closest('li').appendChild(ok);
          setTimeout(() => location.reload(), 3000);
        } else {
          this.disabled = false;
          this.innerHTML = originalText;
          const err = document.createElement('div');
          err.className = 'alert alert-danger mt-2';
          err.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + (data.message || 'Failed to generate FAI index');
          this.closest('li').appendChild(err);
        }
      })
      .catch(err => {
        this.disabled = false;
        this.innerHTML = originalText;
        const errDiv = document.createElement('div');
        errDiv.className = 'alert alert-danger mt-2';
        errDiv.innerHTML = '<i class="fa fa-exclamation-circle"></i> <strong>Error:</strong> ' + err.message;
        this.closest('li').appendChild(errDiv);
      });
    });
  });
});
</script>

  <!-- Back to Admin Dashboard Link (Bottom) -->
  <div class="mt-5 mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>
</div>

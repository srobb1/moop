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
  <div class="card mb-4">
    <div class="card-header adm-head" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#aboutChecklist" role="button" tabindex="0">
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
    <h3 class="mb-3"><i class="fa fa-tasks"></i> Setup Steps</h3>

    <?php // Most checks below read the organism cache -- the same results Manage Organisms
          // and the dashboard show. Say how fresh that is, and what has changed since. ?>
    <div class="alert <?= ($cache_stale_organisms || $cache_stale_reasons || !$cache_generated) ? 'alert-warning' : 'alert-light border' ?> small">
      <i class="fa fa-clock"></i>
      <?php if (!$cache_generated): ?>
        No organism cache yet, so database, BLAST, index, tree and group checks have nothing to report.
      <?php else: ?>
        Database, BLAST, index, tree and group checks come from the organism cache, last refreshed
        <strong><?= htmlspecialchars($cache_generated) ?></strong> &mdash; the same results Manage Organisms shows.
        <?php if ($cache_stale_organisms): ?>
          Changed on disk since then: <strong><?= htmlspecialchars(implode(', ', $cache_stale_organisms)) ?></strong>.
        <?php endif; ?>
        <?php foreach ($cache_stale_reasons as $_reason): ?>
          <?= htmlspecialchars(ucfirst($_reason)) ?>.
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($cache_stale_organisms || $cache_stale_reasons || !$cache_generated): ?>
        <button type="button" class="btn btn-sm btn-warning ms-2" id="checklistRefreshCacheBtn">
          <i class="fa fa-sync-alt"></i> Refresh cache
        </button>
        <span id="checklistRefreshCacheStatus" class="ms-2"></span>
      <?php endif; ?>
    </div>


    <!-- Step 1: Copy Files -->
    <div class="card mb-3">
      <div class="card-header adm-head">
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
            $seq_types    = $sequence_types;   // passed in by admin/organism_checklist.php
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

            <div class="alert alert-success mb-3">
              <i class="fa fa-magic"></i> <strong>You only need four fields.</strong>
              Give MOOP <code>genus</code>, <code>species</code>, <code>common_name</code> and
              <code>taxon_id</code>, and it fills in the rest by itself: it writes a description from the
              organism's taxonomic lineage plus the Wikipedia extract (credited, with a link), and finds
              an image — trying a cached NCBI taxonomy image (<code>images/ncbi_taxonomy/{taxon_id}.jpg</code>)
              first, then falling back to Wikipedia, cached in <code>images/wikimedia/</code> so it downloads once.
              Add <code>html_p</code> or <code>images</code> only when you want to override that.
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
              <li><code>images</code> - Array of image objects with filename and caption.
                  <strong>Leave it out and MOOP finds an image for you</strong>, in this order: a cached
                  NCBI taxonomy image at <code>images/ncbi_taxonomy/{taxon_id}.jpg</code> if one is already
                  there, otherwise Wikipedia — downloaded once and cached in <code>images/wikimedia/</code>.
                  Supply this field only when you want a specific image instead.</li>
              <li><code>html_p</code> - Array of HTML content paragraphs.
                  <strong>Leave it out and MOOP writes the description itself</strong>: a summary built from
                  the organism's taxonomic lineage (from <code>taxon_id</code>), plus the Wikipedia extract,
                  credited with a link back to the article. Supply this field only to override that.</li>
              <li><code>feature_types</code> - Which feature types are treated as parents and children,
                  e.g. <code>{"parents":["gene"],"children":["mRNA","transcript","protein"]}</code>.
                  Controls what the gene page treats as the top-level feature. Omit it and MOOP falls back
                  to <code>["gene","pseudogene"]</code> as parents. Every organism on this deployment has it,
                  because the loader writes it.</li>
              <li><code>subclassification</code> - Optional grouping shown on the organism page, e.g.
                  <code>{"type":"strain","value":"CC7"}</code>. Written as
                  <code>{"type":null,"value":null}</code> when unused.</li>
            </ul>

            <p class="mb-2"><strong>Example organism.json</strong> — the normal case, all four required fields:</p>
            <pre class="bg-light p-3 rounded code-example">{
  "genus": "Lasiurus",
  "species": "cinereus",
  "common_name": "Hoary bat",
  "taxon_id": "257879"
}</pre>
            <small class="text-muted d-block mb-3">
              That is genuinely all you need. MOOP writes the description and finds an image itself
              (see above), and the loader adds <code>feature_types</code> and
              <code>subclassification</code> — every organism on this deployment has those two, but
              you do not type them by hand.
            </small>

            <p class="mb-2">
              <a class="text-decoration-none" data-bs-toggle="collapse" href="#organismJsonOverride" role="button">
                <i class="fa fa-chevron-down small"></i> <strong>Overriding the automatic image or description</strong>
              </a>
            </p>
            <div class="collapse" id="organismJsonOverride">
              <p class="small text-muted mb-2">
                Add <code>images</code> or <code>html_p</code> <em>only</em> when the automatic choice is
                wrong for this organism. Supplying either one switches that piece off — MOOP will not
                merge its own content with yours.
              </p>
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
              <small class="text-muted"><strong>Note:</strong> Images you supply go in <code>/moop/images/</code>.</small>
            </div>
            <small class="text-muted d-block mt-2">Complete and review this metadata in <strong>Step 3</strong>.</small>
          </div>
        </div>

        <!-- organism.sqlite Status Check -->
        <?php $db_problem_count = count($no_database_organisms) + count($empty_database_organisms); ?>
        <div class="mt-4">
          <h6 class="fw-bold mb-3" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#sqliteStatusStep1" role="button" tabindex="0">
            <i class="fa fa-database"></i> Database Status
            <?php if ($db_problem_count): ?>
              <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i> <?= $db_problem_count ?> Need attention</span>
            <?php else: ?>
              <span class="badge bg-success"><i class="fa fa-check"></i> OK</span>
            <?php endif; ?>
            <i class="fa fa-chevron-down float-end"></i>
          </h6>
          <div class="collapse show" id="sqliteStatusStep1">

            <?php if (!empty($no_database_organisms)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>No <code>organism.sqlite</code>:</strong>
                <p class="mb-2 mt-2">These organisms have assembly data on disk but no database, so nothing about their genes can be searched or shown:</p>
                <div class="bg-light p-2 rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                  <ul class="mb-0">
                    <?php foreach ($no_database_organisms as $org): ?>
                      <li><code><?= htmlspecialchars($org) ?>/organism.sqlite</code></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <p class="mb-0">Build the database with the loading pipeline. See the <a href="/<?= htmlspecialchars($site) ?>/help.php?topic=organism-data-organization#database-schema" target="_blank"><strong>Database Schema section</strong></a> for the file format and structure.</p>
              </div>
            <?php endif; ?>

            <?php if (!empty($empty_database_organisms)): ?>
              <div class="alert alert-warning">
                <i class="fa fa-exclamation-triangle"></i> <strong>Database present but holds no usable data:</strong>
                <p class="mb-2 mt-2">These open fine, so a file check passes them &mdash; but users searching them get no results:</p>
                <div class="bg-light p-2 rounded mb-3" style="max-height: 150px; overflow-y: auto;">
                  <ul class="mb-0">
                    <?php foreach ($empty_database_organisms as $e): ?>
                      <li><code><?= htmlspecialchars($e['organism']) ?></code>
                        <span class="text-muted small">&mdash; <?= htmlspecialchars(implode('; ', $e['issues'])) ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <p class="mb-0">Reload the database. Details per organism are in <a href="manage_organisms.php?filter=db-content"><strong>Manage Organisms</strong></a>.</p>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- organism.json Status Check -->
        <div class="mt-4">
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
                        $gs_fullpath  = $issue['gs_fullpath'];
                        $perm_check   = $issue['perm_check'];
                        $can_generate = $perm_check['writable'];

                        // Calculate commands upfront so they're available everywhere
                        $db_type = $issue['db_type'];
                        $cd_cmd = "cd " . htmlspecialchars($gs_fullpath);
                        $makeblastdb_cmd = "makeblastdb -in " . htmlspecialchars($issue['fasta']) . " -dbtype " . htmlspecialchars($db_type) . " -parse_seqids";
                      ?>
                      <li class="mb-3 pb-3 border-bottom">
                        <strong><?= htmlspecialchars($issue['organism']) ?>/<?= htmlspecialchars($issue['assembly']) ?>/<?= htmlspecialchars($issue['gene_set']) ?>/<?= htmlspecialchars($issue['fasta']) ?></strong><br>
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
                                <button type="button" class="btn btn-outline-success generate-blast-btn" data-organism="<?= htmlspecialchars($issue['organism']) ?>" data-assembly="<?= htmlspecialchars($issue['assembly']) ?>" data-gene-set="<?= htmlspecialchars($issue['gene_set']) ?>" data-fasta="<?= htmlspecialchars($issue['fasta']) ?>" title="Generate now">
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
    <div class="card mb-3">
      <div class="card-header adm-head">
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

        
        <?php if (!empty($permission_issues)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Permission Issues Found in Organism Directories:</strong>
            <div class="small mt-1">Required: <?= htmlspecialchars($permission_expectation) ?>.</div>
            <ul class="mb-0 mt-2">
              <?php foreach ($permission_issues as $item): ?>
                <li>
                  <strong><?= htmlspecialchars($item['organism']) ?></strong>
                  <span class="text-muted">— currently <?= htmlspecialchars($item['current_perms'] ?? 'unknown') ?>, group <?= htmlspecialchars($item['current_group'] ?? 'unknown') ?></span>
                  <ul class="mt-1 mb-0">
                    <?php foreach ($item['issues'] as $issue): ?>
                      <li><?= htmlspecialchars($issue) ?></li>
                    <?php endforeach; ?>
                  </ul>
                  <?php if (!empty($item['fix_commands'])): ?>
                    <div class="mt-1 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.85em;">
                      <?php foreach ($item['fix_commands'] as $cmd): ?><?= htmlspecialchars($cmd) ?><br><?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php // Files never get the execute bit: the fixes above touch DIRECTORIES only.
                  // `chmod -R 2775` once handed out here is where 234 executable index files
                  // on this box came from. ?>
            <div class="small mt-2">If data files were made executable by an earlier recursive chmod:</div>
            <div class="p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.85em;">
              <?= htmlspecialchars($exec_files_fix_command) ?>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> All <?= count($organisms_in_system) ?> organism directories pass:
            <?= htmlspecialchars($permission_expectation) ?>.
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Detailed Check:</strong> <a href="manage_filesystem_permissions.php" class="btn btn-primary"><i class="fa fa-lock"></i> Manage Filesystem Permissions</a></p>
        <small class="text-muted d-block mt-2"><i class="fa fa-info-circle"></i>
          This card checks the organism directories live. The full page checks every assembly and
          gene set directory, the FASTA files, and the rest of the site with the same rules<?php
          if (!empty($perm_summary['checked_at'])): ?> — its last background scan
          (<?= htmlspecialchars($perm_summary['checked_at']) ?>) found
          <?= (int)($perm_summary['finding_count'] ?? 0) ?> issue area<?= ((int)($perm_summary['finding_count'] ?? 0)) === 1 ? '' : 's' ?><?php
          endif; ?>.
        </small>
      </div>
    </div>

    <!-- Step 3: Check Organism Status -->
    <div class="card mb-3">
      <div class="card-header adm-head">
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


        
        <?php if (!empty($missing_json)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i> <strong>Missing organism.json:</strong>
            <ul class="mb-0 mt-2">
              <?php foreach ($missing_json as $org): ?>
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


        <?php if ($fasta_gaps['unknown']): ?>
          <div class="alert alert-secondary mt-3">
            <i class="fa fa-info-circle"></i> Missing-FASTA detail is not in the organism cache yet —
            refresh the cache to see which gene sets lack a FASTA type.
          </div>
        <?php elseif (!empty($fasta_gaps['gene_sets']) || !empty($fasta_gaps['empty_assemblies'])): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>Sequence data missing:</strong>
            <div class="small mt-1">
              Each gene set is expected to hold <?= htmlspecialchars(implode(', ', $fasta_gaps['expected'])) ?> FASTA. A missing type cannot be
              BLASTed or downloaded for that gene set. An assembly with no gene set shows no genes at all.
            </div>
            <div class="mt-2 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.8em; max-height: 16em; overflow-y: auto;">
              <?php foreach ($fasta_gaps['gene_sets'] as $gs): ?>
                <?= htmlspecialchars($gs['path']) ?><span class="text-warning">   [no <?= htmlspecialchars(implode(', ', $gs['missing'])) ?>]</span><br>
              <?php endforeach; ?>
              <?php foreach ($fasta_gaps['empty_assemblies'] as $asm): ?>
                <?= htmlspecialchars($asm) ?><span class="text-warning">   [no gene set]</span><br>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> Every gene set has its <?= htmlspecialchars(implode(', ', $fasta_gaps['expected'])) ?> FASTA.
          </div>
        <?php endif; ?>

        <?php if (!empty($gene_sets_missing_gff)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>No <code><?= htmlspecialchars(genes_gff_filename()) ?></code> in
              <?= count($gene_sets_missing_gff) ?> gene set<?= count($gene_sets_missing_gff) === 1 ? '' : 's' ?>:</strong>
            <div class="small mt-1">
              These still work for BLAST, but JBrowse has <strong>no annotation track</strong> for them and
              BLAST linkouts are unavailable (<code>feature_coords.tsv</code> is built from the GFF).
              Only gene sets on an assembly <strong>with a genome</strong> are listed — without one there is
              nothing to place genes on, so no GFF is expected. Track these down on your data server.
            </div>
            <div class="mt-2 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.8em; max-height: 16em; overflow-y: auto;">
              <?php foreach ($gene_sets_missing_gff as $gs): ?>
                <?= htmlspecialchars($gs['path']) ?><span class="text-muted"><?= $gs['has'] ? '   [has: ' . htmlspecialchars(implode(', ', $gs['has'])) . ']' : '' ?></span><br>
              <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-2">
              <i class="fa fa-info-circle"></i>
              Copy the missing <code><?= htmlspecialchars(genes_gff_filename()) ?></code> into each gene-set
              directory, then re-register the gene set (Manage JBrowse) to build its annotation track and
              <code>feature_coords.tsv</code>.
            </small>
          </div>
        <?php else: ?>
          <div class="alert alert-success mt-3">
            <i class="fa fa-check-circle"></i> Every gene set on an assembly with a genome has its
            <code><?= htmlspecialchars(genes_gff_filename()) ?></code> annotations file. (Transcriptome-only
            assemblies have no genome to place genes on, so they need none.)
          </div>
        <?php endif; ?>

        <?php if (!empty($seqname_gaps)): ?>
          <div class="alert alert-warning mt-3">
            <i class="fa fa-exclamation-triangle"></i>
            <strong><?= count($seqname_gaps) ?> gene set<?= count($seqname_gaps) === 1 ? ' names' : 's name' ?> chromosomes differently from the genome:</strong>
            <div class="small mt-1">
              The coordinates in <code>feature_coords.tsv</code> (built from the GFF) use sequence names that are
              not in the assembly's <code><?= htmlspecialchars(genome_fasta_filename()) ?></code> -- for example
              Ensembl <code>2L</code> against RefSeq <code>NC_004354.4</code>. Genomic sequence, exon export and
              the genome browser find nothing for these genes; search and BLAST still work.
            </div>
            <div class="mt-2 p-2 bg-dark text-light rounded" style="font-family: monospace; font-size: 0.8em; max-height: 16em; overflow-y: auto;">
              <?php foreach ($seqname_gaps as $gs): ?>
                <?= htmlspecialchars($gs['path']) ?><span class="text-warning">   [<?= number_format($gs['missing']) ?> of <?= number_format($gs['total']) ?> not in genome, e.g. <?= htmlspecialchars(implode(', ', $gs['examples'])) ?>]</span><br>
              <?php endforeach; ?>
            </div>
            <small class="text-muted d-block mt-2">
              <i class="fa fa-info-circle"></i>
              Use a genome and an annotation from the same source, or give the gene set a name-translation table.
              Keep the original files as they are.
            </small>
          </div>
        <?php endif; ?>

        <p class="mt-3"><strong>Full Management:</strong> <a href="manage_organisms.php" class="btn btn-success"><i class="fa fa-dna"></i> Manage Organisms</a></p>
      </div>
    </div>

    <!-- Step 4: Verify Homepage Selector -->
    <div class="card mb-3">
      <div class="card-header adm-head">
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
    <div class="card mb-3">
      <div class="card-header adm-head">
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
    <div class="card mb-3">
      <div class="card-header adm-head">
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

  <!-- Back to Admin Dashboard Link (Bottom) -->
  <div class="mt-5 mb-4">
    <a href="admin.php" class="btn btn-outline-secondary btn-sm">
      <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
    </a>
  </div>
</div>

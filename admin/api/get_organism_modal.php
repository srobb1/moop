<?php
/**
 * On-demand organism modal renderer.
 * Returns HTML for a single Bootstrap modal-dialog based on type + organism.
 */
include_once __DIR__ . '/../admin_init.php';
include_once __DIR__ . '/../../lib/blast_functions.php';
include_once __DIR__ . '/../../lib/functions_data.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$type     = $_POST['type']     ?? '';
$organism = $_POST['organism'] ?? '';
$assembly = $_POST['assembly'] ?? '';

if (!in_array($type, ['db', 'metadata', 'asm', 'status'], true)) {
    http_response_code(400);
    echo 'Invalid modal type';
    exit;
}

// Organism names are directory names: letters, digits, underscores, hyphens, dots.
if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $organism)) {
    http_response_code(400);
    echo 'Invalid organism name';
    exit;
}

$organism_data      = $config->getPath('organism_data');
$metadata_path      = $config->getPath('metadata_path');
$sequence_types     = $config->getSequenceTypes();
$groups_data        = getGroupData();
$taxonomy_tree_file = $metadata_path . '/taxonomy_tree_config.json';

$cache_file = moop_organism_cache_file();
if (!file_exists($cache_file)) {
    http_response_code(503);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">Cache unavailable — try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$raw_cache = loadJsonFile($cache_file, []);
$organisms = $raw_cache['data'] ?? [];

if (!isset($organisms[$organism])) {
    http_response_code(404);
    echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-danger">Organism not found. Try refreshing the cache.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
    exit;
}

$data = $organisms[$organism];

header('Content-Type: text/html; charset=utf-8');

switch ($type) {
    case 'db':       render_db_modal($organism, $data, $organism_data); break;
    case 'metadata': render_metadata_modal($organism, $data, $organism_data); break;
    case 'asm':
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $assembly)) {
            http_response_code(400);
            echo 'Invalid assembly name';
            exit;
        }
        render_asm_modal($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data);
        break;
    case 'status':
        render_status_modal($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
        break;
}

/**
 * Small (i) info icon whose native tooltip carries a section's "what's required" note,
 * replacing the bulky blue alert boxes so each modal's actual data reads first.
 */
function req_info(string $text, string $title = 'What this section needs'): string {
    return '<a role="button" tabindex="0" class="text-info ms-1" style="cursor:pointer; font-weight:normal;"'
         . ' data-bs-toggle="popover" data-bs-trigger="focus" data-bs-placement="top"'
         . ' data-bs-title="' . htmlspecialchars($title, ENT_QUOTES) . '"'
         . ' data-bs-content="' . htmlspecialchars($text, ENT_QUOTES) . '">'
         . '<i class="fa fa-info-circle"></i></a>';
}

// ---------------------------------------------------------------------------

function render_db_modal($organism, $data, $organism_data) {
    if (!$data['db_validation']) {
        echo '<div class="modal-dialog"><div class="modal-content"><div class="modal-body"><div class="alert alert-warning">No database validation data available.</div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div>';
        return;
    }
    $validation         = $data['db_validation'];
    // Live scan — always fresh so the rename helper reflects current disk state
    $assembly_validation = ($data['db_file'] && $data['path'])
        ? validateAssemblyDirectories($data['db_file'], $data['path'])
        : $data['assembly_validation'];
    $org_safe           = htmlspecialchars($organism);
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-database"></i> Database Status: <?= $org_safe ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

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

      <h6 class="fw-bold mb-2"><i class="fa fa-file"></i> Database File <?= req_info('A valid SQLite database file named organism.sqlite must exist in the organism directory, readable by the web server.') ?></h6>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1"><strong>Path:</strong> <?= htmlspecialchars($data['db_file'] ?? 'N/A') ?></p>
          <p class="mb-0">
            <strong>Readable:</strong>
            <?= $validation['readable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
          </p>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Database Validity <?= req_info('Must be a valid SQLite3 file with the proper structure — all required tables from the schema present.') ?></h6>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-table"></i> Database Tables <?= req_info('Required tables: organism, genome, gene_set, feature, annotation_source, annotation, feature_annotation.') ?></h6>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-sitemap"></i> Feature Types and Counts</h6>
      <p class="small text-muted mb-2">Shows the count of each feature type in the database.</p>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-layer-group"></i> Gene Sets <small class="text-muted fw-normal">— database records vs. on-disk directories</small> <?= req_info('Each gene_set row in the database should have a matching subdirectory inside its assembly on disk. This flags database gene sets whose directory is missing — e.g. data was loaded but the files were never deployed (or were removed). Per-assembly FASTA / BLAST file detail lives in the assembly modal.', 'Gene sets: database vs. disk') ?></h6>
      <?php
        // Live query: get gene_sets from DB grouped by genome
        $gene_sets_by_genome = [];
        $gene_set_query_error = null;
        if ($data['db_file'] && $data['db_validation']['readable'] ?? false) {
            try {
                $gs_dbh = getDbConnection($data['db_file']);
                $gs_stmt = $gs_dbh->query("
                    SELECT gs.gene_set_id, gs.gene_set_name, gs.gene_set_description,
                           g.genome_id, g.genome_name, g.genome_accession
                    FROM gene_set gs
                    JOIN genome g ON gs.genome_id = g.genome_id
                    ORDER BY g.genome_name, gs.gene_set_name
                ");
                foreach ($gs_stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $gkey = $row['genome_name'] ?: $row['genome_accession'];
                    if (!isset($gene_sets_by_genome[$gkey])) {
                        $gene_sets_by_genome[$gkey] = [
                            'genome_name'      => $row['genome_name'],
                            'genome_accession' => $row['genome_accession'],
                            'gene_sets'        => []
                        ];
                    }
                    // Find the assembly dir for this genome
                    $org_path = $organism_data . '/' . $organism;
                    $asm_dir  = null;
                    foreach ([$row['genome_name'], $row['genome_accession']] as $candidate) {
                        if ($candidate && is_dir("$org_path/$candidate")) { $asm_dir = $candidate; break; }
                    }
                    $gs_dir_exists = $asm_dir !== null && is_dir("$org_path/$asm_dir/{$row['gene_set_name']}");
                    $gene_sets_by_genome[$gkey]['gene_sets'][] = [
                        'name'        => $row['gene_set_name'],
                        'description' => $row['gene_set_description'],
                        'asm_dir'     => $asm_dir,
                        'dir_exists'  => $gs_dir_exists,
                    ];
                }
                $gs_dbh = null;
            } catch (PDOException $e) {
                $gene_set_query_error = $e->getMessage();
            }
        }
      ?>
      <?php if ($gene_set_query_error): ?>
        <div class="alert alert-warning small mb-3">Could not query gene_set table: <?= htmlspecialchars($gene_set_query_error) ?></div>
      <?php elseif (empty($gene_sets_by_genome)): ?>
        <div class="card mb-3 border-danger border-2">
          <div class="card-body small">
            <span class="badge bg-danger"><i class="fa fa-times"></i></span>
            No gene sets found in the database.
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($gene_sets_by_genome as $gkey => $gdata): ?>
          <?php $any_missing_dir = !empty(array_filter($gdata['gene_sets'], fn($gs) => !$gs['dir_exists'])); ?>
          <div class="card mb-2 <?= $any_missing_dir ? 'border-warning border-2' : 'border-success' ?>">
            <div class="card-header py-1 px-3 <?= $any_missing_dir ? 'bg-warning bg-opacity-10' : 'bg-success bg-opacity-10' ?>">
              <strong><i class="fa fa-folder"></i> <?= htmlspecialchars($gdata['genome_name'] ?: $gdata['genome_accession']) ?></strong>
              <small class="text-muted ms-2"><?= htmlspecialchars($gdata['genome_accession']) ?></small>
              <?php if ($any_missing_dir): ?>
                <span class="badge bg-warning text-dark ms-2"><i class="fa fa-exclamation-triangle"></i> Missing directories</span>
              <?php else: ?>
                <span class="badge bg-success ms-2"><i class="fa fa-check"></i> All directories present</span>
              <?php endif; ?>
            </div>
            <div class="card-body small py-2">
              <ul class="mb-0">
                <?php foreach ($gdata['gene_sets'] as $gs): ?>
                  <li class="mb-1">
                    <?php if ($gs['dir_exists']): ?>
                      <span class="badge bg-success"><i class="fa fa-check"></i></span>
                    <?php else: ?>
                      <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
                    <?php endif; ?>
                    <strong><?= htmlspecialchars($gs['name']) ?></strong>
                    <?php if ($gs['description']): ?>
                      <small class="text-muted ms-1">— <?= htmlspecialchars($gs['description']) ?></small>
                    <?php endif; ?>
                    <?php if (!$gs['dir_exists']): ?>
                      <small class="text-danger ms-1">
                        — directory not found:
                        <?php if ($gs['asm_dir']): ?>
                          <code><?= htmlspecialchars($gs['asm_dir']) ?>/<?= htmlspecialchars($gs['name']) ?>/</code>
                        <?php else: ?>
                          assembly directory not found either
                        <?php endif; ?>
                      </small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6 class="fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Data Quality <?= req_info('Database records should have valid relationships and complete data — this checks for orphaned annotations, missing accessions, and features without proper organism links.', 'Data quality check') ?></h6>
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

      <?php if ($assembly_validation): ?>
        <h6 class="fw-bold mb-2"><i class="fa fa-folder"></i> Assembly Validation <?= req_info('For each genome record in the database, a directory must exist in the organism folder whose name matches the genome_name or genome_accession.') ?></h6>
        <div class="card mb-3 <?= $assembly_validation['valid'] ? 'border-success' : 'border-danger border-2' ?>">
          <div class="card-body small">
            <?php if ($assembly_validation['valid'] && empty($assembly_validation['mismatches'])): ?>
              <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> All assembly and gene set directories match database records</p>
            <?php else: ?>
              <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Issues Found:</strong></p>
              <ul class="mb-0">
                <?php foreach ($assembly_validation['mismatches'] as $mismatch): ?>
                  <li class="mb-2">
                    <?php if ($mismatch['type'] === 'missing_directory'): ?>
                      <span class="badge bg-danger">Missing assembly dir</span>
                      <small class="text-muted ms-1">No directory matching genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>"</small>
                    <?php elseif ($mismatch['type'] === 'missing_gene_set_directory'): ?>
                      <?php
                        $gs_safe_id = preg_replace('/[^a-zA-Z0-9]/', '_', $mismatch['assembly_dir'] . '_' . $mismatch['gene_set_name']);
                        $asm_path   = $data['path'] . '/' . $mismatch['assembly_dir'];
                        $existing_gs_dirs = [];
                        if (is_dir($asm_path)) {
                            foreach (array_diff(scandir($asm_path), ['.', '..']) as $f) {
                                if (is_dir("$asm_path/$f") && $f !== $mismatch['gene_set_name']) {
                                    $existing_gs_dirs[] = $f;
                                }
                            }
                        }
                        // This mismatch means the gene set is a ROW in organism.sqlite with no
                        // matching directory. The feature count tells the admin which case it is:
                        // an empty leftover row (0) vs a real gene set whose data was not deployed (>0).
                        $gs_feature_count = null;
                        if (!empty($data['db_file']) && file_exists($data['db_file'])) {
                            try {
                                $_dbh  = getDbConnection($data['db_file']);
                                $_stmt = $_dbh->prepare(
                                    'SELECT COUNT(*) FROM feature f
                                     JOIN gene_set gs ON gs.gene_set_id = f.gene_set_id
                                     WHERE gs.gene_set_name = ?'
                                );
                                $_stmt->execute([$mismatch['gene_set_name']]);
                                $gs_feature_count = (int) $_stmt->fetchColumn();
                                $_dbh = null;
                            } catch (PDOException $e) {
                                $gs_feature_count = null;
                            }
                        }
                      ?>
                      <span class="badge bg-warning text-dark">Gene set in DB, no directory</span>
                      <small class="text-muted ms-1">
                        Gene set <code><?= htmlspecialchars($mismatch['gene_set_name']) ?></code> is a row in
                        <code>organism.sqlite</code> (assembly <code><?= htmlspecialchars($mismatch['assembly_dir']) ?></code>),
                        but its directory
                        <code><?= htmlspecialchars($mismatch['assembly_dir']) ?>/<?= htmlspecialchars($mismatch['gene_set_name']) ?>/</code>
                        does not exist on disk<?php if ($gs_feature_count !== null): ?> (<?= number_format($gs_feature_count) ?> features in DB)<?php endif; ?>.
                      </small>
                      <?php if ($gs_feature_count === 0): ?>
                        <div class="mt-2 p-2 border rounded bg-light small">
                          <i class="fa fa-info-circle text-secondary"></i>
                          <strong>0 features</strong> — this is a <strong>stale leftover row</strong> from a previous import.
                          Fix it by re-importing a corrected <code>organism.sqlite</code> (or deleting the row);
                          do <strong>not</strong> create a directory. Renaming a directory will not help here.
                        </div>
                      <?php elseif ($gs_feature_count > 0): ?>
                        <div class="mt-2 p-2 border rounded bg-light small">
                          <i class="fa fa-info-circle text-secondary"></i>
                          This gene set has <strong><?= number_format($gs_feature_count) ?> features</strong> in the database,
                          so its data directory is expected but missing. Restore/copy the
                          <code><?= htmlspecialchars($mismatch['gene_set_name']) ?>/</code> directory, or rename an existing one to match below.
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($existing_gs_dirs)): ?>
                        <div class="mt-2 p-2 border rounded bg-light">
                          <p class="mb-1 small"><strong>Rename an existing directory to match:</strong></p>
                          <div class="d-flex gap-2 align-items-center flex-wrap">
                            <select id="gsOldDirName<?= $gs_safe_id ?>" class="form-select form-select-sm" style="max-width:200px;">
                              <option value="">— select current dir —</option>
                              <?php foreach ($existing_gs_dirs as $d): ?>
                                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                              <?php endforeach; ?>
                            </select>
                            <span class="text-muted small">→</span>
                            <input id="gsNewDirName<?= $gs_safe_id ?>" type="text" class="form-control form-control-sm" style="max-width:200px;" value="<?= htmlspecialchars($mismatch['gene_set_name']) ?>" readonly>
                            <button class="btn btn-info btn-sm" onclick="renameGeneSetDirectory(event, <?= htmlspecialchars(json_encode($organism)) ?>, <?= htmlspecialchars(json_encode($mismatch['assembly_dir'])) ?>, '<?= $gs_safe_id ?>')">
                              <i class="fa fa-exchange-alt"></i> Rename
                            </button>
                          </div>
                          <div id="gsRenameResult<?= $gs_safe_id ?>" class="mt-2 d-none"></div>
                        </div>
                      <?php else: ?>
                        <div class="mt-1"><small class="text-muted fst-italic">No candidate directories found in <code><?= htmlspecialchars($mismatch['assembly_dir']) ?>/</code> to rename.</small></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">Name mismatch</span>
                      <small class="text-muted ms-1">Directory "<?= htmlspecialchars($mismatch['found_directory']) ?>" doesn't match genome_name "<?= htmlspecialchars($mismatch['genome_name']) ?>" or genome_accession "<?= htmlspecialchars($mismatch['genome_accession']) ?>"</small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

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
            <div id="fixResult<?= $org_safe ?>" class="mt-3 d-none"></div>
          </div>
        </div>
      <?php endif; ?>

    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}

// ---------------------------------------------------------------------------

function render_metadata_modal($organism, $data, $organism_data) {
    $json_val = $data['json_validation'];
    $org_safe = htmlspecialchars($organism);
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-file-code"></i> Organism Metadata: <?= $org_safe ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

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

      <h6 class="fw-bold mb-2"><i class="fa fa-clipboard-check"></i> File Status</h6>
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

      <?php echo generatePermissionAlert(
          $data['path'] . '/organism.json',
          'Metadata File Permission Issue',
          'The organism.json file has permission issues.',
          'file',
          $organism
      ); ?>

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Required Fields <?= req_info('All fields must be present and non-empty: genus, species, common_name, taxon_id.') ?></h6>
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
            <ul class="mb-0">
              <?php foreach ($json_val['required_fields'] as $field): ?>
                <li class="mb-1">
                  <span class="badge bg-success"><i class="fa fa-check"></i></span> <strong><?= htmlspecialchars($field) ?></strong>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-edit"></i> Metadata Editor</h6>
      <form id="metadataForm<?= $org_safe ?>" class="metadata-form">
        <input type="hidden" name="organism" value="<?= $org_safe ?>">
        <input type="hidden" name="images_json" id="images-json-<?= $org_safe ?>">
        <input type="hidden" name="html_p_json" id="html-p-json-<?= $org_safe ?>">

        <div class="mb-3">
          <label for="genus<?= $org_safe ?>" class="form-label">Genus <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="genus<?= $org_safe ?>" name="genus"
                 value="<?= htmlspecialchars($data['info']['genus'] ?? '') ?>" required>
          <small class="text-muted">e.g., Anoura</small>
        </div>

        <div class="mb-3">
          <label for="species<?= $org_safe ?>" class="form-label">Species <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="species<?= $org_safe ?>" name="species"
                 value="<?= htmlspecialchars($data['info']['species'] ?? '') ?>" required>
          <small class="text-muted">e.g., caudifer</small>
        </div>

        <div class="mb-3">
          <label for="common_name<?= $org_safe ?>" class="form-label">Common Name <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="common_name<?= $org_safe ?>" name="common_name"
                 value="<?= htmlspecialchars($data['info']['common_name'] ?? '') ?>" required>
          <small class="text-muted">e.g., Tailed Tailless Bat</small>
        </div>

        <div class="mb-3">
          <label for="taxon_id<?= $org_safe ?>" class="form-label">Taxon ID <span class="text-danger">*</span></label>
          <input type="text" class="form-control" id="taxon_id<?= $org_safe ?>" name="taxon_id"
                 value="<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>" required>
          <small class="text-muted">NCBI taxonomy ID, e.g., 27642</small>
        </div>

        <hr class="my-4">

        <h5 class="mb-3"><i class="fa fa-sitemap"></i> Feature Types</h5>
        <p class="small text-muted mb-3">Define which feature types are parents (typically genes) and which are children (transcripts, proteins, etc.).</p>

        <input type="hidden" name="parents_json" id="parents-json-<?= $org_safe ?>">
        <input type="hidden" name="children_json" id="children-json-<?= $org_safe ?>">

        <div class="row">
          <div class="col-md-6">
            <label class="form-label">Parent Features</label>
            <div id="parents-<?= $org_safe ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
              <?php
                $parents = $data['info']['feature_types']['parents'] ?? ['gene'];
                foreach ($parents as $feature):
              ?>
                <span class="badge bg-primary me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                  <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                </span>
              <?php endforeach; ?>
            </div>
            <input type="text" class="form-control mt-2" id="parent-feature-input-<?= $org_safe ?>" placeholder="e.g., gene, pseudogene">
            <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'parent')">
              <i class="fa fa-plus"></i> Add Parent Feature
            </button>
          </div>
          <div class="col-md-6">
            <label class="form-label">Child Features</label>
            <div id="children-<?= $org_safe ?>" class="feature-tag-container" style="border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; min-height: 50px; background: #f8f9fa;">
              <?php
                $children = $data['info']['feature_types']['children'] ?? ['mRNA', 'transcript'];
                foreach ($children as $feature):
              ?>
                <span class="badge bg-info me-2 mb-2 feature-tag" data-feature="<?= htmlspecialchars($feature) ?>">
                  <?= htmlspecialchars($feature) ?> <i class="fa fa-times" style="cursor: pointer;" onclick="removeFeatureTag(this, '<?= $org_safe ?>')"></i>
                </span>
              <?php endforeach; ?>
            </div>
            <input type="text" class="form-control mt-2" id="child-feature-input-<?= $org_safe ?>" placeholder="e.g., mRNA, transcript, protein">
            <button type="button" class="btn btn-sm btn-outline-info mt-2" onclick="addFeatureTag('<?= $org_safe ?>', 'child')">
              <i class="fa fa-plus"></i> Add Child Feature
            </button>
          </div>
        </div>

        <hr class="my-4">

        <h5 class="mb-3"><i class="fa fa-image"></i> Images</h5>
        <p class="small text-muted mb-3">
          <strong>Leave this empty and MOOP finds an image itself</strong> — you only need to fill it in
          to override that choice. It tries, in order: a cached
          <a href="https://www.ncbi.nlm.nih.gov/datasets/taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '') ?>/" target="_blank">NCBI
          Taxonomy (ID: <?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>)</a>
          image at <code>images/ncbi_taxonomy/<?= htmlspecialchars($data['info']['taxon_id'] ?? '[taxon_id]') ?>.jpg</code>
          if one is already present, then Wikipedia — downloaded once and cached in
          <code>images/wikimedia/</code>.
        </p>
        <div id="images-container-<?= $org_safe ?>">
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

        <h5 class="mb-3"><i class="fa fa-paragraph"></i> HTML Paragraphs</h5>
        <p class="small text-muted mb-3">
          <strong>Leave this empty and MOOP writes the description itself</strong> — a summary built from
          the organism's taxonomic lineage (via <code>taxon_id</code>) plus the Wikipedia extract, credited
          with a link back to the article. Add paragraphs here only to replace that text.
        </p>
        <div id="paragraphs-container-<?= $org_safe ?>">
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

        <div id="saveResult<?= $org_safe ?>"></div>

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
    <?php
}

// ---------------------------------------------------------------------------

function render_asm_modal($organism, $assembly, $data, $sequence_types, $groups_data, $organism_data) {
    $safe_asm_id         = preg_replace('/[^a-zA-Z0-9_-]/', '_', $organism . '_' . $assembly);
    $assembly_validation = $data['assembly_validation'];
    $assembly_path       = $data['path'] . '/' . $assembly;

    // FAI check (assembly-level)
    $genome_fa_path = $assembly_path . '/genome.fa';
    $fai_info = $data['fai_validation'][$assembly] ?? [
        'genome_fa_exists' => file_exists($genome_fa_path),
        'fai_exists'       => file_exists($genome_fa_path . '.fai'),
    ];
    $modal_has_missing_fai = ($fai_info['genome_fa_exists'] && !$fai_info['fai_exists']);

    $assembly_groups = getAssemblyGroups($organism, $assembly, $groups_data);

    // DB mismatch check
    $has_db_mismatch      = false;
    $db_mismatch_messages = [];
    $matching_genome      = null;
    if ($assembly_validation) {
        foreach ($assembly_validation['genomes'] as $genome) {
            if ($assembly === $genome['genome_name'] || $assembly === $genome['genome_accession']) {
                $matching_genome = $genome;
                break;
            }
        }
        if (!$matching_genome) {
            $has_db_mismatch        = true;
            $db_mismatch_messages[] = "Assembly directory '$assembly' does not match any genome_name or genome_accession in the database";
        }
    }

    // Canonical "Name (Accession)" label from the matched genome record (reuses the
    // db-directory check above); falls back to the bare directory name on a mismatch.
    $assembly_display = $matching_genome
        ? assembly_label($matching_genome['genome_name'] ?? '', $matching_genome['genome_accession'] ?? '')
        : $assembly;

    // Live scan: gene_set subdirs inside this assembly
    $gene_set_dirs_live = [];
    if (is_dir($assembly_path)) {
        foreach (array_diff(scandir($assembly_path), ['.', '..']) as $f) {
            if (is_dir("$assembly_path/$f")) $gene_set_dirs_live[] = $f;
        }
    }
    sort($gene_set_dirs_live);

    // Classify sequence types: assembly-level (file found directly) vs gene_set-level
    $asm_level_files = [];   // [type => ['config'=>..., 'file'=>string]]
    $gs_level_types  = [];   // [type => config]  — checked per gene_set
    foreach ($sequence_types as $type => $config) {
        $direct = glob("$assembly_path/*{$config['pattern']}") ?: [];
        if (!empty($direct)) {
            $asm_level_files[$type] = ['config' => $config, 'file' => basename($direct[0])];
        } else {
            $gs_level_types[$type] = $config;
        }
    }

    // Per gene_set: FASTA presence
    $gs_fasta_status = [];  // [gs => [type => ['found'=>bool, 'file'=>string|null]]]
    foreach ($gene_set_dirs_live as $gs) {
        $gs_fasta_status[$gs] = [];
        foreach ($gs_level_types as $type => $config) {
            $files = glob("$assembly_path/$gs/*{$config['pattern']}") ?: [];
            $gs_fasta_status[$gs][$type] = !empty($files)
                ? ['found' => true,  'file' => basename($files[0])]
                : ['found' => false, 'file' => null];
        }
    }

    // Per gene_set: BLAST indexes
    $gs_blast_status = [];  // [gs => blast_validation_result]
    foreach ($gene_set_dirs_live as $gs) {
        $gs_blast_status[$gs] = validateBlastIndexFiles("$assembly_path/$gs", $sequence_types);
    }

    // Compute missing flags from live data
    $is_missing_live = false;
    if (!empty($gs_level_types)) {
        if (empty($gene_set_dirs_live)) {
            $is_missing_live = true;
        } else {
            foreach ($gs_fasta_status as $gs_files) {
                foreach ($gs_files as $fi) {
                    if (!$fi['found']) { $is_missing_live = true; break 2; }
                }
            }
        }
    }
    $modal_has_missing_blast = false;
    foreach ($gs_blast_status as $bv) {
        foreach ($bv['databases'] ?? [] as $db) {
            if (!$db['has_indexes']) { $modal_has_missing_blast = true; break 2; }
        }
    }
    ?>
<div class="modal-dialog modal-lg">
  <div class="modal-content">
    <div class="modal-header">
      <h5 class="modal-title"><i class="fa fa-folder"></i> Assembly: <?= htmlspecialchars($assembly_display) ?></h5>
      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <div class="modal-body">

      <h6 class="fw-bold mb-2"><i class="fa fa-star"></i> Overall Status</h6>
      <div class="card mb-3">
        <div class="card-body">
          <?php if (!$has_db_mismatch && !$is_missing_live && !$modal_has_missing_blast && !$modal_has_missing_fai): ?>
            <span class="badge bg-success h6"><i class="fa fa-check-circle"></i> Assembly is Complete</span>
          <?php else: ?>
            <span class="badge bg-danger h6"><i class="fa fa-times-circle"></i> Assembly has Issues</span>
            <p class="mt-2 mb-0 text-muted small">Please fix the issues listed below.</p>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-tag"></i> Assembly Information</h6>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars($assembly_display) ?></p>
          <p class="mb-1"><strong>Organism:</strong> <?= htmlspecialchars($organism) ?></p>
          <p class="mb-0"><strong>Path:</strong> <?= htmlspecialchars($data['path'] . '/' . $assembly) ?></p>
        </div>
      </div>

      <?php if ($assembly_validation): ?>
        <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Directory Matching <?= req_info('The assembly directory name must match either the genome_name or genome_accession from the database.') ?></h6>
        <div class="card mb-3 <?= $has_db_mismatch ? 'border-danger border-2' : 'border-success' ?>">
          <div class="card-body small">
            <?php
              if ($matching_genome) {
                echo '<p class="mb-2"><strong>The assembly directory name "' . htmlspecialchars($assembly) . '" matches:</strong></p>';
                echo '<ul class="mb-0">';
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
                      $organism_path = $data['path'];
                      if (is_dir($organism_path)) {
                        $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                        foreach ($dirs as $dir) {
                          if (is_dir("$organism_path/$dir")) {
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
              <p class="mb-3 small">If you no longer need this assembly directory, you can delete it permanently. This action cannot be undone.</p>
              <div class="row mb-3">
                <div class="col-md-6">
                  <label for="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>" class="form-label">Directory to Delete</label>
                  <select class="form-select form-select-sm" id="dirToDelete<?= htmlspecialchars($safe_asm_id) ?>">
                    <option value="">-- Select directory to delete --</option>
                    <?php
                      if (is_dir($organism_path)) {
                        $dirs = array_diff(scandir($organism_path), ['.', '..', 'organism.json', basename($data['db_file'] ?? '')]);
                        foreach ($dirs as $dir) {
                          if (is_dir("$organism_path/$dir")) {
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

      <h6 class="fw-bold mb-2"><i class="fa fa-dna"></i> Reference genome</h6>
      <?php $has_genome_fa = $fai_info['genome_fa_exists']; ?>
      <div class="card mb-3 <?= !$has_genome_fa ? 'border-secondary' : ($fai_info['fai_exists'] ? 'border-success' : 'border-warning border-2') ?>">
        <div class="card-body small py-2">
          <?php if (!$has_genome_fa): ?>
            <span class="text-muted"><i class="fa fa-info-circle"></i> No <code>genome.fa</code> in this assembly — transcriptome / proteome only. A reference genome (and its FAI index) is not applicable.</span>
          <?php else: ?>
            <ul class="mb-0">
              <?php foreach ($asm_level_files as $type => $fi): ?>
                <li class="mb-1">
                  <span class="badge bg-success"><i class="fa fa-check"></i></span>
                  <strong><?= htmlspecialchars($fi['config']['label'] ?? $type) ?>:</strong> <?= htmlspecialchars($fi['file']) ?>
                </li>
              <?php endforeach; ?>
              <li class="mb-0">
                <?php if ($fai_info['fai_exists']): ?>
                  <span class="badge bg-success"><i class="fa fa-check"></i></span>
                  <strong>FAI index:</strong> <code>genome.fa.fai</code>
                  <?= req_info('A genome.fa.fai samtools index lets the SVG gene-model sequence viewer fetch region sequences.') ?>
                <?php else: ?>
                  <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
                  <strong>FAI index:</strong> <small class="text-danger"><code>genome.fa.fai</code> missing — the SVG sequence viewer will be unavailable.</small>
                  <div class="mt-2 p-2 bg-light border rounded small">
                    <strong class="d-block mb-2">To generate it, run on the server:</strong>
                    <?php $fai_asm_path = $organism_data . '/' . $organism . '/' . $assembly; ?>
                    <code class="d-block" style="word-break:break-all;white-space:normal;">cd <?= htmlspecialchars($fai_asm_path) ?> && \<br>samtools faidx genome.fa</code>
                  </div>
                <?php endif; ?>
              </li>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-layer-group"></i> Gene sets <small class="text-muted fw-normal">— sequences &amp; BLAST indexes</small></h6>
      <?php if (empty($gene_set_dirs_live)): ?>
        <div class="card mb-3 border-danger border-2">
          <div class="card-body small">
            <span class="badge bg-danger"><i class="fa fa-times"></i></span>
            No gene set subdirectories found inside this assembly.
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($gene_set_dirs_live as $gs): ?>
          <?php
            $gs_files      = $gs_fasta_status[$gs] ?? [];
            $bv            = $gs_blast_status[$gs] ?? ['databases' => [], 'total_count' => 0];
            $gs_fasta_miss = in_array(false, array_column($gs_files, 'found'), true);
            $gs_blast_miss = !empty(array_filter($bv['databases'] ?? [], fn($db) => !$db['has_indexes']));
            $gs_all_ok     = !$gs_fasta_miss && !$gs_blast_miss;
            $gs_border     = $gs_all_ok ? 'border-success' : 'border-warning border-2';
            $gs_hdr        = $gs_all_ok ? 'bg-success bg-opacity-10' : 'bg-warning bg-opacity-10';
          ?>
          <div class="card mb-2 <?= $gs_border ?>">
            <div class="card-header py-1 px-3 <?= $gs_hdr ?>">
              <strong><i class="fa fa-folder"></i> <?= htmlspecialchars($gs) ?></strong>
              <?php if ($gs_all_ok): ?>
                <span class="badge bg-success ms-2"><i class="fa fa-check"></i> Complete</span>
              <?php else: ?>
                <span class="badge bg-warning text-dark ms-2"><i class="fa fa-exclamation-triangle"></i> <?= $gs_fasta_miss ? 'Missing files' : 'Missing BLAST indexes' ?></span>
              <?php endif; ?>
            </div>
            <div class="card-body small py-2">
              <p class="text-muted small fw-bold mb-1">Sequences</p>
              <ul class="mb-2">
                <?php foreach ($gs_files as $type => $fi): ?>
                  <li class="mb-1">
                    <?php if ($fi['found']): ?>
                      <span class="badge bg-success"><i class="fa fa-check"></i></span>
                      <strong><?= htmlspecialchars($gs_level_types[$type]['label'] ?? $type) ?>:</strong> <?= htmlspecialchars($fi['file']) ?>
                    <?php else: ?>
                      <span class="badge bg-danger"><i class="fa fa-times"></i></span>
                      <strong><?= htmlspecialchars($gs_level_types[$type]['label'] ?? $type) ?>:</strong>
                      <small class="text-muted">Missing — expected pattern: *<?= htmlspecialchars($gs_level_types[$type]['pattern']) ?></small>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
              <p class="text-muted small fw-bold mb-1">BLAST indexes</p>
              <?php if (empty($bv['databases'])): ?>
                <p class="small text-muted mb-0">No FASTA files to index.</p>
              <?php else: ?>
                <ul class="mb-0">
                  <?php foreach ($bv['databases'] as $db): ?>
                    <li class="mb-2">
                      <?php if ($db['has_indexes']): ?>
                        <span class="badge bg-success"><i class="fa fa-check"></i></span>
                        <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                      <?php else: ?>
                        <span class="badge bg-warning"><i class="fa fa-exclamation-triangle"></i></span>
                        <strong><?= htmlspecialchars($db['name']) ?>:</strong> <?= htmlspecialchars($db['fasta']) ?>
                        <br><small class="text-danger">Missing: <?= htmlspecialchars(implode(', ', $db['missing_indexes'])) ?></small>
                        <div class="mt-2 p-2 bg-light border rounded small">
                          <strong class="d-block mb-1">To generate BLAST indexes, run on the server:</strong>
                          <?php
                            $gs_fullpath = $assembly_path . '/' . $gs;
                            $db_type     = strpos($db['fasta'], 'protein') !== false ? 'prot' : 'nucl';
                          ?>
                          <code class="d-block" style="word-break:break-all;white-space:normal;">cd <?= htmlspecialchars($gs_fullpath) ?> && \<br>makeblastdb -in <?= htmlspecialchars($db['fasta']) ?> -dbtype <?= htmlspecialchars($db_type) ?> -parse_seqids</code>
                        </div>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>


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

      <div class="alert <?= ($is_missing_live || $has_db_mismatch || $modal_has_missing_blast || $modal_has_missing_fai) ? 'alert-danger' : 'alert-success' ?>">
        <?php if ($has_db_mismatch || $is_missing_live || $modal_has_missing_blast || $modal_has_missing_fai): ?>
          <i class="fa fa-exclamation-circle"></i> <strong>Issues Found:</strong>
          <ul class="mb-0 mt-2">
            <?php if ($has_db_mismatch): ?><li>Directory name does not match any genome record in the database</li><?php endif; ?>
            <?php if ($is_missing_live): ?><li>Missing required FASTA files in one or more gene sets</li><?php endif; ?>
            <?php if ($modal_has_missing_blast): ?><li>Missing BLAST index files in one or more gene sets</li><?php endif; ?>
            <?php if ($modal_has_missing_fai): ?><li>Missing <code>genome.fa.fai</code> index (required for SVG sequence viewer)</li><?php endif; ?>
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
    <?php
}

// ---------------------------------------------------------------------------

function render_status_modal($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types) {
    $status      = getOrganismOverallStatus($organism, $data, $groups_data, $taxonomy_tree_file, $sequence_types);
    $checks      = $status['checks'];
    $pass_count  = $status['pass_count'];
    ?>
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
            <span class="badge <?= $status['all_pass'] ? 'bg-success' : 'bg-warning text-dark' ?> fs-6"><?= $pass_count ?>/<?= $status['total_count'] ?> Complete</span>
          </div>
          <div class="list-group">
            <?php
              $checklist = [
                  'has_assemblies'      => 'Has assemblies',
                  'has_fasta'           => 'Has FASTA files',
                  'has_blast_indexes'   => 'Has BLAST indexes',
                  'has_fai_index'       => 'Has FAI index',
                  'has_database'        => 'Has database file',
                  'database_valid'      => 'Database is valid',
                  'directories_match_db'=> 'Assembly & gene set dirs match DB',
                  'assemblies_in_groups'=> 'Assembly in organism groups',
                  'in_taxonomy_tree'    => 'In taxonomy tree',
                  'metadata_complete'   => 'Metadata complete',
              ];
              foreach ($checklist as $key => $label):
                  $pass = $checks[$key] ?? false;
                  $labelText = is_array($label) ? $label[0] : $label;
                  $labelExtra = is_array($label) ? $label[1] : '';
            ?>
              <div class="list-group-item <?= $pass ? '' : 'bg-light' ?>">
                <div class="d-flex align-items-center">
                  <?php if ($pass): ?>
                    <i class="fa fa-check-circle text-success me-2" style="font-size: 18px;"></i>
                  <?php else: ?>
                    <i class="fa fa-times-circle text-danger me-2" style="font-size: 18px;"></i>
                  <?php endif; ?>
                  <span><strong><?= htmlspecialchars($labelText) ?></strong> <?= $labelExtra ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
    </div>
  </div>
</div>
    <?php
}

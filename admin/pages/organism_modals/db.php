<?php
/**
 * Organism modal: database. Rendered by admin/api/get_organism_modal.php; the data comes
 * from organism_modal_db_data() in lib/organism_modals.php, so this file only displays it.
 */
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

      <h6 class="fw-bold mb-2"><i class="fa fa-file"></i> Database File <?= field_help('A valid SQLite database file named organism.sqlite must exist in the organism directory, readable by the web server.', 'What this section needs') ?></h6>
      <div class="card mb-3">
        <div class="card-body small">
          <p class="mb-1"><strong>Path:</strong> <?= htmlspecialchars($data['db_file'] ?? 'N/A') ?></p>
          <p class="mb-0">
            <strong>Readable:</strong>
            <?= $validation['readable'] ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-danger">No</span>' ?>
          </p>
        </div>
      </div>

      <h6 class="fw-bold mb-2"><i class="fa fa-check-square"></i> Database Validity <?= field_help('Must be a valid SQLite3 file with the proper structure — all required tables from the schema present.', 'What this section needs') ?></h6>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-table"></i> Database Tables <?= field_help('Required tables: organism, genome, gene_set, feature, annotation_source, annotation, feature_annotation.', 'What this section needs') ?></h6>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-layer-group"></i> Gene Sets <small class="text-muted fw-normal">— database records vs. on-disk directories</small> <?= field_help('Each gene_set row in the database should have a matching subdirectory inside its assembly on disk. This flags database gene sets whose directory is missing — e.g. data was loaded but the files were never deployed (or were removed). Per-assembly FASTA / BLAST file detail lives in the assembly modal.', 'Gene sets: database vs. disk') ?></h6>
      <?php /* $gene_sets_by_genome, $gene_set_query_error: organism_modal_db_data() */ ?>
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

      <h6 class="fw-bold mb-2"><i class="fa fa-exclamation-triangle"></i> Data Quality <?= field_help('Database records should have valid relationships and complete data — this checks for orphaned annotations, missing accessions, and features without proper organism links.', 'Data quality check') ?></h6>
      <div class="card mb-3 <?= empty($validation['data_issues']) ? 'border-success' : 'border-danger border-2' ?>">
        <div class="card-body small">
          <?php if (empty($validation['data_issues'])): ?>
            <p class="mb-0"><span class="badge bg-success"><i class="fa fa-check"></i></span> No data quality issues found</p>
          <?php else: ?>
            <p class="mb-2"><strong class="text-danger"><i class="fa fa-exclamation-circle"></i> Issues Found:</strong></p>
            <ul class="mb-0">
              <?php foreach ($validation['data_issues'] as $issue_i => $issue):
                  // data_issues (prose) and data_issue_codes are built in lockstep; explain by
                  // CODE, never by matching the prose (CLAUDE.md 9b) -- reword a message in the
                  // validator and a strpos() here silently stops matching.
                  $issue_code = $validation['data_issue_codes'][$issue_i] ?? ''; ?>
                <li class="mb-2">
                  <span class="text-danger"><?= htmlspecialchars($issue) ?></span>
                  <br>
                  <small class="text-muted">
                    <?php
                      if ($issue_code === 'orphaned-annotations') {
                        echo 'Annotations exist in the database but are not linked to any annotation source. These records cannot be properly accessed.';
                      } elseif ($issue_code === 'annotations-missing-accession') {
                        echo 'An accession is a unique identifier (like a UniProt ID or NCBI accession number). Annotations should have accession values for proper identification and linking to external databases. Missing accessions prevent proper data cross-referencing.';
                      } elseif ($issue_code === 'features-without-organism') {
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
        <h6 class="fw-bold mb-2"><i class="fa fa-folder"></i> Assembly Validation <?= field_help('For each genome record in the database, a directory must exist in the organism folder whose name matches the genome_name or genome_accession.', 'What this section needs') ?></h6>
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
                        // existing_gs_dirs + gs_feature_count (an empty leftover row vs undeployed data) are
                        // looked up in organism_modal_db_data(), not queried from here.
                        ['existing_gs_dirs' => $existing_gs_dirs, 'gs_feature_count' => $gs_feature_count]
                            = $gene_set_mismatch_details[$mismatch['assembly_dir'] . '/' . $mismatch['gene_set_name']];
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
                            <button class="btn btn-info btn-sm" onclick="renameGeneSetDirectory(event, <?= htmlspecialchars(json_encode($organism)) ?>, <?= htmlspecialchars(json_encode($mismatch['assembly_dir'])) ?>, <?= htmlspecialchars(json_encode($gs_safe_id)) ?>)">
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
            <button class="btn btn-warning btn-sm" onclick="fixDatabasePermissions(event, <?= htmlspecialchars(json_encode($organism)) ?>)">
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
    
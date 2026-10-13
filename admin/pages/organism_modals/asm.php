<?php
/**
 * Organism modal: assembly. Rendered by admin/api/get_organism_modal.php; the data comes
 * from organism_modal_asm_data() in lib/organism_modals.php, so this file only displays it.
 */
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
        <h6 class="fw-bold mb-2"><i class="fa fa-database"></i> Database Directory Matching <?= field_help('The assembly directory name must match either the genome_name or genome_accession from the database.', 'What this section needs') ?></h6>
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
                      foreach ($assembly_dir_options as $dir) {
                        echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
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
                      foreach ($assembly_dir_options as $dir) {
                        echo '<option value="' . htmlspecialchars($dir) . '">' . htmlspecialchars($dir) . '</option>';
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
                  <?= field_help('A genome.fa.fai samtools index lets the SVG gene-model sequence viewer fetch region sequences.', 'What this section needs') ?>
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
                <span class="badge bg-warning text-dark ms-2"><i class="fa fa-exclamation-triangle"></i> <?= $gs_fasta_miss ? 'No ' . htmlspecialchars(implode(', ', $gs_missing_labels[$gs] ?? [])) . ' FASTA' : 'Missing BLAST indexes' ?></span>
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
                      <small class="text-muted">Missing — expected file: <?= htmlspecialchars($gs_level_types[$type]['pattern']) ?></small>
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
                            $db_type     = sequence_type_blast_dbtype($db['type']);
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
            <?php if ($is_missing_live && empty($gene_set_dirs_live)): ?><li>No gene set data in this assembly</li><?php endif; ?>
            <?php foreach ($gs_missing_labels as $gs => $labels): ?>
              <li>Gene set <code><?= htmlspecialchars($gs) ?></code> has no <?= htmlspecialchars(implode(', ', $labels)) ?> FASTA</li>
            <?php endforeach; ?>
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
    
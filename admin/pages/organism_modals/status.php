<?php
/**
 * Organism modal: overall status. Rendered by admin/api/get_organism_modal.php; the data comes
 * from organism_modal_status_data() in lib/organism_modals.php, so this file only displays it.
 */
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
            <h6 class="mb-0"><i class="fa fa-tasks"></i> <strong>Setup Checklist</strong></h6>
            <span class="badge <?= $status['all_pass'] ? 'bg-success' : 'bg-warning text-dark' ?> fs-6"><?= $pass_count ?>/<?= $status['total_count'] ?> Complete</span>
          </div>
          <div class="list-group">
            <?php
              $checklist = [
                  'has_assemblies'      => 'Has assemblies',
                  'has_fasta'           => 'Has FASTA files',
                  'gene_sets_complete'  => 'Every assembly has a gene set with Protein, mRNA and CDS FASTA',
                  'has_blast_indexes'   => 'Has BLAST indexes',
                  'has_fai_index'       => 'Has FAI index',
                  'seqnames_match_genome' => 'Gene sets name chromosomes as the genome does',
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
    
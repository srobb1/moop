<?php
/**
 * Reusable Annotation Types modal.
 *
 * Requires $ann_type_info to be in scope:
 *   [ type => ['color' => '...', 'description' => '...'], ... ]
 *
 * Trigger with:
 *   data-bs-toggle="modal" data-bs-target="#ann-types-modal"
 */
?>
<div class="modal fade" id="ann-types-modal" tabindex="-1"
     aria-labelledby="ann-types-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h5 class="modal-title fw-bold" id="ann-types-modal-label">Annotation Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">

        <p class="mb-3">
          An <strong>annotation</strong> is a functional description attached to a genomic feature —
          for example, a gene name, protein function, GO term, or database cross-reference.
          Most annotations are added by running bioinformatic analysis tools such as
          BLAST or InterProScan.
        </p>

        <p class="text-muted mb-4">
          Annotations are grouped by type below.
          Select only the types relevant to your query — for example, choose <em>Gene Ontology</em>
          to find genes by GO term, or <em>Domains</em> to search protein domain annotations.
        </p>

        <div class="row g-3">
          <?php foreach ($ann_type_info as $type => $info):
            $color = htmlspecialchars($info['color']);
            $desc  = $info['description'];
            if (!$desc) continue;
          ?>
          <div class="col-md-6">
            <div class="card h-100 border-0 shadow-sm">
              <div class="card-body">
                <h6 class="card-title mb-2">
                  <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($type) ?></span>
                </h6>
                <p class="card-text small text-muted mb-0"><?= $desc ?></p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

      </div>
    </div>
  </div>
</div>

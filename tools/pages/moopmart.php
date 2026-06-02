<?php
/**
 * MOOPmart — Feature List Builder
 * Variables: $scope_tree, $organism_info, $organism_groups,
 *            $annotation_source_names, $annotation_source_types
 */

// Group color hash (matches index.js GROUP_COLORS palette)
$gp = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'];
$groupColor = fn($n) => $gp[abs(array_sum(array_map('ord', str_split($n))) * 31) % count($gp)];
?>
<div class="container-fluid py-3">

  <!-- Header -->
  <div class="mb-4">
    <h4 class="mb-1 moop-tool-title text-dark">MOOPmart — Feature List Builder</h4>
    <p class="text-muted mb-0 small">
      Build a list of genomic features and download as TSV or FASTA.
      Use <a href="search.php" class="text-decoration-none">Annotation Search</a> to explore specific genes first.
    </p>
  </div>

  <!-- ① Select Organisms -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">1</span>
      <span class="fw-semibold me-auto" style="font-size:0.9rem;">Select organisms</span>
      <div class="d-flex gap-1">
        <button type="button" class="btn btn-sm btn-outline-light py-0" id="mm-select-all">All</button>
        <button type="button" class="btn btn-sm btn-outline-light py-0" id="mm-clear-all">None</button>
      </div>
    </div>
    <div class="px-2 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
      <input type="text" class="form-control form-control-sm moop-input" id="mm-scope-filter"
             placeholder="Filter by group, organism, assembly, gene set…" autocomplete="off">
      <div class="form-check form-switch mb-0 flex-shrink-0">
        <input class="form-check-input" type="checkbox" role="switch" id="mm-scope-detail">
        <label class="form-check-label small text-muted text-nowrap" for="mm-scope-detail">Details</label>
      </div>
    </div>
    <div style="overflow-y:auto; max-height:180px; background:#fff;" id="mm-scope-list" class="mm-scope-detail-hidden">
      <?php if (empty($scope_tree)): ?>
        <p class="text-muted small p-3">No accessible organisms found.</p>
      <?php else:
        $rowIdx = 0;
        foreach ($scope_tree as $organism => $assemblies):
          $info   = $organism_info[$organism] ?? [];
          $label  = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);
          $cn     = $info['common_name'] ?? '';
          $groups = $organism_groups[$organism] ?? [];
          foreach ($assemblies as $asm => $gene_sets):
            foreach ($gene_sets as $gs):
              $rowIdx++;
              $gsid = 'mm_gs_' . $rowIdx;
              $searchSimple = strtolower("$label $cn " . implode(' ', $groups));
              $searchDetail = strtolower("$asm $gs");
              $search = $searchSimple . ' ' . $searchDetail;
      ?>
      <div class="org-select-row mm-scope-row"
           data-search="<?= htmlspecialchars($search) ?>"
           data-search-simple="<?= htmlspecialchars($searchSimple) ?>"
           data-search-detail="<?= htmlspecialchars($searchDetail) ?>">
        <input type="checkbox" class="mm-gs-cb visually-hidden"
               id="<?= $gsid ?>"
               data-org="<?= htmlspecialchars($organism) ?>"
               data-asm="<?= htmlspecialchars($asm) ?>"
               data-gs="<?= htmlspecialchars($gs) ?>">
        <span class="org-groups flex-shrink-0">
          <?php foreach ($groups as $g): ?>
          <span class="org-group-chip" style="background:<?= $groupColor($g) ?>"><?= htmlspecialchars($g) ?></span>
          <?php endforeach; ?>
        </span>
        <span class="flex-grow-1 text-truncate" style="min-width:0;">
          <em><?= htmlspecialchars($label) ?></em>
          <?php if ($cn): ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($cn) ?></span><?php endif; ?>
          <span class="mm-scope-row-detail text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asm) ?> › <?= htmlspecialchars($gs ?: '(default)') ?></span>
        </span>
        <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
      </div>
      <?php endforeach; endforeach; endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="px-3 py-1 border-top d-flex align-items-center" style="background:#f8f9fa; font-size:0.8rem;">
      <span class="text-muted" id="mm-scope-counts">Select at least one organism above</span>
    </div>
  </div>

  <!-- ② Build Your List -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">2</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Build your list</span>
      <small class="ms-auto" style="color:rgba(255,255,255,0.75); font-size:0.78rem;">all sections optional</small>
    </div>
    <div class="card-body pt-2 pb-3">

      <!-- AND / OR logic toggle -->
      <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
        <span class="small fw-semibold text-muted">Combine sections with:</span>
        <div class="btn-group btn-group-sm" role="group">
          <input type="radio" class="btn-check" name="mm-logic" id="mm-logic-and" value="and" checked>
          <label class="btn btn-outline-secondary" for="mm-logic-and">AND</label>
          <input type="radio" class="btn-check" name="mm-logic" id="mm-logic-or" value="or">
          <label class="btn btn-outline-secondary" for="mm-logic-or">OR</label>
        </div>
        <span class="small text-muted fst-italic" id="mm-logic-hint">Features must match ALL filled sections</span>
      </div>

      <!-- Accordion sections -->
      <div class="d-flex flex-column gap-2">

        <!-- By Feature IDs -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ids-header" role="button"
               aria-expanded="false" aria-controls="mm-ids-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ids-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature IDs</span>
            </span>
          </div>
          <div class="collapse" id="mm-ids-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Paste a list of feature IDs — separate them with commas, spaces, or new lines.</p>
              <textarea id="mm-feature-ids" class="form-control moop-input" rows="4"
                        placeholder="Enter one or more feature IDs&#10;e.g.  gene1, gene2&#10;or one per line"></textarea>
            </div>
          </div>
        </div>

        <!-- By Feature Name -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-name-header" role="button"
               aria-expanded="false" aria-controls="mm-name-body"
               data-bs-toggle="collapse" data-bs-target="#mm-name-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature Name</span>
            </span>
          </div>
          <div class="collapse" id="mm-name-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Partial match, case-insensitive. Searches the feature name field.
                <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer;" data-help-type="basic"></i>
              </p>
              <input type="text" id="mm-gene-name" class="form-control moop-input"
                     placeholder="e.g. BRCA1">
            </div>
          </div>
        </div>

        <!-- By Feature Description -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-desc-header" role="button"
               aria-expanded="false" aria-controls="mm-desc-body"
               data-bs-toggle="collapse" data-bs-target="#mm-desc-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Feature Description</span>
            </span>
          </div>
          <div class="collapse" id="mm-desc-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">Searches the feature description field. Partial match, case-insensitive.
                <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer;" data-help-type="basic"></i>
              </p>
              <input type="text" id="mm-gene-description" class="form-control moop-input"
                     placeholder="e.g. kinase">
            </div>
          </div>
        </div>

        <!-- By Annotation -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-ann-filter-header" role="button"
               aria-expanded="false" aria-controls="mm-ann-filter-body"
               data-bs-toggle="collapse" data-bs-target="#mm-ann-filter-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Annotation</span>
            </span>
          </div>
          <div class="collapse" id="mm-ann-filter-body">
            <div class="browse-select-panel">
              <p class="text-muted small mb-2">
                Restrict to features that have at least one annotation from the selected type.
                Leave all unchecked to include features regardless of annotation type.
                Fill accession or keyword to further narrow within the selected types.
                <button type="button" class="btn btn-link btn-sm p-0 ms-1 text-muted"
                        style="font-size:0.85rem; line-height:1; vertical-align:middle;"
                        data-bs-toggle="modal" data-bs-target="#ann-types-modal" title="About annotation types">
                  <i class="fa fa-info-circle"></i>
                </button>
              </p>

              <!-- Annotation type checkbox list -->
              <div class="mb-3">
                <div class="d-flex align-items-center mb-1">
                  <span class="small fw-semibold text-muted me-auto">Annotation type</span>
                  <span class="small text-muted fst-italic" id="mm-filter-ann-count">none selected</span>
                </div>
                <div class="px-2 pt-1 pb-1 border-bottom" style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:0.375rem 0.375rem 0 0;">
                  <input type="text" class="form-control form-control-sm moop-input border-0 bg-transparent p-0"
                         id="mm-filter-ann-input" placeholder="Filter annotation types…" autocomplete="off">
                </div>
                <div style="max-height:150px; overflow-y:auto; background:#fff; border:1px solid #dee2e6; border-top:none; border-radius:0 0 0.375rem 0.375rem;"
                     id="mm-filter-ann-panel">
                  <?php foreach ($annotation_source_types as $type => $type_data):
                    $type_safe = 'mm-filter-atype-' . preg_replace('/[^a-z0-9]/i', '_', $type);
                    $color     = htmlspecialchars($type_data['color']);
                    $src_count = count($type_data['sources']);
                  ?>
                  <div class="mm-filter-ann-group">
                    <div class="d-flex align-items-center px-2 py-1" style="background:#f8f9fa; border-bottom:1px solid #e9ecef;">
                      <input type="checkbox" class="form-check-input me-2 mb-0 mm-filter-ann-type-cb flex-shrink-0"
                             id="<?= $type_safe ?>" data-type="<?= htmlspecialchars($type) ?>">
                      <label for="<?= $type_safe ?>" class="form-check-label fw-semibold mb-0 me-auto"
                             style="cursor:pointer; font-size:0.85rem;">
                        <span class="badge bg-<?= $color ?> me-1"><?= htmlspecialchars($type) ?></span>
                      </label>
                      <span class="text-muted" style="font-size:0.72rem;"><?= $src_count ?> source<?= $src_count !== 1 ? 's' : '' ?></span>
                    </div>
                    <div class="ps-3">
                      <?php foreach ($type_data['sources'] as $src_name):
                        $safe_id = 'mm-filter-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                      ?>
                      <div class="d-flex align-items-center gap-1 px-1 py-1 mm-filter-ann-item">
                        <input type="checkbox" class="form-check-input flex-shrink-0 mm-filter-ann-src-cb mb-0"
                               id="<?= $safe_id ?>" value="<?= htmlspecialchars($src_name) ?>"
                               data-type="<?= htmlspecialchars($type) ?>">
                        <label class="form-check-label mb-0" for="<?= $safe_id ?>"
                               style="cursor:pointer; font-size:0.82rem;">
                          <?= htmlspecialchars($src_name) ?>
                        </label>
                      </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              </div>

              <!-- Accession and keyword -->
              <div class="row g-2">
                <div class="col-sm-6">
                  <label class="form-label small mb-1">Accession <span class="text-muted">(exact, e.g. GO:0006351)</span></label>
                  <input type="text" id="mm-annotation-accession" class="form-control form-control-sm moop-input" placeholder="GO:0000000">
                </div>
                <div class="col-sm-6">
                  <label class="form-label small mb-1">Annotation keyword
                    <i class="fa fa-info-circle search-instructions-trigger ms-1" style="cursor:pointer;" data-help-type="basic"></i>
                  </label>
                  <input type="text" id="mm-annotation-keyword" class="form-control form-control-sm moop-input" placeholder="e.g. transporter">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- By Chromosomal Location -->
        <div>
          <div class="browse-select-header browse-select-header--light mb-0" id="mm-loc-header" role="button"
               aria-expanded="false" aria-controls="mm-loc-body"
               data-bs-toggle="collapse" data-bs-target="#mm-loc-body">
            <span class="d-flex align-items-center gap-2 w-100">
              <i class="fas fa-chevron-down browse-select-chevron"></i>
              <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">By Chromosomal Location</span>
            </span>
          </div>
          <div class="collapse" id="mm-loc-body">
            <div class="browse-select-panel">
              <p class="small text-muted fst-italic mb-2" id="mm-coord-note">
                Select exactly one assembly in Step 1 to enable location search.
              </p>
              <div class="row g-2">
                <div class="col-sm-4">
                  <label class="form-label small mb-1">Chr / scaffold</label>
                  <input type="text" id="mm-coord-chr" class="form-control form-control-sm moop-input"
                         placeholder="e.g. CHR01" list="mm-chr-datalist" autocomplete="off" disabled>
                  <datalist id="mm-chr-datalist"></datalist>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1">Start <span class="text-muted">(1-based)</span></label>
                  <input type="number" id="mm-coord-start" class="form-control form-control-sm" placeholder="1" min="1" disabled>
                </div>
                <div class="col-sm-4">
                  <label class="form-label small mb-1">End <span class="text-muted">(1-based)</span></label>
                  <input type="number" id="mm-coord-end" class="form-control form-control-sm" placeholder="1000000" min="1" disabled>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /accordion sections -->
    </div>
  </div>

  <!-- ③ Design Your Output -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">3</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Design your output</span>
    </div>
    <div class="card-body pt-3">

      <!-- Format toggle -->
      <div class="d-flex align-items-center gap-3 mb-4">
        <span class="small fw-semibold text-muted">Format:</span>
        <div class="btn-group" role="group">
          <input type="radio" class="btn-check" name="mm-format" id="mm-format-tsv" value="tsv" checked>
          <label class="btn btn-outline-secondary" for="mm-format-tsv">
            <i class="fa fa-file-alt me-1"></i>TSV
          </label>
          <input type="radio" class="btn-check" name="mm-format" id="mm-format-fasta" value="fasta">
          <label class="btn btn-outline-secondary" for="mm-format-fasta">
            <i class="fa fa-dna me-1"></i>FASTA
          </label>
        </div>
      </div>

      <!-- TSV options -->
      <div id="mm-tsv-options">

        <!-- Wide / Long -->
        <div class="mb-4">
          <div class="small fw-semibold text-muted mb-2">Table layout</div>
          <div class="d-flex align-items-center gap-2">
            <div class="btn-group btn-group-sm" role="group">
              <input type="radio" class="btn-check" name="mm-ann-format" id="mm-ann-wide" value="wide" checked>
              <label class="btn btn-outline-secondary" for="mm-ann-wide" title="One row per feature">Wide</label>
              <input type="radio" class="btn-check" name="mm-ann-format" id="mm-ann-long" value="long">
              <label class="btn btn-outline-secondary" for="mm-ann-long" title="One row per annotation">Long</label>
            </div>
            <i class="fa fa-info-circle text-muted" style="cursor:pointer;"
               data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
               data-bs-title="Table layout"
               data-bs-content="<strong>Wide</strong> — one row per feature. Multiple annotation values for the same source are joined with '; '<br><br><strong>Long</strong> — one row per annotation. Columns become <em>annotation_source</em>, <em>annotation_id</em>, <em>annotation_description</em>."></i>
          </div>
        </div>

        <!-- Feature columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2">Feature columns</div>
          <div class="d-flex flex-wrap gap-2" id="mm-feature-col-checks">
            <?php
            $feat_cols = [
              'organism'     => 'Organism',
              'assembly'     => 'Assembly',
              'gene_set'     => 'Gene Set',
              'feature_type' => 'Feature Type',
              'feature_id'   => 'Feature ID',
              'chr'          => 'Chr',
              'start'        => 'Start',
              'stop'         => 'Stop',
              'strand'       => 'Strand',
            ];
            foreach ($feat_cols as $val => $lbl):
              $id = 'mm-col-' . $val;
            ?>
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input mm-feat-col" type="checkbox" id="<?= $id ?>"
                     value="<?= $val ?>" checked>
              <label class="form-check-label small" for="<?= $id ?>"><?= $lbl ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Annotation columns -->
        <div class="mb-3">
          <div class="small fw-semibold text-muted mb-2">Annotation columns</div>
          <div class="d-flex flex-wrap gap-2">
            <?php
            $ann_cols = [
              'ann_type'        => 'Ann. Type',
              'ann_source'      => 'Ann. Source',
              'ann_id'          => 'Ann. ID',
              'ann_description' => 'Ann. Description',
            ];
            foreach ($ann_cols as $val => $lbl):
              $id = 'mm-col-' . $val;
            ?>
            <div class="form-check form-check-inline mb-0">
              <input class="form-check-input mm-ann-col-basic" type="checkbox" id="<?= $id ?>"
                     value="<?= $val ?>" checked>
              <label class="form-check-label small" for="<?= $id ?>"><?= $lbl ?></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Annotation sources panel -->
        <?php if (!empty($annotation_source_types)): ?>
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <div class="small fw-semibold text-muted">Annotation types to include</div>
            <div class="d-flex gap-1 ms-auto">
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-all">All</button>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="mm-ann-none">None</button>
            </div>
          </div>
          <div class="px-2 pb-1">
            <input type="text" class="form-control form-control-sm moop-input" id="mm-ann-filter"
                   placeholder="Filter annotation types…" autocomplete="off">
          </div>
          <div id="mm-ann-panel" style="overflow-y:auto; max-height:220px;" class="border rounded mt-1 p-2">
            <?php foreach ($annotation_source_types as $type => $type_data):
              $type_safe = 'mm-atype-' . preg_replace('/[^a-z0-9]/i', '_', $type);
              $color     = htmlspecialchars($type_data['color']);
            ?>
            <div class="mm-ann-group mb-2">
              <div class="d-flex align-items-center px-1 py-1 rounded mb-1" style="background:#f1f3f5;">
                <input type="checkbox" class="form-check-input me-2 mb-0 mm-ann-type-cb flex-shrink-0"
                       id="<?= $type_safe ?>" data-type="<?= htmlspecialchars($type) ?>">
                <label for="<?= $type_safe ?>" class="form-check-label fw-semibold mb-0 me-auto"
                       style="cursor:pointer; font-size:0.88rem;">
                  <span class="badge bg-<?= $color ?> me-1"><?= htmlspecialchars($type) ?></span>
                </label>
              </div>
              <div class="ps-3">
                <?php foreach ($type_data['sources'] as $src_name):
                  $safe_id = 'mm-ann-' . preg_replace('/[^a-z0-9]/i', '_', $src_name);
                ?>
                <div class="d-flex align-items-center gap-1 px-1 py-1 mm-ann-item">
                  <input type="checkbox" class="form-check-input flex-shrink-0 mm-ann-col mb-0"
                         id="<?= $safe_id ?>"
                         value="<?= htmlspecialchars($src_name) ?>"
                         data-type="<?= htmlspecialchars($type) ?>">
                  <label class="form-check-label mb-0" for="<?= $safe_id ?>"
                         style="cursor:pointer; font-size:0.82rem;">
                    <?= htmlspecialchars($src_name) ?>
                  </label>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="small text-muted mt-1" id="mm-ann-counts"></div>
        </div>
        <?php endif; ?>

      </div><!-- /mm-tsv-options -->

      <!-- FASTA options -->
      <div id="mm-fasta-options" class="d-none">
        <div class="small fw-semibold text-muted mb-2">Sequence type</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <?php
          $fasta_modes = [
            'gene'       => 'Genomic',
            'transcript' => 'mRNA',
            'cds'        => 'CDS',
            'protein'    => 'Protein',
            'upstream'   => 'Upstream',
            'downstream' => 'Downstream',
          ];
          foreach ($fasta_modes as $mode => $lbl):
          ?>
          <div class="form-check form-check-inline mb-0">
            <input class="form-check-input mm-fasta-mode" type="radio" name="mm-fasta-type"
                   id="mm-fasta-<?= $mode ?>" value="<?= $mode ?>"
                   <?= $mode === 'gene' ? 'checked' : '' ?>>
            <label class="form-check-label small" for="mm-fasta-<?= $mode ?>"><?= $lbl ?></label>
          </div>
          <?php endforeach; ?>
        </div>
        <div id="mm-flank-wrap" class="d-none">
          <label class="form-label small mb-1">Flank size (bp)</label>
          <input type="number" id="mm-flank-bp" class="form-control form-control-sm moop-input"
                 style="max-width:160px;" placeholder="e.g. 2000" min="1" max="100000">
        </div>
      </div>

    </div>
  </div>

  <!-- ④ Preview & Download -->
  <div class="card mb-3 shadow-sm">
    <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">4</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Preview &amp; Download</span>
      <i class="fa fa-info-circle ms-1" style="cursor:pointer; color:rgba(255,255,255,0.7);"
         data-bs-toggle="popover" data-bs-placement="right" data-bs-html="true"
         data-bs-title="Preview &amp; Download"
         data-bs-content="Preview shows the first 100 matching features so you can verify your list before downloading. The download exports <strong>all</strong> matching features."></i>
    </div>
    <div class="card-body py-3 d-flex align-items-center gap-3 flex-wrap">
      <button type="button" class="btn btn-outline-primary" id="mm-preview-btn">
        <span id="mm-count-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
        <i class="fa fa-eye me-1"></i> Preview
      </button>
      <button type="button" class="btn btn-tool-emerald" id="mm-dl-btn">
        <i class="fa fa-download me-1"></i> <span id="mm-dl-label">Download TSV</span>
      </button>
      <div id="mm-count-result" class="small text-muted"></div>
    </div>
  </div>

  <!-- Results preview table -->
  <div id="mm-results-section" class="d-none mt-3">
    <div class="card">
      <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Preview <small id="mm-results-caption" class="text-muted fw-normal ms-1"></small></span>
        <span class="text-muted small">Download exports the full result set.</span>
      </div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table id="mm-results-table" class="table table-sm table-striped table-hover w-100" style="font-size:0.85rem;"></table>
        </div>
      </div>
    </div>
  </div>

</div>

<?php include_once __DIR__ . '/../../includes/ann_types_modal.php'; ?>

<style>
/* Simple/detail toggle for organism scope list */
#mm-scope-list.mm-scope-detail-hidden .mm-scope-row-detail { display: none; }
</style>

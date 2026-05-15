<?php
/**
 * DOWNLOADS - Display Page
 * Rendered by tools/downloads.php via display-template.php.
 * Variables available: $download_tree, $site, $siteTitle, $page_title,
 *                      $context_organism, $context_assembly, $context_group,
 *                      $display_name, $filter_organisms
 */
$filter_organisms = $filter_organisms ?? [];
$has_context = !empty($context_organism) || !empty($context_assembly)
            || !empty($context_group)    || !empty($filter_organisms);
$clear_url   = '/' . $site . '/tools/downloads.php';
?>
<div class="container mt-5">
  <div class="row mb-3">
    <div class="col-12">
      <h2 class="mb-1"><i class="fas fa-download me-2"></i>Downloads</h2>
      <p class="text-muted mb-0">Browse and download genome files for organisms you have access to.</p>
    </div>
  </div>

  <?php if ($has_context): ?>
  <div class="alert alert-info d-flex align-items-center py-2 mb-3">
    <i class="fas fa-filter me-2 flex-shrink-0"></i>
    <span class="me-3">
      Filtered to:
      <?php
        $parts = [];
        if (!empty($display_name)) {
            $parts[] = '<strong>' . htmlspecialchars($display_name) . '</strong>';
        } elseif (!empty($context_group)) {
            $parts[] = 'group <strong>' . htmlspecialchars($context_group) . '</strong>';
        } elseif (!empty($filter_organisms)) {
            $n = count($filter_organisms);
            if ($n === 1) {
                $parts[] = '<strong><em>' . htmlspecialchars(str_replace('_', ' ', $filter_organisms[0])) . '</em></strong>';
            } elseif ($n <= 3) {
                $names = array_map(fn($o) => '<em>' . htmlspecialchars(str_replace('_', ' ', $o)) . '</em>', $filter_organisms);
                $parts[] = implode(', ', $names);
            } else {
                $parts[] = '<strong>' . $n . ' organisms</strong>';
            }
        } elseif (!empty($context_organism)) {
            $parts[] = '<strong><em>' . htmlspecialchars(str_replace('_', ' ', $context_organism)) . '</em></strong>';
        }
        if (!empty($context_assembly)) $parts[] = 'assembly <strong>' . htmlspecialchars($context_assembly) . '</strong>';
        if (!empty($context_gene_set)) $parts[] = 'gene set <strong>' . htmlspecialchars($context_gene_set) . '</strong>';
        echo implode(', ', $parts);
      ?>
    </span>
    <a href="<?= htmlspecialchars($clear_url) ?>" class="btn btn-sm btn-outline-secondary ms-auto flex-shrink-0">
      <i class="fas fa-times me-1"></i>Clear filter
    </a>
  </div>
  <?php endif; ?>

  <?php if (empty($download_tree)): ?>
    <div class="alert alert-info">
      <i class="fas fa-info-circle me-1"></i>
      No downloadable files were found for your current access level
      <?= !empty($context_organism) ? ' for ' . htmlspecialchars(str_replace('_', ' ', $context_organism)) : '' ?>.
    </div>
  <?php else: ?>

  <!-- Controls -->
  <div class="row align-items-center mb-3 g-2">
    <?php if (count($download_tree) > 1): ?>
    <div class="col-md-4 col-lg-3">
      <input type="text" id="organism-filter" class="form-control form-control-sm"
             placeholder="Filter organisms…" autocomplete="off">
    </div>
    <?php endif; ?>
    <div class="col d-flex gap-2 flex-wrap">
      <button id="expand-all-btn" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-chevron-down me-1"></i>Expand All
      </button>
      <button id="collapse-all-btn" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-chevron-right me-1"></i>Collapse All
      </button>
      <button id="select-all-btn" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-check-square me-1"></i>Select All
      </button>
      <button id="deselect-all-btn" class="btn btn-sm btn-outline-secondary">
        <i class="far fa-square me-1"></i>Deselect All
      </button>
      <button id="download-selected-btn" class="btn btn-sm btn-primary" disabled>
        <i class="fas fa-download me-1"></i>Download Selected
        (<span id="selected-count">0</span> files<span id="selected-size-label"></span>)
      </button>
    </div>
  </div>

  <!-- Download tree -->
  <div id="download-tree">
    <?php $org_idx = 0; foreach ($download_tree as $organism => $assemblies): $org_idx++; ?>
    <?php $org_id = 'org_' . $org_idx; $org_display = str_replace('_', ' ', $organism); ?>

    <div class="organism-block card mb-2"
         data-organism-name="<?= htmlspecialchars(strtolower($org_display)) ?>">

      <!-- Organism header -->
      <div class="card-header d-flex align-items-center py-2 organism-header"
           style="cursor:pointer;"
           data-bs-toggle="collapse"
           data-bs-target="#<?= $org_id ?>">
        <input type="checkbox"
               class="form-check-input me-2 flex-shrink-0 org-checkbox"
               id="cb-<?= $org_id ?>"
               data-org-id="<?= $org_id ?>"
               onclick="event.stopPropagation()">
        <label class="form-check-label fw-bold me-auto mb-0 user-select-none"
               for="cb-<?= $org_id ?>"
               style="cursor:pointer;"
               onclick="event.stopPropagation()">
          <em><?= htmlspecialchars($org_display) ?></em>
        </label>
        <small class="text-muted me-3 flex-shrink-0">
          <?= count($assemblies) ?> assembly<?= count($assemblies) !== 1 ? 'ies' : '' ?>
        </small>
        <i class="fas fa-chevron-down toggle-icon text-muted"></i>
      </div>

      <!-- Assemblies collapse -->
      <div class="collapse" id="<?= $org_id ?>">
        <div class="card-body py-2 px-3">
          <?php $asm_idx = 0; foreach ($assemblies as $assembly => $gene_sets): $asm_idx++; ?>
          <?php $asm_id = $org_id . '_asm_' . $asm_idx;
                $asm_total_files = array_sum(array_column($gene_sets, 'file_count'));
          ?>

          <div class="assembly-block mb-2">
            <!-- Assembly header -->
            <div class="d-flex align-items-center px-2 py-2 rounded assembly-header"
                 style="cursor:pointer; background:#d97706; color:white;"
                 data-bs-toggle="collapse"
                 data-bs-target="#<?= $asm_id ?>">
              <input type="checkbox"
                     class="form-check-input me-2 flex-shrink-0 asm-checkbox"
                     id="cb-<?= $asm_id ?>"
                     data-org-id="<?= $org_id ?>"
                     data-asm-id="<?= $asm_id ?>"
                     onclick="event.stopPropagation()">
              <label class="form-check-label fw-semibold me-auto mb-0 user-select-none text-white"
                     for="cb-<?= $asm_id ?>"
                     style="cursor:pointer;"
                     onclick="event.stopPropagation()">
                <?= htmlspecialchars($assembly) ?>
              </label>
              <small class="me-3 flex-shrink-0" style="opacity:0.8;">
                <?= $asm_total_files ?> file<?= $asm_total_files !== 1 ? 's' : '' ?>
              </small>
              <i class="fas fa-chevron-down toggle-icon text-white"></i>
            </div>

            <!-- Gene sets collapse -->
            <div class="collapse" id="<?= $asm_id ?>">
              <?php $gs_idx = 0; foreach ($gene_sets as $gene_set => $asm_data): $gs_idx++; ?>
              <?php $gs_id = $asm_id . '_gs_' . $gs_idx; ?>
              <div class="ps-3 pt-1">
                <?php if (count($gene_sets) > 1): ?>
                <!-- Gene set sub-header (only shown when multiple gene sets exist) -->
                <div class="d-flex align-items-center px-2 py-1 rounded mb-1 gs-header"
                     style="cursor:pointer; background:#e11d48; color:white;"
                     data-bs-toggle="collapse"
                     data-bs-target="#<?= $gs_id ?>">
                  <input type="checkbox"
                         class="form-check-input me-2 flex-shrink-0 gs-checkbox"
                         id="cb-<?= $gs_id ?>"
                         data-org-id="<?= $org_id ?>"
                         data-asm-id="<?= $asm_id ?>"
                         data-gs-id="<?= $gs_id ?>"
                         onclick="event.stopPropagation()">
                  <label class="form-check-label fw-semibold me-auto mb-0 user-select-none small text-white"
                         for="cb-<?= $gs_id ?>"
                         style="cursor:pointer;"
                         onclick="event.stopPropagation()">
                    <span class="badge bg-gene-set me-1" style="font-size:0.7rem;">Gene Set</span><?= htmlspecialchars($gene_set) ?>
                  </label>
                  <small class="me-3 flex-shrink-0" style="opacity:0.8;">
                    <?= $asm_data['file_count'] ?> file<?= $asm_data['file_count'] !== 1 ? 's' : '' ?>,
                    <?= htmlspecialchars($asm_data['total_label']) ?>
                  </small>
                  <i class="fas fa-chevron-down toggle-icon text-white"></i>
                </div>
                <div class="collapse" id="<?= $gs_id ?>">
                <?php else: ?>
                <div>
                <?php endif; ?>
                  <div class="ps-2 pt-1">
                    <?php foreach ($asm_data['files'] as $fi => $file):
                      $file_id = $gs_id . '_f' . $fi;
                      $dl_url  = '/' . $site . '/api/download_file.php'
                               . '?organism=' . urlencode($organism)
                               . '&assembly=' . urlencode($assembly)
                               . '&gene_set=' . urlencode($gene_set)
                               . '&filename=' . urlencode($file['name']);
                    ?>
                    <div class="d-flex align-items-center py-1 px-2 file-row border-bottom">
                      <input type="checkbox"
                             class="form-check-input me-2 flex-shrink-0 file-checkbox"
                             id="cb-<?= $file_id ?>"
                             data-org-id="<?= $org_id ?>"
                             data-asm-id="<?= $asm_id ?>"
                             data-gs-id="<?= $gs_id ?>"
                             data-download-url="<?= htmlspecialchars($dl_url) ?>"
                             data-organism="<?= htmlspecialchars($organism) ?>"
                             data-assembly="<?= htmlspecialchars($assembly) ?>"
                             data-gene-set="<?= htmlspecialchars($gene_set) ?>"
                             data-filename="<?= htmlspecialchars($file['name']) ?>"
                             data-size="<?= $file['size'] ?>">
                      <a href="<?= htmlspecialchars($dl_url) ?>"
                         class="me-auto text-decoration-none file-link"
                         download="<?= htmlspecialchars($file['name']) ?>">
                        <i class="fas fa-file me-1 text-muted small"></i><?= htmlspecialchars($file['name']) ?>
                      </a>
                      <span class="badge bg-secondary ms-3 flex-shrink-0">
                        <?= htmlspecialchars($file['size_label']) ?>
                      </span>
                    </div>
                    <?php endforeach; ?>
                  </div>
                </div><!-- end gene set block -->
              </div>
              <?php endforeach; ?>
            </div><!-- end gene sets collapse -->

          </div><!-- end assembly-block -->
          <?php endforeach; ?>
        </div>
      </div><!-- end organism collapse -->

    </div><!-- end organism-block -->
    <?php endforeach; ?>
  </div><!-- end #download-tree -->

  <?php endif; ?>
</div>

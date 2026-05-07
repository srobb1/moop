<?php
/**
 * DOWNLOADS - Display Page
 * Rendered by tools/downloads.php via display-template.php.
 * Variables available: $download_tree, $site, $siteTitle, $page_title,
 *                      $context_organism, $context_assembly, $context_group, $display_name
 */
?>
<div class="container mt-5">
  <div class="row mb-3">
    <div class="col-12">
      <h2 class="mb-1"><i class="fas fa-download me-2"></i><?= htmlspecialchars($page_title) ?></h2>
      <p class="text-muted mb-0">Browse and download genome files for organisms you have access to.</p>
    </div>
  </div>

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
          <?php $asm_idx = 0; foreach ($assemblies as $assembly => $asm_data): $asm_idx++; ?>
          <?php $asm_id = $org_id . '_asm_' . $asm_idx; ?>

          <div class="assembly-block mb-2">
            <!-- Assembly header -->
            <div class="d-flex align-items-center px-2 py-2 rounded border assembly-header"
                 style="cursor:pointer; background:#dce8f8;"
                 data-bs-toggle="collapse"
                 data-bs-target="#<?= $asm_id ?>">
              <input type="checkbox"
                     class="form-check-input me-2 flex-shrink-0 asm-checkbox"
                     id="cb-<?= $asm_id ?>"
                     data-org-id="<?= $org_id ?>"
                     data-asm-id="<?= $asm_id ?>"
                     onclick="event.stopPropagation()">
              <label class="form-check-label fw-semibold me-auto mb-0 user-select-none"
                     for="cb-<?= $asm_id ?>"
                     style="cursor:pointer;"
                     onclick="event.stopPropagation()">
                <?= htmlspecialchars($assembly) ?>
              </label>
              <small class="text-muted me-3 flex-shrink-0">
                <?= $asm_data['file_count'] ?> file<?= $asm_data['file_count'] !== 1 ? 's' : '' ?>,
                <?= htmlspecialchars($asm_data['total_label']) ?>
              </small>
              <i class="fas fa-chevron-down toggle-icon text-muted"></i>
            </div>

            <!-- Files collapse -->
            <div class="collapse" id="<?= $asm_id ?>">
              <div class="ps-4 pt-1">
                <?php foreach ($asm_data['files'] as $fi => $file):
                  $file_id = $asm_id . '_f' . $fi;
                  $dl_url  = '/' . $site . '/api/download_file.php'
                           . '?organism=' . urlencode($organism)
                           . '&assembly=' . urlencode($assembly)
                           . '&filename=' . urlencode($file['name']);
                ?>
                <div class="d-flex align-items-center py-1 px-2 file-row border-bottom">
                  <input type="checkbox"
                         class="form-check-input me-2 flex-shrink-0 file-checkbox"
                         id="cb-<?= $file_id ?>"
                         data-org-id="<?= $org_id ?>"
                         data-asm-id="<?= $asm_id ?>"
                         data-download-url="<?= htmlspecialchars($dl_url) ?>"
                         data-organism="<?= htmlspecialchars($organism) ?>"
                         data-assembly="<?= htmlspecialchars($assembly) ?>"
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
            </div><!-- end files collapse -->

          </div><!-- end assembly-block -->
          <?php endforeach; ?>
        </div>
      </div><!-- end organism collapse -->

    </div><!-- end organism-block -->
    <?php endforeach; ?>
  </div><!-- end #download-tree -->

  <?php endif; ?>
</div>

<?php
/**
 * Genome Browser — Display Page
 * Variables: $scope_tree, $organism_info, $site, $dl_organism, $dl_assembly
 */
?>
<div class="container-fluid py-3 px-3">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center" style="background-color:#0891b2;">
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Genome Browser</span>
    </div>
    <div class="card-body py-2">
      <p class="text-muted small mb-0">Select an organism and assembly to explore in JBrowse2. Navigate genes, tracks, and sequence annotations interactively.</p>
    </div>
  </div>

  <?php if (empty($scope_tree)): ?>
    <div class="alert alert-warning">No accessible assemblies found. Contact an administrator for access.</div>
  <?php elseif (empty($dl_organism) || empty($dl_assembly)): ?>

  <!-- Selector (shown when no deep-link params) -->
  <div class="card shadow-sm mb-4" id="jb-selector-card">
    <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
      <span class="step-badge me-2">1</span>
      <span class="fw-semibold" style="font-size:0.9rem;">Select an organism and assembly</span>
    </div>
    <div class="row g-0" style="min-height:220px;">

      <!-- Left: organism list -->
      <div class="col-lg-7 border-end d-flex flex-column">
        <div class="px-2 pt-2 pb-1 border-bottom">
          <input type="text" class="form-control form-control-sm moop-input" id="jb-org-filter"
                 placeholder="Filter organisms…" autocomplete="off">
        </div>
        <div style="overflow-y:auto; max-height:220px;" id="jb-org-list">
          <?php foreach ($scope_tree as $org => $assemblies):
            $info    = $organism_info[$org] ?? [];
            $genus   = $info['genus']       ?? '';
            $species = $info['species']     ?? '';
            $common  = $info['common_name'] ?? '';
            $display = $genus && $species ? "$genus $species" : str_replace('_', ' ', $org);
          ?>
          <div class="jb-org-row px-3 py-2 border-bottom d-flex align-items-center gap-2"
               style="cursor:pointer;"
               data-org="<?= htmlspecialchars($org) ?>"
               data-assemblies="<?= htmlspecialchars(json_encode($assemblies)) ?>"
               data-search="<?= htmlspecialchars(strtolower("$org $display $common")) ?>">
            <div class="flex-grow-1">
              <span class="small fw-semibold"><em><?= htmlspecialchars($display) ?></em></span>
              <?php if ($common): ?>
                <span class="text-muted small ms-1">(<?= htmlspecialchars($common) ?>)</span>
              <?php endif; ?>
            </div>
            <i class="fas fa-chevron-right text-muted small jb-org-chevron"></i>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Right: assembly picker -->
      <div class="col-lg-5 d-flex flex-column">
        <div class="px-3 pt-2 pb-1 border-bottom">
          <span class="small text-muted" id="jb-asm-label">← Select an organism</span>
        </div>
        <div style="overflow-y:auto; max-height:220px; flex:1;" id="jb-asm-list">
        </div>
      </div>

    </div>
    <div class="card-footer d-flex align-items-center gap-3">
      <button class="btn btn-primary" id="jb-launch-btn" disabled>
        <i class="fas fa-dna me-1"></i> Open Genome Browser
      </button>
      <span class="text-muted small" id="jb-selection-label">No assembly selected</span>
    </div>
  </div>

  <?php endif; ?>

  <!-- JBrowse iframe area — shown after launch or immediately for deep-links -->
  <div id="jb-viewer" style="display:none;">
    <div class="d-flex align-items-center gap-2 mb-2">
      <?php if (empty($dl_organism)): ?>
      <button class="btn btn-sm btn-outline-secondary" id="jb-back-btn">
        <i class="fas fa-arrow-left me-1"></i> Back
      </button>
      <?php endif; ?>
      <span class="text-muted small" id="jb-viewer-label"></span>
      <div class="ms-auto d-flex gap-1">
        <button class="btn btn-sm btn-outline-secondary" id="jb-fullscreen-btn" title="Toggle fullscreen">
          <i class="fas fa-expand"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary" id="jb-newwindow-btn" title="Open in new window">
          <i class="fas fa-external-link-alt"></i>
        </button>
      </div>
    </div>
    <div id="jb-iframe-wrap" style="border:1px solid #dee2e6; border-radius:4px; overflow:hidden;">
      <iframe id="jb-iframe" style="width:100%; height:100%; border:none;" title="JBrowse2 Genome Browser"></iframe>
    </div>
  </div>

</div>

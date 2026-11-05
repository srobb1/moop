<?php
/**
 * Opens the closed "For researchers" section that technical panels (BUSCO, assembly statistics,
 * the gene set comparison table) sit in on a teaching site -- Site Configuration -> Technical
 * Panels, ConfigManager::technicalPanelsTucked(). The caller includes this only when that is on,
 * and closes it with </details>; on a site that shows them (simrbase) nothing here is emitted.
 */
?>
<details class="moop-researchers card adm-card mb-4">
  <summary class="card-header adm-head h6 mb-0" style="cursor: pointer;"><i class="fa fa-microscope me-2"></i><?= t('For researchers: technical details') ?></summary>
  <div class="card-body pb-0">

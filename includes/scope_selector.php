<?php
/**
 * The organism scope selector card -- ONE copy, used by Annotation Search and the Data Exporter.
 * Behaviour: js/modules/scope-selector.js. Data: lib/scope_selector.php. Styles: css/display.css
 * (#scope-org-list). History and plan: notes/SHARED_SCOPE_SELECTOR_PLAN.md.
 *
 * Expects:
 *   $scope       moop_scope_selector_data()
 *   $scope_opts  [
 *     'step'       => '2',                          the step badge
 *     'title'      => t('Choose organisms to search'),  header text, already translated (HTML)
 *     'help_intro' => 'Choose which organisms …',   plain English; help_modal() translates it
 *     'help_need'  => 'A search always runs …',     the "Pick at least one" card, plain English
 *   ]
 *
 * One selector per page: the ids are fixed (scope-*), and the page script reads the ticked
 * boxes as `.scope-gs-cb:checked`.
 */

$scope_tree      = $scope['tree'] ?? [];
$scope_info      = $scope['organism_info'] ?? [];
$scope_asm_names = $scope['assembly_names'] ?? [];
$scope_groups    = $scope['organism_groups'] ?? [];

echo help_modal(
    'scope-info-modal',
    'How to select organisms',
    [[
        'heading' => '',
        'cards'   => [
            ['label' => 'Find them fast',
             'text'  => t_markup('Type in the box to filter by group, genus / species or common name, assembly, or %s.', gloss('gene set')),
             'html'  => true],
            ['label' => 'Pick an organism',
             'text'  => 'Click a row to select it. One click takes all of that organism\'s gene sets — the usual case.'],
            ['label' => 'Or narrow with Details',
             'text'  => t_markup('Turn on the Details switch to expand each organism into its separate %s and gene sets, and pick individual ones.', gloss('assembly', 'assemblies')),
             'html'  => true],
            ['label' => 'Pick at least one',
             'text'  => $scope_opts['help_need']],
            ['label'  => 'Why the list looks shorter than you expect',
             'accent' => true,
             'text'   => 'A few organisms carry more than one gene set. In the simple view those collapse to a '
                       . 'single row marked "· 3 gene sets" rather than repeating the organism name three times. '
                       . 'Details expands them.'],
        ],
    ]],
    ['intro' => $scope_opts['help_intro']]
);
?>
<div class="card mb-3 shadow-sm">
  <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
    <span class="step-badge me-2"><?= htmlspecialchars((string)$scope_opts['step']) ?></span>
    <span class="fw-semibold" style="font-size:0.9rem;"><?= $scope_opts['title'] ?></span>
    <?= help_modal_trigger('scope-info-modal', '', 'How to select organisms') ?>
    <?php /* One toggle, not an All/None pair: its label states what the click will do, so it
             doubles as a readout of whether everything is selected. Deselect is immediate;
             select-all routes through the "this can take a while" warning below. */ ?>
    <button type="button" class="btn btn-sm btn-light py-0 ms-auto scope-toggle-all">
      <span class="scope-toggle-all-label"><?= t('Select all') ?></span>
    </button>
  </div>

  <div class="row g-0" style="min-height:200px;">

    <!-- Left: organism list -->
    <div class="col-lg-8 border-end d-flex flex-column">
      <div class="px-2 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
        <input type="text" class="form-control form-control-sm moop-input" id="scope-filter"
               placeholder="<?= t('Filter by group, organism, assembly, gene set…') ?>" autocomplete="off">
        <div class="form-check form-switch mb-0 flex-shrink-0">
          <input class="form-check-input" type="checkbox" role="switch" id="scope-show-detail">
          <label class="form-check-label small text-muted text-nowrap" for="scope-show-detail"><?= t('Details') ?></label>
        </div>
      </div>
      <?php /* 180px is deliberate (feedback_scope_list_height_deliberate): a compact page, and
               the Selected panel beside it is capped at the same height so the card is one
               rectangle. */ ?>
      <div style="overflow-y:auto; max-height:180px;" id="scope-org-list" class="scope-detail-hidden">
        <?php if (empty($scope_tree)): ?>
          <p class="text-muted small p-3"><?= t('No accessible organisms found.') ?></p>
        <?php else:
          // An organism with several gene sets renders as ONE row in simple view (with a
          // "N gene sets" note) instead of N rows the eye cannot tell apart; the extra rows
          // (.scope-row-secondary) appear with the Details switch.
          $orgGsCount = [];
          foreach ($scope_tree as $o => $asmList) {
              $orgGsCount[$o] = array_sum(array_map('count', $asmList));
          }
          $seenOrg = [];
          $rowIdx  = 0;
          foreach ($scope_tree as $organism => $assemblies):
            $info   = $scope_info[$organism] ?? [];
            $label  = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);
            $cn     = $info['common_name'] ?? '';
            $groups = $scope_groups[$organism] ?? [];
            foreach ($assemblies as $asm => $gene_sets):
              $an           = $scope_asm_names[$organism][$asm] ?? '';
              $asmDisplay   = $an ? $an : $asm;
              $asmAccession = $an ? $asm : '';
              foreach ($gene_sets as $gs):
                $rowIdx++;
                $searchSimple = strtolower("$label $cn " . implode(' ', $groups));
                $searchDetail = strtolower("$asm $an $gs");
                $isRep   = !isset($seenOrg[$organism]);   // first row = the one simple view shows
                $seenOrg[$organism] = true;
                $gsCount = $orgGsCount[$organism] ?? 1;
                $tooltip = $label . ($cn ? ' · ' . $cn : '') . ' · ' . $asmDisplay . ($asmAccession ? ' (' . $asmAccession . ')' : '') . ' › ' . $gs;
        ?>
        <div class="org-select-row scope-row<?= $isRep ? '' : ' scope-row-secondary' ?>"
             data-org="<?= htmlspecialchars($organism) ?>"
             data-search="<?= htmlspecialchars($searchSimple . ' ' . $searchDetail) ?>"
             data-search-simple="<?= htmlspecialchars($searchSimple) ?>"
             data-search-detail="<?= htmlspecialchars($searchDetail) ?>"
             title="<?= htmlspecialchars($tooltip) ?>">
          <?php /* label / cn / asm-display repeat what the row shows, so the Selected panel is
                   built from the checkboxes alone, never by scraping sibling DOM. */ ?>
          <input type="checkbox" class="scope-gs-cb visually-hidden"
                 id="scope_gs_<?= $rowIdx ?>"
                 data-org="<?= htmlspecialchars($organism) ?>"
                 data-asm="<?= htmlspecialchars($asm) ?>"
                 data-gs="<?= htmlspecialchars($gs) ?>"
                 data-label="<?= htmlspecialchars($label) ?>"
                 data-cn="<?= htmlspecialchars($cn) ?>"
                 data-asm-display="<?= htmlspecialchars($asmDisplay) ?>">
          <span class="org-groups flex-shrink-0">
            <?php foreach ($groups as $g): ?>
            <span class="org-group-chip" style="background:<?= moop_group_color($g) ?>"><?= htmlspecialchars($g) ?></span>
            <?php endforeach; ?>
          </span>
          <span class="flex-grow-1 text-truncate" style="min-width:0; white-space:nowrap;">
            <em><?= htmlspecialchars($label) ?></em><?php if ($cn): ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($cn) ?></span><?php endif;
            ?><?php if ($isRep && $gsCount > 1): ?><span class="scope-gs-count text-muted" style="font-size:0.78em;"> · <?= t('%d gene sets', (int)$gsCount) ?></span><?php endif;
            ?><span class="scope-row-detail text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asmDisplay) ?><?php if ($asmAccession): ?> <span style="font-size:0.9em;">(<?= htmlspecialchars($asmAccession) ?>)</span><?php endif; ?> › <?= htmlspecialchars($gs) ?></span>
          </span>
          <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
        </div>
        <?php endforeach; endforeach; endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: what is selected -->
    <div class="col-lg-4 d-flex flex-column">
      <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
           style="background:#f8f9fa;">
        <span class="small fw-semibold text-muted"><?= t('Selected') ?></span>
        <span class="badge bg-secondary" id="scope-selected-count">0</span>
      </div>
      <?php /* Says what to DO. Never "none = all organisms": nothing selected means the page
               cannot proceed, and an unscoped fan-out is the query we least want by accident. */ ?>
      <div style="overflow-y:auto; max-height:180px; font-size:0.82rem;" id="scope-selected-panel">
        <div class="text-muted small p-2 fst-italic"><?= t('None yet — pick at least one organism') ?></div>
      </div>
    </div>

  </div><!-- /row -->

  <?php /* Under BOTH columns: it summarises the card. Organisms / assemblies / gene sets in full,
           because one click on a collapsed row can take four gene sets with it. */ ?>
  <div class="px-3 py-1 border-top" style="background:#f8f9fa; font-size:0.8rem;">
    <span class="text-muted" id="scope-counts"><?= t('Select at least one organism above') ?></span>
  </div>
</div>

<!-- Select-all warning -->
<div class="modal fade" id="scope-select-all-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-warning">
      <div class="modal-header bg-warning bg-opacity-10 py-2">
        <h5 class="modal-title fw-bold"><i class="fa fa-exclamation-triangle text-warning me-2"></i><?= t('Select all organisms?') ?></h5>
      </div>
      <div class="modal-body">
        <?= t_markup('This will select all %s across all organisms.', '<strong id="scope-select-all-count"></strong>') . "\n" ?>
        <?= t('Searches across all organisms can take a while — consider selecting only the ones you need.') . "\n" ?>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= t('Cancel') ?></button>
        <button type="button" class="btn btn-warning" id="scope-select-all-confirm"><?= t('Select all') ?></button>
      </div>
    </div>
  </div>
</div>

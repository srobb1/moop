<?php
/**
 * The organism scope selector -- ONE copy for every page where a visitor picks organisms /
 * gene sets. Behaviour: js/modules/scope-selector.js. Data: lib/scope_selector.php. Styles:
 * css/display.css (#scope-org-list). History and plan: notes/SHARED_SCOPE_SELECTOR_PLAN.md.
 *
 * Two modes:
 *   multi  (default) -- Annotation Search, Data Exporter. Renders the whole step CARD: header
 *                       with Select all, the list, a Selected panel, the counts line, the help
 *                       window and the select-all warning.
 *   single           -- Retrieve Sequences, BLAST, Primer BLAST. Renders only the filter, the
 *                       list and a "Selected:" line, for the page to place inside its own step
 *                       card and FORM: each row is a radio named selected_source whose value is
 *                       "organism|assembly|gene_set", exactly what those pages already submit.
 *
 * Expects:
 *   $scope       moop_scope_selector_data()
 *   $scope_opts  multi:  step, title (translated HTML), help_intro, help_need (plain English)
 *                single: mode => 'single'
 *                        selected     => 'org|asm|gs' to pre-select ('' for none)
 *                        filter       => text to pre-fill the filter with ('' for none)
 *                        has_genome   => ['org|asm|gs' => bool]  optional, emits data-has-genome
 *                        availability => ['org|asm|gs' => ['ok'=>bool,'reason'=>..,'usable_mode'=>..]]
 *                                        optional: data-available etc., and a "!" on unusable rows
 *                        help_id      => id of a help_modal() the page renders, optional: an (i)
 *                                        beside the filter
 *                        only         => [organism, ...] optional: show only these until "Show all"
 *                                        (an organisms list from the group / home / multi page)
 *
 * One selector per page: the ids are fixed (scope-*).
 */

$scope_tree      = $scope['tree'] ?? [];
$scope_info      = $scope['organism_info'] ?? [];
$scope_asm_names = $scope['assembly_names'] ?? [];
$scope_groups    = $scope['organism_groups'] ?? [];
$scope_blast_only = $scope['blast_only'] ?? [];
$scope_single    = ($scope_opts['mode'] ?? 'multi') === 'single';
$scope_selected  = $scope_single ? (string)($scope_opts['selected'] ?? '') : '';
$scope_has_genome   = $scope_opts['has_genome'] ?? null;
$scope_availability = $scope_opts['availability'] ?? null;
$scope_only      = $scope_single ? array_flip($scope_opts['only'] ?? []) : [];

// Single mode: open in Details when the pre-selected gene set is not its organism's only one,
// so the chosen gene set is the row the visitor sees highlighted.
$scope_details_on = false;
if ($scope_selected !== '') {
    [$so, $sa] = array_pad(explode('|', $scope_selected, 3), 2, '');
    $scope_details_on = isset($scope_tree[$so]) && array_sum(array_map('count', $scope_tree[$so])) > 1;
}

// One row per gene set. In simple view an organism with several gene sets shows as ONE row
// (the first, with a "N gene sets" note); the others (.scope-row-secondary) appear with Details.
$scope_render_rows = function () use ($scope_tree, $scope_info, $scope_asm_names, $scope_groups, $scope_blast_only,
                                      $scope_single, $scope_selected, $scope_has_genome, $scope_availability, $scope_only) {
    if (empty($scope_tree)) {
        echo '<p class="text-muted small p-3">' . t('No accessible organisms found.') . '</p>';
        return;
    }
    $orgGsCount = [];
    foreach ($scope_tree as $o => $asmList) $orgGsCount[$o] = array_sum(array_map('count', $asmList));
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
                $gsDisplay    = !empty($scope_blast_only[$organism][$asm]) && $gs === 'blast_only' ? t_plain('genome only') : $gs;
                $key          = "$organism|$asm|$gs";
                $searchSimple = strtolower("$label $cn " . implode(' ', $groups));
                $searchDetail = strtolower("$asm $an $gsDisplay");
                $isRep   = !isset($seenOrg[$organism]);   // first row = the one simple view shows
                $seenOrg[$organism] = true;
                $gsCount = $orgGsCount[$organism] ?? 1;
                $tooltip = $label . ($cn ? ' · ' . $cn : '') . ' · ' . $asmDisplay . ($asmAccession ? ' (' . $asmAccession . ')' : '') . ' › ' . $gsDisplay;
                $isSel   = $scope_single && $key === $scope_selected;
                $avail   = $scope_availability[$key] ?? null;
?>
        <div class="org-select-row scope-row<?= $isRep ? '' : ' scope-row-secondary' ?><?= $isSel ? ' selected' : '' ?><?= $scope_only && !isset($scope_only[$organism]) ? ' scope-row-outside' : '' ?>"
             data-org="<?= htmlspecialchars($organism) ?>"
             data-search="<?= htmlspecialchars($searchSimple . ' ' . $searchDetail) ?>"
             data-search-simple="<?= htmlspecialchars($searchSimple) ?>"
             data-search-detail="<?= htmlspecialchars($searchDetail) ?>"
             title="<?= htmlspecialchars($tooltip) ?>">
          <?php /* label / cn / asm-display repeat what the row shows, so the Selected panel and
                   the readout are built from the inputs alone, never by scraping sibling DOM. */ ?>
          <input type="<?= $scope_single ? 'radio' : 'checkbox' ?>" class="scope-gs-cb visually-hidden"
                 id="scope_gs_<?= $rowIdx ?>"
                 <?php if ($scope_single): ?>name="selected_source" value="<?= htmlspecialchars($key) ?>"
                 data-organism="<?= htmlspecialchars($organism) ?>"
                 data-assembly="<?= htmlspecialchars($asm) ?>"
                 data-gene-set="<?= htmlspecialchars($gs) ?>"
                 <?php if ($scope_has_genome !== null): ?>data-has-genome="<?= !empty($scope_has_genome[$key]) ? '1' : '0' ?>"<?php endif; ?>
                 <?php if ($avail !== null): ?>data-available="<?= !empty($avail['ok']) ? '1' : '0' ?>"
                 data-unavailable-reason="<?= htmlspecialchars($avail['reason'] ?? '') ?>"
                 data-usable-mode="<?= htmlspecialchars($avail['usable_mode'] ?? '') ?>"<?php endif; ?>
                 <?= $isSel ? 'checked' : '' ?>
                 <?php endif; ?>
                 data-org="<?= htmlspecialchars($organism) ?>"
                 data-asm="<?= htmlspecialchars($asm) ?>"
                 data-gs="<?= htmlspecialchars($gs) ?>"
                 data-gs-display="<?= htmlspecialchars($gsDisplay) ?>"
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
            ?><span class="scope-row-detail text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asmDisplay) ?><?php if ($asmAccession): ?> <span style="font-size:0.9em;">(<?= htmlspecialchars($asmAccession) ?>)</span><?php endif; ?> › <?= htmlspecialchars($gsDisplay) ?></span>
          </span>
          <?php if ($avail !== null && empty($avail['ok'])): /* the tool cannot use this one -- why, on hover */ ?>
          <span class="badge badge-sm bg-warning text-dark source-unavailable flex-shrink-0"
                title="<?= htmlspecialchars($avail['reason'] ?? t_plain('Not available for this tool')) ?>"
                data-bs-toggle="tooltip">!</span>
          <?php endif; ?>
          <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
        </div>
<?php
            endforeach;
        endforeach;
    endforeach;
};

$scope_filter_row = function (string $value, bool $details_on, string $help_id = '') {
?>
      <div class="px-2 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
        <input type="text" class="form-control form-control-sm moop-input" id="scope-filter"
               placeholder="<?= t('Filter by group, organism, assembly, gene set…') ?>" autocomplete="off"
               value="<?= htmlspecialchars($value) ?>">
        <div class="form-check form-switch mb-0 flex-shrink-0">
          <input class="form-check-input" type="checkbox" role="switch" id="scope-show-detail"<?= $details_on ? ' checked' : '' ?>>
          <label class="form-check-label small text-muted text-nowrap" for="scope-show-detail"><?= t('Details') ?></label>
        </div>
        <?php /* Always there: clears the filter and any "chosen on the previous page" limit. */ ?>
        <button type="button" class="btn btn-sm btn-outline-secondary py-0 flex-shrink-0 text-nowrap" id="scope-show-all"><?= t('Show all') ?></button>
        <?php if ($help_id !== ''): ?><?= help_modal_trigger($help_id, '', 'About this list') ?><?php endif; ?>
      </div>
<?php
};

// ── Single mode: filter + list + readout, inside the page's own card and form ─────────────
if ($scope_single): ?>
<div class="border rounded scope-single">
  <?php $scope_filter_row((string)($scope_opts['filter'] ?? ''), $scope_details_on, (string)($scope_opts['help_id'] ?? '')); ?>
  <?php if ($scope_only): /* the list is shorter than the box says -- explain why (Show all lifts it) */ ?>
  <div class="px-3 py-1 border-bottom small" id="scope-only-note" style="background:rgba(var(--moop-main-rgb), 0.07);">
    <?= count($scope_only) === 1
        ? t('Showing the organism you chose on the previous page.')
        : t('Showing the %d organisms you chose on the previous page.', count($scope_only)) ?>
  </div>
  <?php endif; ?>
  <?php /* 180px: the same deliberate height as the multi list (feedback_scope_list_height_deliberate). */ ?>
  <div style="overflow-y:auto; max-height:180px;" id="scope-org-list" class="<?= $scope_details_on ? '' : 'scope-detail-hidden' ?><?= $scope_only ? ' scope-limited' : '' ?>">
    <?php $scope_render_rows(); ?>
  </div>
  <div class="px-3 py-1 border-top d-flex align-items-center gap-2" style="background:#f8f9fa; font-size:0.85rem;">
    <span class="text-muted flex-grow-1" id="scope-counts"><?= t('None selected') ?></span>
    <?php /* Deselect from anywhere: the chosen row may be filtered out of sight. Shown only
             while something is selected (js/modules/scope-selector.js). */ ?>
    <button type="button" class="btn btn-sm btn-link text-danger p-0 flex-shrink-0 d-none" id="scope-deselect">
      <i class="fas fa-times me-1"></i><?= t('Deselect') ?>
    </button>
  </div>
</div>
<?php return; endif;

// ── Multi mode: the whole step card ───────────────────────────────────────────────────────
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
      <?php $scope_filter_row('', false); ?>
      <?php /* 180px is deliberate (feedback_scope_list_height_deliberate): a compact page, and
               the Selected panel beside it is capped at the same height so the card is one
               rectangle. */ ?>
      <div style="overflow-y:auto; max-height:180px;" id="scope-org-list" class="scope-detail-hidden">
        <?php $scope_render_rows(); ?>
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

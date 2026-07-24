<?php
/**
 * Annotation Search — Display Page
 * Variables: $scope_tree, $organism_info, $all_organisms, $site, $siteTitle
 */
?>
<style>
/* Simple view: ONE row per organism. Hide the per-gene-set detail text AND the secondary
   rows, and show the "N gene sets" note in their place — otherwise an organism with four
   gene sets renders as four rows that are byte-identical on screen (Romankenkius sp did
   exactly that). Detail view reveals every gene-set row and drops the now-redundant count.
   Mirrors #mm-scope-list in tools/pages/moopmart.php; both collapse into one shared
   component next — see notes/SHARED_SCOPE_SELECTOR_PLAN.md. */
#scope-org-list.scope-detail-hidden .scope-row-detail     { display: none; }
#scope-org-list.scope-detail-hidden .scope-row-secondary  { display: none; }
#scope-org-list:not(.scope-detail-hidden) .scope-gs-count { display: none; }
/* Filter matched text that simple view hides: reveal that one row's detail so the user can
   see what they matched, and drop its "N gene sets" note — the row now stands for a single
   gene set, not the collapsed organism. */
#scope-org-list.scope-detail-hidden .scope-gs-full-row.scope-detail-forced .scope-row-detail { display: inline; }
#scope-org-list.scope-detail-hidden .scope-gs-full-row.scope-detail-forced .scope-gs-count   { display: none; }
/* Highlighted matched text within the detail span */
mark.scope-hl { background: rgba(254, 240, 138, 0.9); border-radius: 2px; padding: 0 1px; color: inherit; }
</style>
<div class="container mt-4">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2" style="background-color:#0891b2;">
      <?= page_title('Search Features by Annotation') ?>
      <?= help_modal_trigger('search-page-help', '', 'What this page does') ?>
    </div>
    <div class="card-body py-2">
      <p class="text-muted small mb-0">Find specific <?= gloss('feature', 'features') ?> — genes, mRNAs, or proteins — by ID or keyword, matched against their <?= gloss('annotation', 'annotations') ?> across one or more organisms. Use <a href="moopmart.php" class="text-decoration-none">MOOPmart</a> to bulk-download many features at once.</p>
    </div>
  </div>

  <?php
  // Opened by the (i) on the page header. One card per numbered step, carrying the SAME
  // .step-badge number, so the overview reads as a map of the four boxes below it — the
  // pattern MOOPmart's 'mm-help' established. Per-step detail lives at each step's own (i);
  // this stays a walkthrough so the two never restate each other.
  echo help_modal(
      'search-page-help',
      'What this page does',
      [[
          'heading' => '',
          'cards'   => [
              ['num' => '1', 'label' => 'Type what you are looking for',
               'text' => 'A gene or transcript ID, or a keyword from an annotation. Three characters minimum.'],
              ['num' => '2', 'label' => 'Choose organisms',
               'text' => 'Pick the organisms to look in. Searching is per organism, so choosing only the ones '
                       . 'you need is what keeps it fast.'],
              ['num' => '3', 'label' => 'Choose annotation types',
               'text' => 'Decide which kinds of annotation to look inside — GO terms, protein domains, homologs. '
                       . 'The list is built from what your chosen organisms actually carry.'],
              ['num' => '4', 'label' => 'Search',
               'text' => 'Results arrive per organism as each one finishes, so the first ones appear before the '
                       . 'whole search is done.'],
          ],
      ]],
      ['intro' => 'Look features up by ID or annotation keyword across the organisms you choose. '
                . 'To bulk-export many features rather than look them up, MOOPmart is the tool for that.']
  );
  ?>

  <form id="searchForm">

    <!-- ① Keyword -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
        <span class="step-badge me-2">1</span>
        <span class="fw-semibold" style="font-size:0.9rem;">Enter a gene ID or annotation keyword</span>
        <?= help_modal_trigger('search-help', '', 'How to search') ?>
      </div>
      <div class="card-body py-3">
        <input type="text" class="form-control moop-input" id="searchKeywords"
               placeholder='e.g. BRCA1, "Histone Deacetylase 1", GO:0006351 (minimum 3 characters)…'>
      </div>
    </div>

    <!-- ② Organisms -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
        <span class="step-badge me-2">2</span>
        <span class="fw-semibold" style="font-size:0.9rem;">Choose organisms to search</span>
        <?= help_modal_trigger('scope-info-modal', '', 'How to select organisms') ?>
        <?php /* One toggle, not an All/None pair: its label states what the click will do,
                 so it doubles as a readout of whether everything is selected. Same control
                 as MOOPmart Step 1. Deselect is immediate; select-all still routes through
                 the "this can take a while" warning. */ ?>
        <button type="button" class="btn btn-sm btn-light py-0 ms-auto scope-toggle-all">
          <span class="scope-toggle-all-label">Select all</span>
        </button>
      </div>

      <div class="row g-0" style="min-height:200px;">

        <!-- Left: organism list -->
        <div class="col-lg-8 border-end d-flex flex-column">
          <div class="px-2 pt-2 pb-1 border-bottom d-flex align-items-center gap-2">
            <input type="text" class="form-control form-control-sm moop-input" id="scope-filter"
                   placeholder="Filter by group, organism, assembly, gene set…" autocomplete="off">
            <div class="form-check form-switch mb-0 flex-shrink-0">
              <input class="form-check-input" type="checkbox" role="switch" id="scope-show-detail">
              <label class="form-check-label small text-muted text-nowrap" for="scope-show-detail">Details</label>
            </div>
          </div>
          <div style="overflow-y:auto; max-height:180px;" id="scope-org-list" class="scope-detail-hidden">
            <?php if (empty($scope_tree)): ?>
              <p class="text-muted small p-3">No accessible organisms found.</p>
            <?php else: ?>
            <?php
            $gp = ['#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6','#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'];
            $groupColor = fn($n) => $gp[abs(array_sum(array_map('ord', str_split($n))) * 31) % count($gp)];
            // How many gene-set rows each organism has, so a multi-gene-set organism renders
            // as ONE row in simple view (carrying a "N gene sets" note) instead of N rows the
            // eye cannot tell apart. The extra rows only distinguish themselves once Details
            // is on. Same logic as MOOPmart's $orgGsCount / $seenOrg.
            $orgGsCount = [];
            foreach ($scope_tree as $o => $asmList) {
                $orgGsCount[$o] = array_sum(array_map('count', $asmList));
            }
            $seenOrg = [];
            $rowIdx = 0;
            foreach ($scope_tree as $organism => $assemblies):
              $info   = $organism_info[$organism] ?? [];
              $label  = trim(($info['genus'] ?? '') . ' ' . ($info['species'] ?? '')) ?: str_replace('_', ' ', $organism);
              $cn     = $info['common_name'] ?? '';
              $groups = $organism_groups[$organism] ?? [];
              foreach ($assemblies as $asm => $gene_sets):
                $an = $assembly_names[$organism][$asm] ?? '';
                $asmDisplay = $an ? $an : $asm;
                $asmAccession = $an ? $asm : '';
                foreach ($gene_sets as $gs):
                  $rowIdx++;
                  $gsid   = 'sgs_' . $rowIdx;
                  $searchSimple = strtolower("$label $cn " . implode(' ', $groups));
                  $searchDetail = strtolower("$asm $an $gs");
                  $search = $searchSimple . ' ' . $searchDetail;
                  // First row for this organism is the representative shown in simple view;
                  // the rest are hidden there and revealed by the Details switch.
                  $isRep   = !isset($seenOrg[$organism]);
                  $seenOrg[$organism] = true;
                  $gsCount = $orgGsCount[$organism] ?? 1;
            ?>
            <?php
              $tooltip = $label . ($cn ? ' · ' . $cn : '') . ' · ' . $asmDisplay . ($asmAccession ? ' (' . $asmAccession . ')' : '') . ' › ' . $gs;
            ?>
            <div class="org-select-row scope-gs-full-row<?= $isRep ? '' : ' scope-row-secondary' ?>"
                 data-org="<?= htmlspecialchars($organism) ?>"
                 data-search="<?= htmlspecialchars($search) ?>"
                 data-search-simple="<?= htmlspecialchars($searchSimple) ?>"
                 data-search-detail="<?= htmlspecialchars($searchDetail) ?>"
                 title="<?= htmlspecialchars($tooltip) ?>">
              <input type="checkbox" class="scope-gs-cb visually-hidden"
                     id="<?= $gsid ?>"
                     data-org="<?= htmlspecialchars($organism) ?>"
                     data-asm="<?= htmlspecialchars($asm) ?>"
                     data-gs="<?= htmlspecialchars($gs) ?>"
                     data-label="<?= htmlspecialchars($label) ?>"
                     data-cn="<?= htmlspecialchars($cn) ?>"
                     data-asm-display="<?= htmlspecialchars($asmDisplay) ?>">
              <span class="org-groups flex-shrink-0">
                <?php foreach ($groups as $g): ?>
                <span class="org-group-chip" style="background:<?= $groupColor($g) ?>"><?= htmlspecialchars($g) ?></span>
                <?php endforeach; ?>
              </span>
              <span class="flex-grow-1 text-truncate" style="min-width:0; white-space:nowrap;">
                <em><?= htmlspecialchars($label) ?></em><?php if ($cn): ?><span class="text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($cn) ?></span><?php endif;
                ?><?php if ($isRep && $gsCount > 1): ?><span class="scope-gs-count text-muted" style="font-size:0.78em;"> · <?= (int)$gsCount ?> gene sets</span><?php endif;
                ?><span class="scope-row-detail text-muted" style="font-size:0.8em;"> · <?= htmlspecialchars($asmDisplay) ?><?php if ($asmAccession): ?> <span style="font-size:0.9em;">(<?= htmlspecialchars($asmAccession) ?>)</span><?php endif; ?> › <?= htmlspecialchars($gs) ?></span>
              </span>
              <span class="org-check flex-shrink-0"><i class="fas fa-check text-success"></i></span>
            </div>
            <?php endforeach; endforeach; endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right: selected organisms panel -->
        <div class="col-lg-4 d-flex flex-column">
          <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
               style="background:#f8f9fa;">
            <span class="small fw-semibold text-muted">Selected</span>
            <span class="badge bg-secondary" id="scope-selected-count">0</span>
          </div>
          <?php /* Empty text used to read "None — will search all organisms", which was never
                   true: with nothing selected no annotation types load, so Step 3 stays empty
                   and the search is refused. It also described the wrong policy — an unscoped
                   fan-out across every organism is the query we most want people NOT to run by
                   accident. Say what to do instead. */ ?>
          <?php /* 180px matches the organism list beside it exactly, so the two columns are one
                   rectangle instead of a ragged pair. It was 340px, which left the card's height
                   set by whichever side happened to be fuller. Scrolls when the selection is
                   longer — that scrollbar is the point: it keeps the card compact while the
                   whole selection stays reviewable. The list height itself is deliberate; see
                   the note on .org-select-list in css/display.css. */ ?>
          <div style="overflow-y:auto; max-height:180px; font-size:0.82rem;" id="scope-selected-panel">
            <div class="text-muted small p-2 fst-italic">None yet — pick at least one organism</div>
          </div>
        </div>

      </div><!-- /row -->

      <?php /* Counts under BOTH columns, because it summarises the card, not one side of it.
               Spells out organisms / assemblies / gene sets rather than showing a bare number:
               one click on an organism row in simple view can take four gene sets with it, and
               that is precisely the thing the collapsed list does not show. Same readout as
               MOOPmart's #mm-scope-counts. */ ?>
      <div class="px-3 py-1 border-top" style="background:#f8f9fa; font-size:0.8rem;">
        <span class="text-muted" id="scope-counts">Select at least one organism above</span>
      </div>
    </div>

    <!-- ③ Annotation Types -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2" style="background:#0891b2; color:#fff;">
        <span class="step-badge me-2">3</span>
        <span class="fw-semibold" style="font-size:0.9rem;">Choose which <?= gloss('annotation type', 'annotation types') ?> to search</span>
        <?= help_modal_trigger('ann-types-modal', '', 'About annotation types') ?>
        <button type="button" class="btn btn-sm btn-light py-0 ms-auto sources-toggle-all">
          <span class="sources-toggle-all-label">Select all</span>
        </button>
      </div>

      <div class="row g-0" style="min-height:120px;">

        <!-- Left: annotation types list -->
        <div class="col-lg-8 border-end d-flex flex-column">
          <div class="px-2 pt-2 pb-1 border-bottom" id="sources-filter-wrap" style="display:none;">
            <input type="text" class="form-control form-control-sm moop-input" id="sources-filter"
                   placeholder="Filter annotation types…" autocomplete="off">
          </div>
          <div id="sourcesPanel" style="overflow-y:auto; max-height:280px;">
            <div class="text-center p-3 text-muted">
              <i class="fa fa-spinner fa-spin me-1"></i> Loading…
            </div>
          </div>
        </div>

        <!-- Right: selected annotation types panel -->
        <div class="col-lg-4 d-flex flex-column">
          <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
               style="background:#f8f9fa;">
            <span class="small fw-semibold text-muted">Selected</span>
            <span class="badge bg-secondary" id="ann-types-selected-count">0</span>
          </div>
          <div style="overflow-y:auto; max-height:280px; font-size:0.82rem;" id="ann-types-selected-panel">
            <div class="text-muted small p-2 fst-italic">No types selected</div>
          </div>
        </div>

      </div><!-- /row -->
    </div>

    <!-- ④ Search -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center" style="background:#0891b2; color:#fff;">
        <span class="step-badge me-2">4</span>
        <span class="fw-semibold" style="font-size:0.9rem;">Search</span>
      </div>
      <div class="card-body py-3">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-lg fw-semibold text-white flex-grow-1" id="searchBtn"
                  style="background:#6366f1; border-color:#6366f1;">
            <i class="fa fa-search me-2"></i>Search
          </button>
          <button type="button" class="btn btn-lg btn-outline-danger" id="search-cancel-btn" style="display:none;">
            <i class="fa fa-ban me-1"></i>Cancel
          </button>
        </div>
        <?php /* The "you skipped a step" reminder, in the same amber inline treatment BLAST and
                 Retrieve Sequences use — not a modal interrupt and not a browser alert(). The
                 text names the step that is missing; js/search-display.js fills it in. */ ?>
        <div id="search-select-hint" class="tools-select-hint small mt-2" style="display:none;"></div>
      </div>
    </div>

  </form>

  <?php
  // Step 2 help. Was a hand-written modal of <h6>/<p> prose whose opening paragraph
  // contradicted itself — "you must select at least one row before searching" immediately
  // followed by "if nothing is selected, all organisms will be searched". Only the first
  // half was ever true, and now it is true by design (see the submit guard in
  // js/search-display.js). Rebuilt as help_modal cards so it matches every other help
  // surface on the site, and kept deliberately parallel to MOOPmart's 'mm-scope-help'.
  echo help_modal(
      'scope-info-modal',
      'How to select organisms',
      [[
          'heading' => '',
          'cards'   => [
              ['label' => 'Find them fast',
               'text'  => 'Type in the box to filter by group, genus / species or common name, assembly, or '
                        . gloss('gene set') . '.',
               'html'  => true],
              ['label' => 'Pick an organism',
               'text'  => 'Click a row to select it. One click takes all of that organism\'s gene sets — the usual case.'],
              ['label' => 'Or narrow with Details',
               'text'  => 'Turn on the Details switch to expand each organism into its separate '
                        . gloss('assembly', 'assemblies') . ' and gene sets, and pick individual ones.',
               'html'  => true],
              ['label' => 'Pick at least one',
               'text'  => 'A search always runs against organisms you chose. Nothing selected means nothing to '
                        . 'search — and fewer organisms means a faster answer.'],
              ['label'  => 'Why the list looks shorter than you expect',
               'accent' => true,
               'text'   => 'A few organisms carry more than one gene set. In the simple view those collapse to a '
                         . 'single row marked "· 3 gene sets" rather than repeating the organism name three times. '
                         . 'Details expands them.'],
          ],
      ]],
      ['intro' => 'Choose which organisms — or which specific gene sets — the search looks in.']
  );
  ?>

  <!-- Annotation types info modal -->
  <?php include_once __DIR__ . '/../../includes/ann_types_modal.php'; ?>

  <?php /* The "no annotation types selected" modal that used to live here is gone: it fired a
           modal interrupt telling the reader to go to section ③ — which, when they had not
           picked organisms yet, was an empty box. Replaced by #search-select-hint above, which
           names the step actually blocking them. */ ?>

  <!-- Select-all-organisms warning modal -->
  <div class="modal fade" id="select-all-orgs-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-warning">
        <div class="modal-header bg-warning bg-opacity-10 py-2">
          <h5 class="modal-title fw-bold"><i class="fa fa-triangle-exclamation text-warning me-2"></i>Select all organisms?</h5>
        </div>
        <div class="modal-body">
          This will select all <strong id="select-all-orgs-count"></strong> organisms.
          Searches across all organisms can take a while — consider selecting only the ones you need.
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-warning" id="select-all-orgs-confirm">Select all</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Results -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase" style="letter-spacing:0.1em; font-size:0.8rem;"><i class="fa fa-list me-1"></i> Search Results <?= help_modal_trigger('search-results-help', '', 'Understanding your search results') ?></span>
      </div>
      <div class="card-body">
        <div id="searchInfo" class="alert alert-info mb-3"></div>
        <div id="searchProgress" class="mb-3"></div>
        <div id="resultsContainer"></div>
      </div>
    </div>
  </div>

</div>

<?php /* Shared results help — ONE home for the explanation, included by every page
        that renders a results table. Opened by the trigger on the section header above. */ ?>
<?php include_once __DIR__ . '/../../includes/search_results_modal.php'; ?>

<?php /* Shared search-box help — ONE home, included by every page with a search
        box. 'multi' pages search several organisms at once and get the organism
        selection card plus the per-organism phrasing of the result cap. */ ?>
<?php $search_help_scope = 'single';
      include __DIR__ . '/../../includes/search_help_modal.php'; ?>

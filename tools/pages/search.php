<?php
/**
 * Annotation Search — Display Page
 * Variables: $scope (lib/scope_selector.php), $all_organisms, $site, $siteTitle
 */
?>
<div class="container mt-4">

  <!-- Header -->
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title('Annotation Search') ?>
      <?= page_purpose('Find genes across one or more organisms by what is known about them — a keyword, an annotation term, or an ID.', '', false, false) ?>
      <?= help_modal_trigger('search-page-help', '', 'What this page does') ?>
    </div>
    <?= tool_page_blurb('annotation_search') ?>
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
               'text' => 'A gene or transcript ID, or a keyword from an annotation. Two characters minimum. '
                       . 'A single word is checked against IDs first: if any ID contains it, you get those IDs '
                       . 'and annotations are not searched.'
                       . (moop_tool_on('moopmart') ? ' To search annotations only, without the ID check, use '
                                                    . 'MOOPmart: Data Exporter and its By Annotation list.' : '')],
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
      ['intro' => 'Look features up by ID or annotation keyword across the organisms you choose.'
                . (moop_tool_on('moopmart') ? ' To bulk-export many features rather than look them up, MOOPmart is the tool for that.' : '')]
  );
  ?>

  <form id="searchForm">

    <!-- ① Keyword -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center tool-header">
        <span class="step-badge me-2">1</span>
        <span class="fw-semibold" style="font-size:0.9rem;"><?= t_markup('Enter a %1$s ID or %2$s keyword', gloss('gene'), gloss('annotation')) ?></span>
        <?= help_modal_trigger('search-help', '', 'How to search') ?>
      </div>
      <div class="card-body py-3">
        <?php // ?q= pre-fills the term: the home page's Genes box links here when an ID
              // lookup finds nothing ("Search annotations for 'BRCA1'"). ?>
        <input type="text" class="form-control moop-input" id="searchKeywords"
               value="<?= htmlspecialchars(trim((string)($_GET['q'] ?? ''))) ?>"
               placeholder='<?= /* single-quoted attribute: & < > and ' escaped, " left as is */ str_replace("'", '&#039;', htmlspecialchars(t_plain('e.g. BRCA1, "Histone Deacetylase 1", GO:0006351 (minimum 3 characters)…'), ENT_NOQUOTES)) ?>'>
      </div>
    </div>

    <!-- ② Organisms -->
    <?php
    $scope_opts = [
        'step'       => '2',
        'title'      => t('Choose organisms to search'),
        'help_intro' => 'Choose which organisms — or which specific gene sets — the search looks in.',
        'help_need'  => 'A search always runs against organisms you chose. Nothing selected means nothing to '
                      . 'search — and fewer organisms means a faster answer.',
    ];
    include __DIR__ . '/../../includes/scope_selector.php';
    ?>

    <!-- ③ Annotation Types -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
        <span class="step-badge me-2">3</span>
        <span class="fw-semibold" style="font-size:0.9rem;"><?= t_markup('Choose which %s to search', gloss('annotation type', 'annotation types')) ?></span>
        <?php /* The header (i) explains THIS STEP, matching Steps 1 and 2 — theirs are
                 "How to search" and "How to select organisms". This slot used to hold
                 ann-types-modal, a reference list of what each type covers, so the one
                 header button on the page that did not describe its own box was this one.
                 That modal is still reachable, from the line beside "annotation types"
                 in the panel below, which is where a definition belongs. */ ?>
        <?= help_modal_trigger('ann-types-step-help', '', 'About this step') ?>
        <button type="button" class="btn btn-sm btn-light py-0 ms-auto sources-toggle-all">
          <span class="sources-toggle-all-label"><?= t('Select all') ?></span>
        </button>
      </div>

      <div class="row g-0" style="min-height:120px;">

        <!-- Left: annotation types list -->
        <div class="col-lg-8 border-end d-flex flex-column">
          <?php /* ONE message at a time, never two. This line and the panel's empty state
                   used to print together and pulled against each other: a rule about ticking
                   boxes, sitting directly above "Choose organisms in Step 2" — i.e. above a
                   panel with nothing to tick. The rule is meaningless until a list exists, so
                   js/search-display.js reveals this line with the types and hides it with
                   them, on exactly the condition it already uses for the filter box below.
                   Starts hidden because the panel starts empty. */ ?>
          <div class="px-3 pt-3" id="sources-help-line" style="display:none;">
            <div class="form-text moop-help">
              <?= t("All types are searched; untick any you don't want.") ?> <?= help_modal_trigger('ann-types-modal', '', 'What each annotation type covers') ?>
            </div>
          </div>
          <div class="px-2 pt-2 pb-1 border-bottom" id="sources-filter-wrap" style="display:none;">
            <input type="text" class="form-control form-control-sm moop-input" id="sources-filter"
                   placeholder="<?= t('Filter annotation types…') ?>" autocomplete="off">
          </div>
          <div id="sourcesPanel" style="overflow-y:auto; max-height:280px;">
            <div class="text-center p-3 text-muted">
              <i class="fa fa-spinner fa-spin me-1"></i> <?= t('Loading…') . "\n" ?>
            </div>
          </div>
        </div>

        <!-- Right: selected annotation types panel -->
        <div class="col-lg-4 d-flex flex-column">
          <div class="px-2 py-1 border-bottom d-flex justify-content-between align-items-center"
               style="background:#f8f9fa;">
            <span class="small fw-semibold text-muted"><?= t('Selected') ?></span>
            <span class="badge bg-secondary" id="ann-types-selected-count">0</span>
          </div>
          <div style="overflow-y:auto; max-height:280px; font-size:0.82rem;" id="ann-types-selected-panel">
            <div class="text-muted small p-2 fst-italic"><?= t('No types selected') ?></div>
          </div>
        </div>

      </div><!-- /row -->
    </div>

    <!-- ④ Search -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center tool-header">
        <span class="step-badge me-2">4</span>
        <span class="fw-semibold" style="font-size:0.9rem;"><?= t('Search') ?></span>
      </div>
      <div class="card-body py-3">
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-lg fw-semibold text-white flex-grow-1" id="searchBtn"
                  style="background:var(--moop-action); border-color:var(--moop-action);">
            <i class="fa fa-search me-2"></i><?= t('Search') . "\n" ?>
          </button>
          <button type="button" class="btn btn-lg btn-outline-danger" id="search-cancel-btn" style="display:none;">
            <i class="fa fa-ban me-1"></i><?= t('Cancel') . "\n" ?>
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
  // Step 3 help — how to use the step, in the same shape as Steps 1 and 2. Short on
  // purpose: what the tick does, and why the list is empty until Step 2 is answered. What
  // each individual TYPE means stays in ann-types-modal, reachable from the line beside
  // "annotation types" in the panel, so neither has to repeat the other.
  echo help_modal(
      'ann-types-step-help',
      'Choosing annotation types',
      [[
          'heading' => '',
          'cards'   => [
              ['label' => 'Every type starts ticked',
               'text'  => 'Untick a type and its annotations are skipped.'],
              // No "the list follows Step 2" card: the panel itself says that, in place,
              // exactly when it is true — and stops saying it once types have loaded, which
              // a modal cannot do.
              ['label' => 'Not sure what a type is?',
               'text'  => 'Open the (i) beside "annotation types" for a description of each.'],
          ],
      ]],
      ['intro' => 'Narrow the search to particular kinds of annotation.']
  );

  ?>

  <!-- Annotation types info modal -->
  <?php include_once __DIR__ . '/../../includes/ann_types_modal.php'; ?>

  <?php /* The "no annotation types selected" modal that used to live here is gone: it fired a
           modal interrupt telling the reader to go to section ③ — which, when they had not
           picked organisms yet, was an empty box. Replaced by #search-select-hint above, which
           names the step actually blocking them. */ ?>

  <!-- Search Results -->
  <div id="searchResults" class="hidden">
    <div class="card shadow-sm mb-5">
      <div class="card-header bg-search-results">
        <span class="fw-semibold text-uppercase section-eyebrow"><i class="fa fa-list me-1"></i> <?= t('Search Results') ?> <?= help_modal_trigger('search-results-help', '', 'Understanding your search results') ?></span>
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

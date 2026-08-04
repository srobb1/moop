<?php
/**
 * Index page content
 * Variables available: $siteTitle, $organism_count, $assembly_count, $cards_to_display, $taxonomy_tree_data, $user_access_json, $ip
 */
?>

<div class="container py-3">
  <!-- Page Header -->
  <div class="text-center mb-3">
    <h1 class="index-site-title moop-tool-title"><?= htmlspecialchars($siteTitle) ?></h1>
    <hr class="mx-auto page-header-divider">
    <p class="mb-2" style="font-size:0.95rem;font-weight:300;color:rgba(8,145,178,0.7);letter-spacing:0.03em;">
      Browse genes, genomes, and annotations<?php if (!empty($organism_count)): ?> across <strong style="font-weight:500;"><?= $organism_count ?></strong> organism<?= $organism_count !== 1 ? 's' : '' ?><?php if (!empty($assembly_count)): ?> and <strong style="font-weight:500;"><?= $assembly_count ?></strong> assembl<?= $assembly_count !== 1 ? 'ies' : 'y' ?><?php endif; ?><?php endif; ?>
    </p>
  </div>

  <!-- Quick search — tabbed -->
  <div class="qs-wrap mb-3">
    <ul class="nav nav-tabs justify-content-end" id="search-tabs" style="border-bottom:0;">
      <li class="nav-item">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-quick" type="button">
          <i class="fa fa-search me-1"></i>Organisms
        </button>
      </li>
      <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-feature" type="button">
          <i class="fa fa-search me-1"></i>Genes
        </button>
      </li>
    </ul>
    <div class="tab-content">

      <!-- Tab 1: organism / group / assembly / gene set search -->
      <div class="tab-pane fade show active" id="tab-quick">
        <div class="card border-0 border-top rounded-0 rounded-bottom qs-card">
          <div class="card-body p-3" style="min-height:88px;">
            <?php /* The (i) sits OUTSIDE .qs-input-wrap. The suggestions dropdown is
                     absolutely positioned to that wrapper with left:0;right:0, so putting
                     the button inside would stretch the dropdown wider than the field it
                     drops from. */ ?>
            <div class="d-flex align-items-center gap-2">
              <div class="qs-input-wrap flex-grow-1" style="min-width:0;">
                <div class="input-group">
                  <span class="input-group-text bg-white border-end-0 pe-1 text-muted">
                    <i class="fa fa-search"></i>
                  </span>
                  <input type="search" id="qs-input" data-1p-ignore data-lpignore="true" data-form-type="other" class="form-control border-start-0 border-end-0 ps-1 moop-input"
                         placeholder="Search organisms, groups, assemblies, gene sets…"
                         aria-label="Search organisms, groups, assemblies and gene sets"
                         autocomplete="off" spellcheck="false">
                  <button id="qs-go" class="btn btn-primary px-3" type="button">Go</button>
                </div>
                <div id="qs-dropdown" class="qs-dropdown"></div>
              </div>
              <?= help_modal_trigger('qs-help', '', 'Help: searching for an organism, group, assembly or gene set') ?>
            </div>
            <div class="qs-examples mt-2">
              <span class="text-muted me-1" style="font-size:0.62rem;">e.g.</span>
              <button class="qs-example-chip sci-name" type="button" style="font-size:0.62rem;">Anoura caudifer</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">Pallid Bat</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">Bats</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">GCA_004027475.1</button>
              <button class="qs-example-chip" type="button" style="font-size:0.62rem;">SIMR_2025-01-24</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Tab 2: exact sequence ID search across all accessible databases -->
      <div class="tab-pane fade" id="tab-feature">
        <div class="card border-0 border-top rounded-0 rounded-bottom qs-card">
          <div class="card-body p-3" style="min-height:88px;">
            <div class="d-flex align-items-center gap-2">
              <div class="qs-input-wrap flex-grow-1" style="min-width:0;">
                <div class="input-group">
                  <span class="input-group-text bg-white border-end-0 pe-1 text-muted">
                    <i class="fa fa-search"></i>
                  </span>
                  <input type="search" id="fs-input" data-1p-ignore data-lpignore="true" data-form-type="other" class="form-control border-start-0 border-end-0 ps-1 moop-input"
                         placeholder="Enter Accession ID…"
                         aria-label="Find a gene, transcript, CDS or protein by its exact accession ID"
                         autocomplete="off" spellcheck="false">
                  <button id="fs-go" class="btn btn-primary px-3" type="button">Search</button>
                </div>
              </div>
              <?= help_modal_trigger('fs-help', '', 'Help: finding a gene by its ID') ?>
            </div>
            <div class="qs-examples mt-2">
              <span class="text-muted me-1" style="font-size:0.62rem;">e.g.</span>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">XM_020002978.1</button>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">ACA1_PVKU01000001.1_000001</button>
              <button class="fs-example-chip qs-example-chip" type="button" style="font-size:0.62rem;">ACA1_PVKU01000001.1_000001.1</button>
            </div>
            <div id="fs-status" class="small mt-2 text-muted" style="display:none;"></div>
            <div id="fs-results"></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <?php if (!empty($featured_groups)): ?>
  <!-- Featured groups -->
  <div class="mb-3 text-center pt-2">
    <div class="text-uppercase fw-semibold mb-2" style="letter-spacing:0.1em;font-size:0.8rem;color:#0891b2;">
      Focus Your Search by Group
    </div>
    <hr class="mx-auto page-header-divider mb-3">
    <div class="d-flex justify-content-center flex-wrap gap-2">
      <?php foreach ($featured_groups as $fg): ?>
        <a href="<?= htmlspecialchars($fg['url']) ?>" class="index-group-chip text-decoration-none"
           data-group="<?= htmlspecialchars($fg['name']) ?>">
          <?= htmlspecialchars($fg['name']) ?>
        </a>
      <?php endforeach; ?>
      <button class="index-group-chip"
              style="background:#e2e8f0;color:#475569;border:none;cursor:pointer;"
              onclick="document.getElementById('browse-group-header').click()">
        More&hellip;
      </button>
    </div>
  </div>
  <?php endif; ?>

  <!-- Browse by Group collapsible header -->
  <div class="browse-select-header mb-3" id="browse-group-header"
       data-bs-toggle="collapse" data-bs-target="#browse-group-body"
       role="button" aria-expanded="false" aria-controls="browse-group-body">
    <span class="d-flex align-items-center gap-2">
      <i class="fas fa-chevron-down browse-select-chevron"></i>
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Search in a Group of Organisms</span>
    </span>
  </div>
  <div class="collapse mb-3" id="browse-group-body">
    <div class="groups-strip">
      <p class="text-muted small mb-2">Click a group to focus your searches on a curated set of organisms.</p>
      <div class="index-group-chip-list">
        <?php foreach ($cards_to_display as $card): ?>
          <a href="<?= htmlspecialchars($card['link']) ?>" target="_blank"
             class="index-group-chip text-decoration-none"
             data-group="<?= htmlspecialchars($card['title']) ?>">
            <?= htmlspecialchars($card['title']) ?>
            <?php if (!empty($card['organism_count'])): ?>
              <span class="index-group-chip-count">(<?= $card['organism_count'] ?>)</span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Browse & Select collapsible header -->
  <div class="browse-select-header mb-0" id="browse-select-header"
       data-bs-toggle="collapse" data-bs-target="#browse-select-body"
       role="button" aria-expanded="false" aria-controls="browse-select-body">
    <span class="d-flex align-items-center gap-2">
      <i class="fas fa-chevron-down browse-select-chevron"></i>
      <span class="text-uppercase fw-semibold" style="letter-spacing:0.1em; font-size:0.8rem;">Search in a Custom Selection of Organisms</span>
    </span>
  </div>

  <?php
  // Help for the two quick searches. They look identical — same box, same button — and do
  // entirely different things, which is the one confusion this page can create. The Genes
  // box is an EXACT id lookup, so a reader who types "kinase" into it gets nothing and has
  // no way to find out why; that is what the second modal exists to prevent.
  echo help_modal(
      'qs-help',
      'Finding an organism, group, assembly or gene set',
      [
          ['heading' => 'What you can type', 'cards' => [
              ['label' => 'A species', 'text' => 'Scientific or common name — "Nematostella vectensis" or "Starlet Sea Anemone". Both work.'],
              ['label' => 'A group',   'text' => 'A curated set such as Bats or Planaria, to search every organism in it at once.'],
              ['label' => 'An assembly or gene set', 'text' => 'An accession like GCA_004027475.1, or a gene set name like SIMR_2025-01-24.'],
          ]],
          ['heading' => 'What happens', 'cards' => [
              ['label' => 'It takes you there', 'text' => 'Choosing a suggestion opens that page, and searches you run from it stay scoped to what you picked.'],
              ['label' => 'The examples are live', 'text' => 'Click any chip under the box to run it and see the shape of the answer.'],
          ]],
      ],
      ['intro' => 'Jump to an organism, a group of organisms, an assembly, or one gene set.']
  );

  echo help_modal(
      'fs-help',
      'Finding a gene by its ID',
      [
          ['heading' => 'What this does', 'cards' => [
              ['label' => 'Exact IDs only', 'text' => 'Paste the accession of a gene, transcript, CDS or protein. It must match exactly — this is a lookup, not a search.'],
              ['label' => 'Everywhere at once', 'text' => 'Every gene set you have access to is checked, so you need not know which organism the ID belongs to.'],
              ['label' => 'Lands on the gene', 'text' => 'A transcript, CDS or protein ID all open the gene page they belong to.'],
          ]],
          // Two routes, because they answer different questions and the choice is the
          // thing a newcomer cannot guess. Naming only the annotation search would send
          // someone hunting one organism across all 85.
          ['heading' => 'Looking for a word, not an ID?', 'cards' => [
              ['label' => 'In one organism, or one group',
               'text' => 'Use the Organisms tab to open that organism or group, then search from its page — results stay scoped to what you picked.'],
              ['label' => 'Across everything, or your own list', 'html' => true,
               'text' => 'Use the <a href="/' . htmlspecialchars($site) . '/tools/search.php">annotation search</a>, where you can search every organism at once or build a custom selection.'],
              ['label' => 'Not this box',
               'text' => 'A protein name, domain or GO term — "kinase", "zinc finger" — will not match here. This field compares accessions exactly.'],
          ]],
      ],
      ['intro' => 'Go straight to one feature when you already have its accession.']
  );
  ?>

  <?php
  // How the Browse & Select workflow works. Was 60 lines of hand-written modal markup with
  // no trigger anywhere on the page -- unreachable since it was written, which is why the
  // home page scored zero help. Rebuilt on help_modal() like every other help on the site.
  //
  // THE TOOL LIST IS GENERATED, not typed. It reads the same source the Tool Box renders
  // from -- getAvailableTools() filtered on the `toolbox` flag -- so it cannot drift from
  // what is actually on the page. The hand-written version had already gone stale by two
  // tools. Annotation Search and MOOPmart are deliberately NOT here: they carry
  // 'toolbox' => false in tools_config.php because they are dense pages that should not be
  // one casual click from the front door, and this help follows that decision rather than
  // quietly reversing it.
  $__box_tools = array_filter(
      getAvailableTools(createToolContext('index', ['use_onclick_handler' => true])),
      fn($t) => ($t['toolbox'] ?? true) !== false
  );
  $__tool_cards = [];
  foreach ($__box_tools as $__t) {
      $__tool_cards[] = ['label' => $__t['name'], 'text' => $__t['description'] ?? ''];
  }

  echo help_modal(
      'how-to-modal',
      'Searching a selection of organisms',
      [
          ['heading' => 'Two steps', 'cards' => [
              ['label' => 'Pick your organisms',
               'text' => 'Use any of the selection modes in this section — by group, by taxonomy, or by name. Mix them freely; your list carries over as you switch.'],
              ['label' => 'Then choose a tool',
               'text' => 'Every tool in the box opens in a new tab, already limited to the organisms you picked.'],
          ]],
          ['heading' => 'Tools in the box', 'cards' => $__tool_cards],
          ['heading' => 'Worth knowing', 'cards' => [
              ['label' => 'Your selection persists',
               'text' => 'It survives switching between the selection modes, so you can build a list from more than one of them.'],
              ['label' => 'Check it before you run',
               'text' => 'The Selected Organisms panel lists everything currently picked, and lets you drop any entry.'],
              ['label' => 'More tools in the menu',
               'text' => 'The Tools menu at the top carries the heavier ones — annotation search and the data exporter — which work the same way but ask more of you.'],
          ]],
      ],
      ['intro' => 'Build a list of organisms, then run any tool across all of them at once.']
  );
  ?>

  <!-- Browse & Select: selected organisms full-width top row, then step 1 + step 2 below -->
  <div class="collapse" id="browse-select-body">
  <div class="browse-select-panel">
  <p class="text-muted small mb-2 px-1">Build a custom collection of organisms to focus your searches — complete
    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#6366f1;color:#fff;font-size:0.62rem;font-weight:700;vertical-align:middle;">1</span>
    then
    <span style="display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#6366f1;color:#fff;font-size:0.62rem;font-weight:700;vertical-align:middle;">2</span>
    below.
    <?php /* The (i) lives HERE, in the panel body, not on the collapse header above.
             The header bar is the collapse toggle, and Bootstrap 5 registers its delegated
             data-api in the CAPTURE phase — so a button anywhere inside that bar fires the
             collapse on the way down and no stopPropagation at the button can stop it.
             Putting the help beside the panel's own intro sentence sidesteps that entirely,
             keeps the whole bar clickable, and puts the explanation next to the thing it
             explains: you are reading this line precisely when you are working out what
             "complete 1 then 2" means. */ ?>
    <?= help_modal_trigger('how-to-modal', '', 'Help: searching a custom selection of organisms') ?>
  </p>
  <div class="bs-grid" id="organism-tabs-anchor">

    <!-- Row 1: Selected Organisms — full width -->
    <div class="bs-grid-selected">
      <div class="card shadow-sm selection-empty" id="selected-organisms-card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <span class="fw-semibold" style="font-size:0.9rem;">
            Selected Organisms
            <span class="badge bg-light text-dark ms-1" id="selected-count">0</span>
          </span>
          <button id="clear-all-organisms" class="btn btn-sm btn-outline-light py-0 px-2" style="display:none" title="Clear all">
            <i class="fa fa-times me-1"></i>Clear all
          </button>
        </div>
        <div class="card-body p-2">
          <div id="selected-organisms-list">
            <div class="text-muted fst-italic small px-1">No organisms selected</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2, Col 1: Step 1 — organism selection tabs -->
    <div class="bs-grid-step1">
      <div class="card shadow-sm">
        <div class="card-header bg-tools text-white py-2">
          <span class="step-badge me-2">1</span>
          <span class="fw-semibold" style="font-size:0.9rem;">Select one or more organisms</span>
        </div>
        <div class="card-body p-2">
          <ul class="nav nav-tabs" id="organism-tabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-organism-select" type="button" role="tab">
                <i class="fa fa-list me-1"></i> Organisms Select
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-taxon-select" type="button" role="tab">
                <i class="fa fa-sitemap me-1"></i> Taxon Select
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-tree-select" type="button" role="tab">
                <i class="fa fa-project-diagram me-1"></i> Tree Select
              </button>
            </li>
          </ul>

          <div class="tab-content border border-top-0 rounded-bottom p-3" id="organism-tabs-content">

            <!-- Tab 1: Organism Select -->
            <div class="tab-pane fade show active" id="tab-organism-select" role="tabpanel">
              <p class="text-muted small mb-2">Filter by scientific name, common name, or group to find specific species. Select one or more to use with a tool.</p>
              <input type="search" autocomplete="off" data-1p-ignore data-lpignore="true" data-form-type="other" class="form-control form-control-sm mb-2 moop-input" id="organism-select-filter"
                     placeholder="Filter by name, common name, or group…">
              <div id="organism-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 2: Taxon Select -->
            <div class="tab-pane fade" id="tab-taxon-select" role="tabpanel">
              <p class="text-muted small mb-2">Each row shows the full taxonomic lineage — filter by any rank to find related species. Select one or more to use with a tool.</p>
              <input type="search" autocomplete="off" data-1p-ignore data-lpignore="true" data-form-type="other" class="form-control form-control-sm mb-2 moop-input" id="taxon-select-filter"
                     placeholder="Filter by taxonomy, name, or common name…">
              <div id="taxon-select-list" class="org-select-list"></div>
            </div>

            <!-- Tab 3: Tree Select -->
            <div class="tab-pane fade" id="tab-tree-select" role="tabpanel">
              <p class="text-muted small mb-2">Click a branch to expand or collapse it. Use the <strong>+</strong> button on any row to add those organisms to your selection. Select one or more to use with a tool.</p>
              <div class="d-flex align-items-center justify-content-end mb-2">
                <div class="d-flex gap-1">
                  <button id="tree-expand-all" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Expand All</button>
                  <button id="tree-collapse-all" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.75rem;">Collapse All</button>
                </div>
              </div>
              <input type="search" autocomplete="off" data-1p-ignore data-lpignore="true" data-form-type="other" class="form-control form-control-sm mb-2 moop-input" id="taxonomy-filter" placeholder="Filter by taxon or organism…">
              <div class="taxonomy-tree-scroll">
                <div id="taxonomy-tree-container"></div>
              </div>
            </div>

          </div><!-- /tab-content -->
        </div><!-- /card-body -->
      </div><!-- /card -->
    </div><!-- /bs-grid-step1 -->

    <!-- Row 2, Col 2: Step 2 — tool selection -->
    <div class="bs-grid-step2">
      <div id="tools-card-wrapper">
      <?php
      $context = createToolContext('index', ['use_onclick_handler' => true]);
      include_once TOOL_SECTION_PATH;
      ?>
      </div>
    </div><!-- /bs-grid-step2 -->

  </div><!-- /bs-grid -->
  </div><!-- /browse-select-panel -->
  </div><!-- /collapse browse-select-body -->
</div><!-- /container -->

<script src="js/modules/taxonomy-tree.js?v=<?= filemtime(__DIR__ . '/../../js/modules/taxonomy-tree.js') ?>"></script>
<script src="js/index.js?v=<?= filemtime(__DIR__ . '/../../js/index.js') ?>"></script>
<script>
const userAccess    = <?= $user_access_json ?>;
const treeData      = <?= json_encode($taxonomy_tree_data) ?>;
const organismData  = <?= $organism_list_json ?>;

</script>

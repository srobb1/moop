<?php
/**
 * "Which page do I want?" — the router.
 *
 * Renders from docs/page_purposes.json, which scripts/extract_page_purposes.php builds
 * by reading each page's own page_purpose() declaration off the RENDERED page. So every
 * sentence here is the sentence that page actually shows or declares — there is no second
 * copy to drift, and a page that stops declaring one simply drops out.
 *
 * Refresh with:  php scripts/extract_page_purposes.php --write
 *
 * ⚠️ Not every page can be linked. organism / assembly / gene set / group pages need a real
 * id in the URL, and any link written here would hard-code whichever organism happened to be
 * public the day it was written. Those carry a per-route "how you get there" instead, written
 * in the extractor's $meta — NOT a generic line appended to anything without a link. These
 * pages form a chain (organism -> assembly -> gene set), so "pick an organism first" is wrong
 * on the gene-set card. A card can list more than one route when two pages were combined.
 *
 * Available: $config, $siteTitle
 */

$site = $config->getString('site');
$file = __DIR__ . '/../../../docs/page_purposes.json';
$data = function_exists('loadJsonFile') ? loadJsonFile($file, []) : [];
$routes = $data['routes'] ?? [];

// Task-first: "I want to X" -> the page for it. Hand-written because no data backs a task,
// but each line says only WHERE to go and what makes that page the right one — how to use
// the page is that page's own (i) help, never restated here.
$tasks = [
    ['Find genes by name, keyword or GO term', 'Across several organisms at once. Group, organism, assembly and gene set pages each have their own search box too — see Every page below.',
     [['Annotation Search', '/tools/search.php'], ['Every page', '#every-page']]],
    ['See everything about one gene', 'Search for its ID, then click it in the results to open the gene page.',
     [['Annotation Search', '/tools/search.php']]],
    ['Find where a sequence matches', 'Paste DNA or protein and choose the assemblies to search.',
     [['BLAST', '/tools/blast.php']]],
    ['Check a primer pair, or design new primers', 'Check where a pair binds, or design primers for a sequence.',
     [['Primer BLAST', '/tools/primer_blast.php'], ['Primer Maker', '/tools/primer_maker.php']]],
    ['Get sequences for a list of gene IDs', 'Protein, mRNA, CDS or genomic sequence for the IDs you paste — whole, or a range of positions.',
     [['Retrieve Sequences', '/tools/retrieve_sequences.php']]],
    ['Export a gene list with its annotations', 'Build the list by ID, name or annotation, across organisms, and download TSV or FASTA.',
     [['MOOPmart (Data Exporter)', '/tools/moopmart.php']]],
    ['Download a whole genome, gene set or GFF', 'Complete files, per assembly and gene set.',
     [['Downloads', '/tools/downloads.php']]],
    ['See a gene in its place on the genome', 'Genes, data tracks and sequence, interactively.',
     [['Genome Browser', '/jbrowse2.php']]],
    ['Find out which annotations an organism has', 'Every annotation source, by type, for the data you can see.',
     [['Where the data comes from', '/help.php?topic=data-sources']]],
];
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-map-signs me-2"></i>What do you want to do?
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Common tasks and where to do them, then every page of <?= htmlspecialchars($siteTitle) ?> in
        one sentence. Each page's own (i) button explains how to use it.
      </p>
    </div>
  </div>

  <?php if (empty($routes)): ?>
    <div class="alert alert-warning">
      No page list found. Regenerate it with
      <code>php scripts/extract_page_purposes.php --write</code>.
    </div>
  <?php else: ?>

  <div class="row mb-4">
    <div class="col-lg-7">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
        <input type="search" class="form-control border-start-0 moop-input"
               data-help-filter=".task-card, .route-card" data-filter-msg="route-filter-msg"
               data-filter-noun="match" data-filter-nouns="matches"
               placeholder="What do you want to do? — try &quot;download&quot;, &quot;sequence&quot;, &quot;annotation&quot;…"
               autocomplete="off" spellcheck="false" aria-label="Filter tasks and pages">
      </div>
      <div id="route-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <section data-filter-group>
    <h2 class="h5 fw-semibold mb-3">Common tasks</h2>
    <div class="row">
      <?php foreach ($tasks as [$task, $hint, $links]): ?>
        <?php $search = strtolower($task . ' ' . $hint . ' ' . implode(' ', array_column($links, 0))); ?>
        <div class="col-md-6 col-lg-4 mb-3 task-card" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
          <div class="card h-100 shadow-sm help-topic-card">
            <div class="card-body p-3 d-flex flex-column">
              <h3 class="h6 fw-semibold mb-1 text-body"><?= htmlspecialchars($task) ?></h3>
              <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars($hint) ?></p>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($links as [$label, $href]): ?>
                  <a class="btn btn-sm btn-outline-moop" href="<?= $href[0] === '#' ? '' : '/' . htmlspecialchars($site) ?><?= htmlspecialchars($href) ?>">
                    <?= htmlspecialchars($label) ?> <i class="fa fa-arrow-right ms-1"></i>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section data-filter-group>
  <h2 class="h5 fw-semibold mb-3 mt-2" id="every-page">Every page</h2>
  <div class="row" id="route-list">
    <?php foreach ($routes as $r): ?>
      <?php $search = strtolower($r['title'] . ' ' . $r['purpose']); ?>
      <div class="col-md-6 mb-3 route-card" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3 d-flex flex-column">
            <h6 class="fw-semibold mb-1 text-body"><?= htmlspecialchars($r['title']) ?></h6>
            <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars($r['purpose']) ?></p>
            <?php if (!empty($r['linkable']) && !empty($r['link'])): ?>
              <a class="btn btn-sm btn-outline-moop align-self-start"
                 href="/<?= htmlspecialchars($site) ?><?= htmlspecialchars($r['link']) ?>">
                Go to <?= htmlspecialchars($r['title']) ?> <i class="fa fa-arrow-right ms-1"></i>
              </a>
            <?php else: ?>
              <?php /* No link on purpose — see the header comment. The route is written PER
                       PAGE rather than appended generically: these pages form a chain
                       (organism -> assembly -> gene set), so "pick an organism first" is
                       simply wrong on the gene-set card. A card can carry more than one route
                       when two pages were combined. */ ?>
              <?php foreach ((array)($r['reached_by'] ?? []) as $how): ?>
                <span class="small text-muted align-self-start d-block">
                  <i class="fa fa-info-circle me-1"></i><?= htmlspecialchars($how) ?>
                </span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  </section>

  <p class="text-muted small">
    Every page above can be reached from the <a href="/<?= htmlspecialchars($site) ?>/">home page</a>.
  </p>


  <?php endif; ?>
</div>

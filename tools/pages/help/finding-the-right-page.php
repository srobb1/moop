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

$file = __DIR__ . '/../../../docs/page_purposes.json';
$data = function_exists('loadJsonFile') ? loadJsonFile($file, []) : [];
$routes = $data['routes'] ?? [];

// Task-first: "I want to X" -> the page for it. Hand-written because no data backs a task,
// but each line says only WHERE to go and what makes that page the right one — how to use
// the page is that page's own (i) help, never restated here.
$tasks = [
    [t_plain('Find genes by name, keyword or GO term'), t_plain('Across several organisms at once. Group, organism, assembly and gene set pages each have their own search box too — see Every page below.'),
     [[t_plain('Annotation Search'), '/tools/search.php'], [t_plain('Every page'), '#every-page']]],
    [t_plain('Find every gene with a GO term, including more specific terms'), t_plain('Pick a term from the tree; its page lists the genes annotated with it or with any term below it.'),
     [[t_plain('Anatomy & Gene Function'), '/tools/ontology_browser.php']]],
    [t_plain('See everything about one gene'), t_plain('Search for its ID, then click it in the results to open the gene page.'),
     [[t_plain('Annotation Search'), '/tools/search.php']]],
    [t_plain('Find the genes that match a human gene'), t_plain('Search for the human gene symbol, such as PIWIL1. Genes whose closest human gene or ortholog has that name come up; each gene page says how strong the match is.'),
     [[t_plain('Annotation Search'), '/tools/search.php']]],
    [t_plain('Translate an ID into the other assemblies and transcriptomes'), t_plain('Paste IDs from a paper or another data set; get the same gene under its IDs in the other transcriptomes, gene sets, ESTs and arrays of that species.'),
     [[t_plain('Rosetta Stone ID Translator'), '/tools/crosswalk.php']]],
    [t_plain('Find where a sequence matches'), t_plain('Paste DNA or protein and choose the assemblies to search.'),
     [[t_plain('BLAST'), '/tools/blast.php']]],
    [t_plain('Check a primer pair, or design new primers'), t_plain('Check where a pair binds, or design primers for a sequence.'),
     [[t_plain('Primer BLAST'), '/tools/primer_blast.php'], [t_plain('Primer Maker'), '/tools/primer_maker.php']]],
    [t_plain('Get sequences for a list of gene IDs'), t_plain('Protein, mRNA, CDS or genomic sequence for the IDs you paste — whole, or a range of positions.'),
     [[t_plain('Retrieve Sequences'), '/tools/retrieve_sequences.php']]],
    [t_plain('Export a gene list with its annotations'), t_plain('Build the list by ID, name or annotation, across organisms, and download TSV or FASTA — or send it to the RNA-seq Expression Explorer.'),
     [[t_plain('MOOPmart (Data Exporter)'), '/tools/moopmart.php']]],
    [t_plain('Compare the expression of a list of genes'), t_plain('Paste genes and see them across experiments as a heatmap. Or build the list in MOOPmart and send it — each gene set with expression data gets its own heatmap.'),
     [[t_plain('RNA-seq Expression Explorer'), '/tools/expression.php'], [t_plain('MOOPmart (Data Exporter)'), '/tools/moopmart.php']]],
    [t_plain('Find genes reported as expressed in a body part'), t_plain('Planarian expression published in papers, by body part, by paper or by gene.'),
     [[t_plain('Expression by Anatomy'), '/tools/anatomy_search.php'], [t_plain('Anatomy & Gene Function'), '/tools/ontology_browser.php']]],
    [t_plain('Download a whole genome, gene set or GFF'), t_plain('Complete files, per assembly and gene set.'),
     [[t_plain('Downloads'), '/tools/downloads.php']]],
    [t_plain('See a gene in its place on the genome'), t_plain('Genes, data tracks and sequence, interactively.'),
     [[t_plain('Genome Browser'), '/jbrowse2.php']]],
    [t_plain('Find out which annotations an organism has'), t_plain('Every annotation source, by type, for the data you can see.'),
     [[t_plain('Where the data comes from'), '/help.php?topic=data-sources']]],
    [t_plain('List the genes in a KEGG pathway'), t_plain('Open a gene set page: its KEGG Pathways card lists each pathway with its genes, and opens KEGG\'s map with them coloured.'),
     [[t_plain('Gene set page'), '#every-page']]],
];

// Help follows the tool switches (Manage Tools; Sofia, 2026-11-06): a switched-off tool's page
// answers 404, so no task or page card may send anyone there. A link is dropped, a task left with
// no link is dropped, and a hint that names a switched-off tool in passing gets its shorter form.
$tool_off = fn(string $href): bool => $href[0] === '/' && strpos($href, '/help.php') !== 0
    && moop_disabled_tool_for_script($config, moop_url_base() . strtok($href, '?#')) !== null;
$hint_without = [
    t_plain('Export a gene list with its annotations') => ['/tools/expression.php',
        t_plain('Build the list by ID, name or annotation, across organisms, and download TSV or FASTA.')],
    t_plain('Compare the expression of a list of genes') => ['/tools/moopmart.php',
        t_plain('Paste genes and see them across experiments as a heatmap.')],
];
foreach ($tasks as $i => [$task, $hint, $links]) {
    $links = array_values(array_filter($links, fn($l) => !$tool_off($l[1])));
    if (!$links) { unset($tasks[$i]); continue; }
    if (isset($hint_without[$task]) && $tool_off($hint_without[$task][0])) $hint = $hint_without[$task][1];
    $tasks[$i] = [$task, $hint, $links];
}
$routes = array_values(array_filter($routes, fn($r) => empty($r['link']) || !$tool_off((string) $r['link'])));
// Primer Maker's sentence names the four shipped cards; a site that changed them gets the generic one.
foreach ($routes as $i => $r) {
    if (($r['link'] ?? '') === '/tools/primer_maker.php' && !moop_primer_maker_as_shipped()) {
        $routes[$i]['purpose'] = PrimerPresets::GENERIC_PURPOSE;
    }
}
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-map-signs me-2"></i><?= t('What do you want to do?') . "\n" ?>
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        <?= t('Common tasks and where to do them, then every page of %s in one sentence. Each page\'s own (i) button explains how to use it.', $siteTitle) . "\n" ?>
      </p>
    </div>
  </div>

  <?php if (empty($routes)): ?>
    <div class="alert alert-warning">
      <?= t('No page list found.') ?> <?= t_markup('Regenerate it with %s.', '<code>php scripts/extract_page_purposes.php --write</code>') . "\n" ?>
    </div>
  <?php else: ?>

  <div class="row mb-4">
    <div class="col-lg-7">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0 text-muted"><i class="fa fa-search"></i></span>
        <input type="search" class="form-control border-start-0 moop-input"
               data-help-filter=".task-card, .route-card" data-filter-msg="route-filter-msg"
               data-filter-noun="<?= t('match') ?>" data-filter-nouns="<?= t('matches') ?>"
               placeholder="<?= t('What do you want to do? — try "download", "sequence", "annotation"…') ?>"
               autocomplete="off" spellcheck="false" aria-label="<?= t('Filter tasks and pages') ?>">
      </div>
      <div id="route-filter-msg" class="small text-muted mt-1" style="display:none;"></div>
    </div>
  </div>

  <section data-filter-group>
    <h2 class="h5 fw-semibold mb-3"><?= t('Common tasks') ?></h2>
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
                  <a class="btn btn-sm btn-outline-moop" href="<?= $href[0] === '#' ? '' : htmlspecialchars(moop_url_base()) ?><?= htmlspecialchars($href) ?>">
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
  <h2 class="h5 fw-semibold mb-3 mt-2" id="every-page"><?= t('Every page') ?></h2>
  <div class="row" id="route-list">
    <?php foreach ($routes as $r): ?>
      <?php // Route text comes from docs/page_purposes.json (English). The purposes are each page's own
            // page_purpose() sentence, already in the catalog; titles and routes come from the extractor
            // (scripts/, which the catalog scan does not read), so they translate only once listed.
            $r['title'] = t_plain((string) $r['title']); $r['purpose'] = t_plain((string) $r['purpose']); ?>
      <?php $search = strtolower($r['title'] . ' ' . $r['purpose']); ?>
      <div class="col-md-6 mb-3 route-card" data-search="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3 d-flex flex-column">
            <h6 class="fw-semibold mb-1 text-body"><?= htmlspecialchars($r['title']) ?></h6>
            <p class="text-muted small flex-grow-1 mb-2"><?= htmlspecialchars($r['purpose']) ?></p>
            <?php if (!empty($r['linkable']) && !empty($r['link'])): ?>
              <a class="btn btn-sm btn-outline-moop align-self-start"
                 href="<?= htmlspecialchars(moop_url_base()) ?><?= htmlspecialchars($r['link']) ?>">
                <?= t('Go to %s', $r['title']) ?> <i class="fa fa-arrow-right ms-1"></i>
              </a>
            <?php else: ?>
              <?php /* No link on purpose — see the header comment. The route is written PER
                       PAGE rather than appended generically: these pages form a chain
                       (organism -> assembly -> gene set), so "pick an organism first" is
                       simply wrong on the gene-set card. A card can carry more than one route
                       when two pages were combined. */ ?>
              <?php foreach ((array)($r['reached_by'] ?? []) as $how): ?>
                <span class="small text-muted align-self-start d-block">
                  <i class="fa fa-info-circle me-1"></i><?= htmlspecialchars(t_plain((string) $how)) ?>
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
    <?= t_markup('Every page above can be reached from the %s.', '<a href="' . htmlspecialchars(moop_url_base()) . '/">' . t('home page') . '</a>') . "\n" ?>
  </p>


  <?php endif; ?>
</div>

<?php
/**
 * Using the data with an AI assistant -- the people's version of the agent guide (llms.php).
 *
 * Only shown where the setting ai_guide is 'here' (help.php sends other sites to the guide's own
 * site). The examples and endpoints must match llms.php; change both together.
 *
 * Available: $config, $siteTitle
 */

$guide = moop_ai_guide();
$llms  = $guide['llms'] ?? moop_public_url('llms.txt');

$smed = '<em>Schmidtea mediterranea</em>';
$examples = [
    ['fa-dna',          t('A gene'),             t_markup('What is the gene SMED30002742 in %s? What does it do, which GO terms and protein domains does it have, and where in the cell is its protein predicted to be?', $smed)],
    ['fa-exchange-alt', t('Translate IDs'),       t_markup('Translate these %s IDs into the other assemblies and transcriptomes, as a table: …', $smed)],
    ['fa-sitemap',      t('Orthologs'),           t('For this list of genes, which have orthologs in human or mouse, and what are they called?')],
    ['fa-tags',         t('Genes with a GO term'), t_markup('Which genes in the %s gene set schMedS3h1_WBPS19 are annotated with axon guidance (GO:0007411), including more specific terms?', $smed)],
    ['fa-puzzle-piece', t('Protein domains'),     t_markup('Which genes in %s and zebrafish have a thioredoxin domain? And which protein domains do these genes have: …', $smed)],
    ['fa-project-diagram', t('KEGG pathways'),    t_markup('List the genes in the lysosome pathway in the %s gene set schMedS3h1_WBPS19. Which pathways is this gene in?', $smed)],
    ['fa-search',       t('Find genes'),          t_markup('Find the piwi genes in %s and list them with their names and descriptions.', $smed)],
    ['fa-user',         t('From human genes'),    t_markup('Which %s genes correspond to human PIWIL1, PCNA and SOX2? Say whether each is an ortholog or only similar.', $smed)],
    ['fa-arrows-alt-h', t('Neighbouring genes'),  t('What are the five genes upstream and downstream of this gene in the genome: …')],
    ['fa-microscope',   t('Expressed in a tissue'), t_markup('Which %s genes have been reported as expressed in the pharynx, and in which papers?', $smed)],
    ['fa-chart-line',   t('RNA-seq'),             t_markup('Which %s genes mark embryo stage 6, expressed there more than at any other stage?', $smed)],
    ['fa-table',        t('Annotate a gene list'), t('For these genes, make a table with their protein domains, GO terms and closest human gene: …')],
    ['fa-file-alt',     t('Sequences'),           t('Give me the protein sequences of these genes, and the 500 bases upstream of each.')],
    ['fa-chart-bar',    t('Assemblies'),          t_markup('How complete are the %s gene sets (BUSCO), and what is each assembly’s N50?', $smed)],
];
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-robot me-2"></i><?= t('Using the data with an AI assistant') . "\n" ?>
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="mb-2"><?= t('An AI assistant that can read web pages (Claude, ChatGPT, Perplexity and others) can look things up here for you: genes, their annotations, protein domains, KEGG pathways, orthologs, ID translations and sequences.') ?></p>
      <p class="text-muted mb-0"><?= t('It reads a short guide written for assistants, which tells it how to ask the site for data instead of reading whole web pages.') ?></p>
    </div>
  </div>

  <h2 class="h5 fw-semibold mb-3"><?= t('How to start') ?></h2>
  <div class="card shadow-sm mb-4">
    <div class="card-body p-4">
      <p class="mb-2"><?= t('Start your chat by giving the assistant the guide, then ask your question:') ?></p>
      <pre class="bg-light border rounded p-3 mb-3 text-wrap"><code><?= t('Read %s and use it to answer: …', $llms) ?></code></pre>
      <p class="text-muted small mb-0"><?= t_markup('The guide itself: %s', '<a href="' . htmlspecialchars($llms) . '">' . htmlspecialchars($llms) . '</a>') ?></p>
    </div>
  </div>

  <h2 class="h5 fw-semibold mb-3"><?= t('Things to ask') ?></h2>
  <div class="row g-3 mb-4">
    <?php foreach ($examples as [$icon, $label, $text]): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h3 class="h6 fw-semibold mb-1"><i class="fa <?= $icon ?> me-2" style="color:var(--moop-tool-header);"></i><?= $label ?></h3>
            <p class="text-muted small mb-0">“<?= $text ?>”</p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="h5 fw-semibold mb-3"><?= t('Good to know') ?></h2>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('What it sees depends on how it connects') ?></h3>
          <p class="text-muted small mb-2"><?= t('Most assistants fetch pages from their own servers, so they see only the public data: never logged in as you, never on the institute network.') ?></p>
          <p class="text-muted small mb-2"><?= t('An assistant that works through your own browser or computer (a browser extension, an AI browser, a desktop app’s built-in browser, or a coding agent on your machine) sees what you see. That includes data that needs your login, and on the institute network it includes data opened by the network itself.') ?></p>
          <p class="text-muted small mb-0"><?= t('Be careful with unpublished data in those tools: whatever the assistant reads ends up in your chat with it.') ?></p>
        </div>
      </div>
    </div>
    <?php /* Added 2026-11-23: a visitor's Claude (desktop app + Claude in Chrome) stopped at the
             human check; the request log showed it. llms.txt and api/v1 are exempt now, but a
             browser-driving assistant still meets the check on ordinary pages. */ ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('Works best with a coding agent (such as Claude Code)') ?></h3>
          <p class="text-muted small mb-2"><?= t('The assistant has to open web addresses it builds itself, one per question. A coding agent on your computer, such as Claude Code, can do that.') ?></p>
          <p class="text-muted small mb-0"><?= t('Many chat apps, such as the Claude desktop and web app, only open addresses that are already in the chat, so they get stuck after reading the guide. If yours says so, switch to a coding agent.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('Using a browser extension (such as Claude in Chrome)') ?></h3>
          <p class="text-muted small mb-2"><?= t('Let the extension use this site when it asks, or allow the site in the extension’s settings.') ?></p>
          <p class="text-muted small mb-0"><?= t('If a “Verify you are human” box appears, click it yourself. Assistants will not answer these checks, so it stops and waits. Once you have passed it, the assistant can carry on.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('Check what it tells you') ?></h3>
          <p class="text-muted small mb-0"><?= t('Assistants can misread data or fill gaps with guesses. Ask it for the gene page link behind each answer and look for yourself before you rely on it.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('“Where is it?” means where in the cell') ?></h3>
          <p class="text-muted small mb-0"><?= t('The location in a gene’s annotations is a prediction of where in the cell the protein goes (nucleus, cytoplasm, membrane …), made by DeepLoc from its sequence. It does not say in which tissue or cell type the gene is expressed.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('Some things need the web page') ?></h3>
          <p class="text-muted small mb-0"><?= t('BLAST, Primer BLAST, keyword search and the genome browser run in your browser. The assistant can tell you which page to use and what to put in it.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('If your assistant cannot read web pages') ?></h3>
          <p class="text-muted small mb-0"><?= t('Open the gene page, choose Download in its overview, save the gene as Markdown and attach that file to your chat.') ?></p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1"><?= t('For scripts and pipelines') ?></h3>
          <p class="text-muted small mb-0"><?= t('The guide lists the same plain web addresses that return JSON or tables, so a script can use them too. Please be polite: one request at a time, gene lists in one request rather than one per gene, and whole genomes or gene sets as one file download. Requests are limited per address.') ?></p>
        </div>
      </div>
    </div>
  </div>

  <p class="text-muted small mb-5"><?= t_markup('When you use this data, please cite %s and the original sources, such as InterPro or UniProt. Each gene record lists where its data came from.', htmlspecialchars($siteTitle)) ?></p>

</div>

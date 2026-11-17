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

$examples = [
    ['fa-dna',          t('A gene'),             t('What is the gene SMED30002742 in Schmidtea mediterranea? What does it do, which GO terms and protein domains does it have, and where in the cell is its protein predicted to be?')],
    ['fa-exchange-alt', t('Translate IDs'),       t('Translate these Schmidtea mediterranea IDs into the other assemblies and transcriptomes, as a table: …')],
    ['fa-sitemap',      t('Orthologs'),           t('For this list of genes, which have orthologs in human or mouse, and what are they called?')],
    ['fa-tags',         t('Genes with a GO term'), t('Which genes in the newest Schmidtea mediterranea gene set are annotated with axon guidance (GO:0007411), including more specific terms?')],
    ['fa-puzzle-piece', t('Protein domains'),     t('Which genes in Schmidtea mediterranea and zebrafish have a thioredoxin domain? And which protein domains do these genes have: …')],
    ['fa-project-diagram', t('KEGG pathways'),    t('List the genes in the lysosome pathway in the newest Schmidtea mediterranea gene set. Which pathways is this gene in?')],
    ['fa-file-alt',     t('Sequences'),           t('Give me the genome sequence 200 bases upstream of this gene.')],
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
          <h3 class="h6 fw-semibold mb-1"><?= t('It sees the public data only') ?></h3>
          <p class="text-muted small mb-0"><?= t('The assistant fetches pages from its own servers, not from your computer, so it is never logged in as you and never on the institute network. Data that needs a login is invisible to it, even when you can see it.') ?></p>
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
          <p class="text-muted small mb-0"><?= t('The guide lists the same plain web addresses that return JSON or tables, so a script can use them too. Requests are limited per address; for whole genomes or gene sets, download the files rather than asking gene by gene.') ?></p>
        </div>
      </div>
    </div>
  </div>

  <p class="text-muted small mb-5"><?= t_markup('Please cite %s and the data sources a record names when you use the data.', htmlspecialchars($siteTitle)) ?></p>

</div>

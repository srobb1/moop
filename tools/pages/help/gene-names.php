<?php
/**
 * How genes are named -- the naming rules in plain words, then the key to the evidence tag
 * that ends a pipeline-named gene's description ("[ISO|1to1|sim+|pthr+]").
 *
 * The site's pages hide that tag (moop_strip_naming_tag()) and say the same thing in words on
 * the gene page; a visitor meets the raw tag in downloads and in the API. The key itself is
 * moop_naming_tag_key() (lib/functions_display.php), the same copy llms.php prints for AI
 * assistants. The rules and the Strong / Moderate / Weak words below follow the naming script,
 * config/build_and_load_db/analysis_parsers/assign_gene_names_v2.pl (the steps; sub relationship
 * and the comment above it) -- compute side's; if a step changes there, change this page.
 *
 * Available: $config, $siteTitle
 */

$key  = moop_naming_tag_key();
$code = fn(string $c): string => '<code class="text-nowrap">' . htmlspecialchars($c) . '</code>';
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-tag me-2"></i><?= t('How genes are named') . "\n" ?>
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="mb-2"><?= t('Our aim is to give each gene the name of its human counterpart, so that a gene is easy to recognise and to compare across species. When there is no strong human match, the name comes from a well-studied protein of another species, and failing that from the protein family or domain.') ?></p>
      <p class="text-muted mb-0"><?= t('A name a curator chose, or a good name that came with the gene set, is kept as it is. Every other name is inferred by the same rules, and the form of the name shows how strong the evidence is.') ?></p>
    </div>
  </div>

  <h2 class="h5 fw-semibold mb-3"><?= t('Read the name') ?></h2>
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card h-100 shadow-sm help-topic-card"><div class="card-body p-3">
        <h3 class="h6 fw-semibold mb-1"><?= t('A human gene\'s name') ?></h3>
        <p class="small mb-1"><code>FANCE: FA complementation group E</code></p>
        <p class="small text-muted mb-0"><?= t('An ortholog of that human gene: the same gene, inherited from a common ancestor. When several genes here are orthologs of one human gene, they share its name, numbered "(1 of 3)".') ?></p>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm help-topic-card"><div class="card-body p-3">
        <h3 class="h6 fw-semibold mb-1"><?= t('A name ending in "-like"') ?></h3>
        <p class="small mb-1"><code>PARP14-like</code></p>
        <p class="small text-muted mb-0"><?= t('A homolog: the protein is similar to the named one along most of its length, but orthology is not shown. It may be a paralog, a related gene in the same family. A species in brackets means the named protein is from that species, not human.') ?></p>
      </div></div>
    </div>
    <div class="col-md-4">
      <div class="card h-100 shadow-sm help-topic-card"><div class="card-body p-3">
        <h3 class="h6 fw-semibold mb-1"><?= t('"Family member" or "domain-containing protein"') ?></h3>
        <p class="small mb-1"><code>Reverse transcriptase domain-containing protein</code></p>
        <p class="small text-muted mb-0"><?= t('The evidence stops at a protein family or a domain. It tells you what kind of protein this is, not which gene.') ?></p>
      </div></div>
    </div>
  </div>
  <p class="text-muted small mb-4"><?= t('A curated name, or one that came with the gene set, may look like any of these; the gene page says which it is, just under the gene\'s name. A gene with no name and no description had no evidence that passed these tests.') ?></p>

  <h2 class="h5 fw-semibold mb-3"><?= t('The order of the rules') ?></h2>
  <div class="card shadow-sm mb-4"><div class="card-body p-4">
    <p class="small text-muted"><?= t('The first rule that gives a name wins.') ?></p>
    <ol class="small mb-0">
      <li><?= t('A curator\'s name.') ?></li>
      <li><?= t('The name that came with the gene set (from RefSeq, Ensembl or the authors), when it says what the gene is.') ?>
          <?= t('For some species instead: the name of the same gene in a closely related species that has better names, such as Schmidtea mediterranea for other flatworms.') ?></li>
      <li><?= t('An ortholog of a human gene (OMA), when other evidence supports it.') ?></li>
      <li><?= t('A transposable-element domain.') ?></li>
      <li><?= t('The PANTHER family tree places the gene with exactly one human gene.') ?></li>
      <li><?= t('Similar to one human protein along most of its length: a "-like" name.') ?></li>
      <li><?= t('No human protein is similar along its length, but a reviewed (Swiss-Prot) protein of another species is: a "-like" name with that species in brackets.') ?></li>
      <li><?= t('A PANTHER protein family: "family member".') ?></li>
      <li><?= t('An InterPro domain: "domain-containing protein".') ?></li>
    </ol>
  </div></div>

  <h2 class="h5 fw-semibold mb-3"><?= t('How much to trust a name') ?></h2>
  <div class="card shadow-sm mb-4"><div class="card-body p-4">
    <ul class="small mb-0">
      <li><?= t_markup('%s an ortholog name (ISO) with nothing against it.', '<strong>' . t('Strong:') . '</strong>') ?></li>
      <li><?= t_markup('%s an ortholog name with one check against it (sim~, sim-, pthrC or treeC), or a "-like" name from a reciprocal best hit with no tie.', '<strong>' . t('Moderate:') . '</strong>') ?></li>
      <li><?= t_markup('%s an ortholog name with two or more checks against it; any other "-like" name; a family or domain name.', '<strong>' . t('Weak:') . '</strong>') ?></li>
    </ul>
  </div></div>

  <h2 class="h5 fw-semibold mb-3"><?= t('The tag at the end of a description') ?></h2>
  <p class="text-muted small mb-3"><?= t_markup('In downloads and in the data for AI assistants, a description ends with a short tag in square brackets, such as %s. The site\'s pages leave it out. The first code is the kind of evidence, after the Gene Ontology\'s evidence codes; the codes after it say how the call was made and what agrees with it.', $code('[ISO|1to1|sim+|pthr+|tree+|hog]')) ?></p>

  <div class="card shadow-sm mb-3"><div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm mb-0 small align-middle">
        <thead><tr>
          <th class="ps-3"><?= t('First code') ?></th>
          <th><?= t('Meaning') ?></th>
          <th class="pe-3"><?= t('Example') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($key['kinds'] as [$c, $words, $label, $text, $eg]): ?>
          <tr>
            <td class="ps-3"><?= $code($c) ?><div class="text-muted"><?= htmlspecialchars($words) ?></div></td>
            <td><strong><?= htmlspecialchars(t_plain($label)) ?></strong>. <?= htmlspecialchars(t_plain($text)) ?></td>
            <td class="pe-3"><code><?= htmlspecialchars($eg) ?></code></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div></div>

  <div class="row g-3 mb-3">
    <?php foreach (['details' => t('How the call was made'), 'support' => t('Checks on the name')] as $part => $heading): ?>
      <div class="col-lg-6">
        <div class="card h-100 shadow-sm"><div class="card-body p-0">
          <h3 class="h6 fw-semibold px-3 pt-3 mb-2"><?= $heading ?></h3>
          <?php if ($part === 'support'): ?>
            <p class="small text-muted px-3 mb-2"><?= t('Each check compares the gene with the human gene it is named after, not with whatever it matches best. The similarity (sim) and PANTHER (pthr) checks are made only on a human ortholog\'s name; the tree and OMA marks can follow any name.') ?></p>
          <?php endif; ?>
          <table class="table table-sm mb-0 small align-middle">
            <tbody>
            <?php foreach ($key[$part] as [$c, $words, $text]): ?>
              <tr>
                <td class="ps-3"><?= $code($c) ?><?php if ($words !== ''): ?><div class="text-muted"><?= htmlspecialchars($words) ?></div><?php endif; ?></td>
                <td class="pe-3"><?= htmlspecialchars(t_plain($text)) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div></div>
      </div>
    <?php endforeach; ?>
  </div>

  <p class="text-muted small mb-5">
    <?= t('The last character of a check:') ?>
    <?php foreach ($key['marks'] as $i => [$m, $words]): ?><?= $i ? ' · ' : ' ' ?><?= $code($m) ?> <?= htmlspecialchars(t_plain($words)) ?><?php endforeach; ?>
  </p>

</div>

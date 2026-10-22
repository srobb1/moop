<?php
/**
 * How the data is organized — the one concept that spans every page.
 *
 * Each level's definition is read from the glossary (glossary_terms()), the same text the
 * dashed-underlined words show, so this page cannot say something different from them.
 * The worked example and the counts are the VIEWER's own accessible data, so a public
 * visitor is never shown an organism they cannot open.
 *
 * Available: $config, $siteTitle
 */

$g = glossary_terms();
$def = fn(string $term): string => $g[strtolower($term)] ?? '';

// The viewer's gene sets, de-duplicated (a gene set appears once per group it is in).
$mine = [];
foreach (getAccessibleGeneSets() as $orgs) {
    foreach ($orgs as $rows) {
        foreach ($rows as $r) {
            $mine[$r['organism'] . '/' . $r['assembly'] . '/' . $r['gene_set']] = $r;
        }
    }
}
ksort($mine);
$n_org = count(array_unique(array_column($mine, 'organism')));
$n_asm = count(array_unique(array_map(fn($r) => $r['organism'] . '/' . $r['assembly'], $mine)));
$n_gs  = count($mine);
$no_genome = 0;
$example = null;
foreach ($mine as $r) {
    $has_gff = is_file($r['path'] . '/' . genes_gff_filename());
    if (!$has_gff) $no_genome++;
    if ($example === null && $has_gff) $example = $r;
}

$levels = [
    ['num' => 1, 'label' => 'Organism', 'icon' => 'fa-paw',
     'text' => 'A species — the starting point on the home page.',
     'eg' => $example ? str_replace('_', ' ', $example['organism']) : '', 'italic' => true],
    ['num' => 2, 'label' => 'Assembly', 'icon' => 'fa-dna',
     'text' => $def('assembly'), 'eg' => $example['assembly'] ?? ''],
    ['num' => 3, 'label' => 'Gene set', 'icon' => 'fa-layer-group',
     'text' => $def('gene set'), 'eg' => $example['gene_set'] ?? ''],
    ['num' => 4, 'label' => 'Gene', 'icon' => 'fa-stream', 'text' => $def('gene')],
    ['num' => 5, 'label' => 'mRNA (transcript)', 'icon' => 'fa-align-left', 'text' => $def('mRNA')],
    ['num' => 6, 'label' => 'Protein', 'icon' => 'fa-cube', 'text' => $def('protein')],
];
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-sitemap me-2"></i>How the data is organized
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Everything in <?= htmlspecialchars($siteTitle) ?> sits in one chain, from a species down to a
        single protein. Each level belongs to the one above it.
        <?php if ($n_gs): ?>
          You can currently see <strong><?= $n_org ?></strong> organism<?= $n_org === 1 ? '' : 's' ?>,
          <strong><?= $n_asm ?></strong> assembl<?= $n_asm === 1 ? 'y' : 'ies' ?> and
          <strong><?= $n_gs ?></strong> gene set<?= $n_gs === 1 ? '' : 's' ?>.
        <?php endif; ?>
      </p>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <?php foreach ($levels as $lv): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h2 class="h6 fw-semibold mb-2 d-flex align-items-center gap-2">
              <span class="step-badge"><?= $lv['num'] ?></span>
              <i class="fa <?= $lv['icon'] ?>" style="color:#0891b2;" aria-hidden="true"></i>
              <?= htmlspecialchars($lv['label']) ?>
            </h2>
            <p class="text-muted small mb-0"><?= htmlspecialchars($lv['text']) ?></p>
            <?php if (!empty($lv['eg'])): ?>
              <p class="small mb-0 mt-2">
                <span class="text-muted">e.g.</span>
                <?= !empty($lv['italic']) ? '<em>' . htmlspecialchars($lv['eg']) . '</em>'
                                           : '<code>' . htmlspecialchars($lv['eg']) . '</code>' ?>
              </p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="h5 fw-semibold mb-3">What hangs off the chain</h2>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-2"><i class="fa fa-tags me-2" style="color:#0891b2;"></i>Annotations</h3>
          <p class="text-muted small mb-2"><?= htmlspecialchars($def('annotation')) ?></p>
          <p class="text-muted small mb-0">
            An annotation belongs to one transcript, so a gene with several transcripts can carry
            different annotations on each. The gene page lists them transcript by transcript.
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-2"><i class="fa fa-filter me-2" style="color:#0891b2;"></i>Annotation types</h3>
          <p class="text-muted small mb-2"><?= htmlspecialchars($def('annotation type')) ?></p>
          <p class="text-muted small mb-0">
            Where each type comes from is listed under
            <a href="?topic=data-sources">Where the data comes from</a>.
          </p>
        </div>
      </div>
    </div>
    <?php if ($no_genome): ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-2"><i class="fa fa-map-marker-alt me-2" style="color:#0891b2;"></i>Gene sets without genome positions</h3>
          <p class="text-muted small mb-0">
            <?= $no_genome ?> of the gene sets you can see <?= $no_genome === 1 ? 'is' : 'are' ?>
            sequences only, with no positions on a genome. Their genes are searchable and have
            sequences and annotations, but they have no gene structure and do not appear in the genome browser.
          </p>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-2"><i class="fa fa-book-open me-2" style="color:#0891b2;"></i>More terms</h3>
          <p class="text-muted small mb-0">
            CDS, GO term, Pfam and the rest are in the <a href="?topic=glossary">Glossary</a>.
          </p>
        </div>
      </div>
    </div>
  </div>

</div>

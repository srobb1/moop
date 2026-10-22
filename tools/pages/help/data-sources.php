<?php
/**
 * Where the data comes from — generated from the data itself.
 *
 * Gene set origins come from each gene set's geneset.json `source`; annotation sources
 * from the per-organism annotation-sources cache (the same list the search and export
 * filters show) and the type colours/order from annotation_config.json. Nothing here is
 * written by hand, so a reload or a new organism updates the page by itself.
 *
 * Scoped to the VIEWER's accessible gene sets. The sources cache is per ORGANISM, so for an
 * organism with both public and restricted gene sets a public visitor sees the source NAMES
 * of the whole organism — names of tools and databases, not of restricted data.
 *
 * Available: $config, $siteTitle
 */

$mine = [];
foreach (getAccessibleGeneSets() as $orgs) {
    foreach ($orgs as $rows) {
        foreach ($rows as $r) {
            $mine[$r['organism'] . '/' . $r['assembly'] . '/' . $r['gene_set']] = $r;
        }
    }
}

// Gene sets by origin.
$origin_label = ['RefSeq' => 'NCBI RefSeq', 'GenBank' => 'NCBI GenBank', 'Ensembl' => 'Ensembl', 'other' => 'Other sources'];
$by_origin = [];
foreach ($mine as $r) {
    $src = loadJsonFile($r['path'] . '/geneset.json', [])['source'] ?? '';
    $src = $src !== '' ? $src : 'other';
    $by_origin[$src] = ($by_origin[$src] ?? 0) + 1;
}
uksort($by_origin, fn($a, $b) => ($a === 'other') <=> ($b === 'other') ?: $by_origin[$b] <=> $by_origin[$a]);

// Annotation sources: type => [source name => number of organisms carrying it].
$by_type = [];
foreach (array_unique(array_column($mine, 'organism')) as $org) {
    // The cache can list one name more than once for an organism (once per gene set),
    // so collect this organism's names first: the count is ORGANISMS, not entries.
    $seen = [];
    foreach (moop_load_annotation_sources_cache($org) ?? [] as $type => $sources) {
        foreach ($sources as $s) {
            $seen[$type][$s['name']] = true;
        }
    }
    foreach ($seen as $type => $names) {
        foreach ($names as $name => $_) {
            $by_type[$type][$name] = ($by_type[$type][$name] ?? 0) + 1;
        }
    }
}
$ann_cfg = loadJsonFile($config->getPath('metadata_path') . '/annotation_config.json', []);
$types   = $ann_cfg['annotation_types'] ?? [];
uksort($by_type, fn($a, $b) => ($types[$a]['order'] ?? 999) <=> ($types[$b]['order'] ?? 999) ?: strcmp($a, $b));
foreach ($by_type as &$names) { arsort($names); }
unset($names);
$n_org = count(array_unique(array_column($mine, 'organism')));
?>

<div class="container mt-5">

  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center tool-header">
      <h1 class="text-uppercase fw-semibold mb-0 d-inline section-eyebrow">
        <i class="fa fa-database me-2"></i>Where the data comes from
      </h1>
    </div>
    <div class="card-body p-4">
      <p class="text-muted mb-0">
        Genomes and gene sets are imported from public databases or from the groups that
        produced them. Most annotations are added afterwards, by analysis tools run over the
        proteins and transcripts.
        This page is built from the data you can see, so it is always current.
      </p>
    </div>
  </div>

  <?php if (!$mine): ?>
    <div class="alert alert-warning">There is no data you can see yet.</div>
  <?php else: ?>

  <h2 class="h5 fw-semibold mb-3">Gene sets</h2>
  <div class="row g-3 mb-4">
    <?php foreach ($by_origin as $src => $n): ?>
      <div class="col-6 col-md-3">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <div class="h4 fw-semibold mb-0" style="color:#0891b2;"><?= $n ?></div>
            <div class="small text-muted"><?= htmlspecialchars($origin_label[$src] ?? $src) ?></div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="text-muted small mb-4">
    Each gene set page names its own source; each assembly page gives the assembly's accession.
  </p>

  <h2 class="h5 fw-semibold mb-3">Annotations</h2>
  <p class="text-muted small mb-3">
    Grouped by <?= gloss('annotation type') ?>. The number beside a source is how many of your
    <?= $n_org ?> organism<?= $n_org === 1 ? '' : 's' ?> carry it.
  </p>
  <div class="row g-3 mb-4">
    <?php foreach ($by_type as $type => $names): ?>
      <div class="col-md-6 col-lg-4">
        <div class="card h-100 shadow-sm help-topic-card">
          <div class="card-body p-3">
            <h3 class="h6 mb-2">
              <span class="badge bg-<?= htmlspecialchars($types[$type]['color'] ?? 'secondary') ?>"><?= htmlspecialchars($type) ?></span>
            </h3>
            <?php /* The most widespread few, the rest one click away — Homologs alone lists
                     ~17 Ensembl species, which is a wall, not a summary. */
                  $shown = array_slice($names, 0, 6, true);
                  $rest  = array_slice($names, 6, null, true); ?>
            <?php foreach ([$shown, $rest] as $i => $part): if (!$part) continue; ?>
              <?php if ($i === 1): ?>
                <details class="small mt-1"><summary class="text-muted"><?= count($rest) ?> more</summary>
              <?php endif; ?>
              <ul class="list-unstyled small mb-0">
                <?php foreach ($part as $name => $n): ?>
                  <li class="d-flex justify-content-between gap-2">
                    <span><?= gloss_terms_in($name) ?></span>
                    <span class="text-muted"><?= $n ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
              <?php if ($i === 1): ?></details><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <h2 class="h5 fw-semibold mb-3">Citing what you use</h2>
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1">Genomes and gene sets</h3>
          <p class="text-muted small mb-0">
            Cite the original assembly by its accession and the gene set by its source, both shown
            on the assembly and gene set pages.
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1">Annotations</h3>
          <p class="text-muted small mb-0">
            Cite the tool or database named beside the annotation on the gene page — for example
            InterProScan for a Pfam domain.
          </p>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm help-topic-card">
        <div class="card-body p-3">
          <h3 class="h6 fw-semibold mb-1">This site</h3>
          <p class="text-muted small mb-0">
            <?= htmlspecialchars($siteTitle) ?> runs on MOOP. To cite it, cite the
            <a href="https://github.com/srobb1/moop" target="_blank" rel="noopener">MOOP GitHub repository</a>.
          </p>
        </div>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

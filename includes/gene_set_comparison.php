<?php
/**
 * Gene set comparison -- the organism page, under About. One row per assembly + gene set the
 * viewer can open (moop_gene_set_comparison_rows(), lib/gene_set_summary.php).
 *
 * Expects: $gene_set_rows, $organism_name, $site.
 *
 * No "best" badge: which gene set is best depends on the question. Instead the highest value in
 * each quality column is marked when there is more than one row, and the table sorts on any
 * column (js/gene-set-comparison.js). BUSCO is shown against eukaryota_odb10 -- the one lineage
 * every gene set on the site is run against, so the bars compare across rows and organisms.
 * Eukaryota ONLY here (user, 2026-10-20): best-fit lineages can differ between rows, and two
 * numbers per cell made the table harder to read. The gene set page shows every lineage.
 */
$gsc_many = count($gene_set_rows) > 1;
$gsc_pct  = fn(float $v): string => number_format($v, 1) . '%';

// Share with a homolog or ortholog (moop_homology_types()): of genes when the gene set has gene
// features, else of transcripts (a few gene sets are transcript-only). null counts = the database
// has no homolog-like annotation type at all -> unknown, shown as a dash.
$gsc_annot = function (?array $c): ?array {
    if (!$c || $c['homology_genes'] === null) return null;
    if ($c['genes'] > 0) return ['pct' => 100 * $c['homology_genes'] / $c['genes'], 'of' => 'genes'];
    if ($c['transcripts'] > 0) return ['pct' => 100 * $c['homology_transcripts'] / $c['transcripts'], 'of' => 'transcripts'];
    return null;
};

// The top value per quality column, marked only when there is something to compare.
$gsc_top = ['busco' => null, 'annot' => null, 'n50' => null];
if ($gsc_many) {
    foreach ($gene_set_rows as $r) {
        if ($r['busco_euk']) $gsc_top['busco'] = max($gsc_top['busco'] ?? 0, $r['busco_euk']['complete']);
        if ($a = $gsc_annot($r['counts'])) $gsc_top['annot'] = max($gsc_top['annot'] ?? 0, round($a['pct'], 1));
        if ($r['assembly_stats']) $gsc_top['n50'] = max($gsc_top['n50'] ?? 0, $r['assembly_stats']['n50']);
    }
}
$gsc_mark = '<i class="fa fa-arrow-up gsc-top-icon" title="Highest of these" aria-label="highest of these"></i>';
?>
<div class="row mb-4" id="geneSetComparison">
  <div class="col-12">
    <div class="feature-header organism-header-custom shadow">
      <h2>
        <?= $gsc_many ? 'Compare assemblies &amp; gene sets' : 'Assembly &amp; gene set at a glance' ?>
        <?= field_help(
            'Each row is one gene set on one assembly. BUSCO: how many genes that almost every '
            . 'eukaryote carries were found complete (higher is better). It is measured against '
            . 'the eukaryota set for every row, so it compares across rows and organisms; the gene '
            . 'set page also shows the more specific lineages. With homolog: the share of genes with '
            . 'a homolog or ortholog in another species -- genes that resemble something known. '
            . 'Spurious gene predictions rarely do, so a low share can mean many of them. N50: half the assembly is in sequences at least this '
            . 'long -- higher means a more contiguous genome. The arrow marks the highest value in a '
            . 'column; click a column heading to sort.',
            'Comparing gene sets'
        ) ?>
      </h2>
      <div class="feature-overview-body">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0 gsc-table" id="geneSetComparisonTable">
            <thead>
              <tr>
                <th>Gene set</th>
                <th>Source</th>
                <th class="text-end">Genes</th>
                <th class="text-end">With homolog</th>
                <th>BUSCO <span class="fw-normal text-muted">(eukaryota)</span></th>
                <th class="text-end">Assembly N50</th>
                <th>Added</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($gene_set_rows as $r):
              $c    = $r['counts'];
              $a    = $gsc_annot($c);
              $euk  = $r['busco_euk'];
              $st   = $r['assembly_stats'];
              $gs_url  = "/$site/tools/gene_set.php?organism=" . urlencode($organism_name)
                       . '&assembly=' . urlencode($r['assembly']) . '&gene_set=' . urlencode($r['gene_set']);
              $asm_url = "/$site/tools/assembly.php?organism=" . urlencode($organism_name)
                       . '&assembly=' . urlencode($r['assembly']);
            ?>
              <tr>
                <td data-order="<?= htmlspecialchars($r['gene_set']) ?>">
                  <a href="<?= htmlspecialchars($gs_url) ?>" class="fw-semibold"><?= htmlspecialchars($r['gene_set']) ?></a>
                  <?php if ($r['warnings']): ?>
                  <i class="fa fa-exclamation-triangle text-warning ms-1"
                     title="<?= htmlspecialchars(implode("\n", $r['warnings'])) ?>"></i>
                  <span class="visually-hidden"><?= htmlspecialchars(implode(' ', $r['warnings'])) ?></span>
                  <?php endif; ?>
                  <div class="small"><a href="<?= htmlspecialchars($asm_url) ?>" class="text-muted"><?= htmlspecialchars($r['assembly_label']) ?></a></div>
                </td>
                <td><?= $r['source'] !== '' ? htmlspecialchars($r['source']) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-end" data-order="<?= $c ? $c['genes'] : -1 ?>">
                  <?php if ($c): ?>
                    <?= number_format($c['genes']) ?>
                    <div class="small text-muted"><?= number_format($c['transcripts']) ?> transcripts</div>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-end" data-order="<?= $a ? round($a['pct'], 1) : -1 ?>">
                  <?php if ($a): ?>
                    <?= $gsc_pct($a['pct']) ?><?= ($gsc_top['annot'] !== null && round($a['pct'], 1) >= $gsc_top['annot']) ? $gsc_mark : '' ?>
                    <div class="small text-muted">of <?= $a['of'] ?></div>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td data-order="<?= $euk ? $euk['complete'] : -1 ?>" class="gsc-busco">
                  <?php if ($euk): ?>
                    <div class="d-flex align-items-center gap-2">
                      <div class="busco-bar gsc-busco-bar" role="img"
                           aria-label="<?= htmlspecialchars('eukaryota: ' . moop_busco_notation($euk)) ?>"
                           title="<?= htmlspecialchars(moop_busco_notation($euk)) ?>">
                        <?php foreach (['single', 'duplicated', 'fragmented', 'missing'] as $k):
                          if ($euk[$k] <= 0) continue; ?>
                        <div class="busco-seg busco-seg-<?= $k ?>" style="flex-grow:<?= $euk[$k] ?>;"></div>
                        <?php endforeach; ?>
                      </div>
                      <span class="text-nowrap"><strong><?= $gsc_pct($euk['complete']) ?></strong><?= ($gsc_top['busco'] !== null && $euk['complete'] >= $gsc_top['busco']) ? $gsc_mark : '' ?></span>
                    </div>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-end" data-order="<?= $st ? $st['n50'] : -1 ?>">
                  <?php if ($st): ?>
                    <?= moop_format_bp($st['n50']) ?><?= ($gsc_top['n50'] !== null && $st['n50'] >= $gsc_top['n50']) ? $gsc_mark : '' ?>
                    <div class="small text-muted"><?= number_format($st['sequences']) ?> seqs &middot; <?= moop_format_bp($st['total_length']) ?></div>
                  <?php else: ?><span class="text-muted">—</span><?php endif; ?>
                </td>
                <td class="text-nowrap" data-order="<?= htmlspecialchars($r['date_added']) ?>">
                  <?= $r['date_added'] !== '' ? htmlspecialchars($r['date_added']) : '<span class="text-muted">—</span>' ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="small text-muted mb-0 mt-2">
          A dash means the figure is not available yet — BUSCO results arrive with each gene set's
          pipeline run, and the counts are refreshed in the background after the data changes.
        </p>
      </div>
    </div>
  </div>
</div>

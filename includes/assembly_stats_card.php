<?php
/**
 * Assembly statistics card -- the assembly page. The organism page's comparison table shows a
 * condensed form of the same figures; this is all of them.
 *
 * Expects $assembly_stats from moop_assembly_stats_or_fallback() (lib/gene_set_summary.php):
 * the pipeline's assembly_stats.json (notes/ASSEMBLY_STATS_SPEC.md) when there is one, else the
 * scaffold figures derived from the .fai. A figure that is absent is simply not shown.
 *
 * Laid out to be read at a glance (user, 2026-10-20: nine label/value pairs in two columns were
 * "hard on the eyes" until you knew what you were looking at): up to four headline numbers, one
 * plain sentence that says what N50 means for THIS genome, and everything else grouped and
 * collapsed beneath.
 */
$as = $assembly_stats;
// One assembled transcript per gene, not a genome (tools/assembly.php, moop_genome_kind()): the
// same figures, named for what they measure.
$as_tx = ($genome_kind ?? '') === 'transcripts';

// Headline tiles: [value, label, context line]. Contig and GC only when the pipeline file has them.
$as_tiles = [
    [moop_format_bp($as['total_length']), $as_tx ? 'Total transcript length' : 'Genome size',
     number_format($as['sequences']) . ($as_tx ? ' transcript' : ' sequence') . ($as['sequences'] == 1 ? '' : 's')],
    [moop_format_bp($as['n50']), $as_tx ? 'Transcript N50' : 'Scaffold N50', 'L50 ' . number_format($as['l50'])],
];
// Not for transcripts: with no gaps to split at, "contigs" are the transcripts again.
if (isset($as['contig_n50']) && !$as_tx) {
    $as_tiles[] = [moop_format_bp($as['contig_n50']), 'Contig N50',
                   isset($as['contigs']) ? number_format($as['contigs']) . ' contigs' : ''];
}
if (isset($as['gc_pct'])) {
    $as_tiles[] = [number_format($as['gc_pct'], 1) . '%', 'GC',
                   isset($as['gap_pct']) ? 'gaps ' . moop_format_small_pct($as['gap_pct']) : ''];
}

// The rest, grouped. [label, value or null]; absent figures are dropped, then empty groups.
$as_groups = [
    'Size' => [
        ['Sequences', number_format($as['sequences'])],
        ['Total length', moop_format_bp($as['total_length']) . ' (' . number_format($as['total_length']) . ' bp)'],
        ['Longest sequence', isset($as['longest']) ? moop_format_bp($as['longest']) : null],
    ],
    'Contiguity' => [
        ['N50', moop_format_bp($as['n50'])],
        ['L50', number_format($as['l50']) . ' sequences'],
        ['N90', isset($as['n90']) ? moop_format_bp($as['n90']) : null],
        ['L90', isset($as['l90']) ? number_format($as['l90']) . ' sequences' : null],
        ['Contigs', isset($as['contigs']) ? number_format($as['contigs']) : null],
        ['Contig N50', isset($as['contig_n50']) ? moop_format_bp($as['contig_n50']) : null],
        ['Contig L50', isset($as['contig_l50']) ? number_format($as['contig_l50']) . ' contigs' : null],
    ],
    'Composition' => [
        ['Gaps (N)', isset($as['gap_bases'], $as['gap_pct']) ? number_format($as['gap_bases']) . ' bp (' . moop_format_small_pct($as['gap_pct']) . ')' : null],
        ['GC content', isset($as['gc_pct']) ? number_format($as['gc_pct'], 1) . '%' : null],
    ],
];
$as_group_help = [
    'Size'        => 'Sequences are the records in the genome FASTA: chromosomes, scaffolds or contigs, as the assembly was deposited. Total length includes gaps (Ns).',
    'Contiguity'  => 'N50: half the assembly is in sequences at least this long; L50: how many of them it takes. N90/L90 are the same at 90%. Contigs are the sequences split at every gap of 10 or more Ns -- the pieces actually assembled before scaffolding joined them -- so contig N50 cannot be inflated by scaffolding.',
    'Composition' => 'Gaps: bases that are N, unknown sequence, mostly between joined contigs. GC: G + C as a share of the A, C, G and T bases.',
];
foreach ($as_groups as $name => $rows) {
    $as_groups[$name] = array_values(array_filter($rows, fn($r) => $r[1] !== null));
    if (!$as_groups[$name]) unset($as_groups[$name]);
}
?>
<div class="row mb-4" id="assemblyStats">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header d-flex align-items-center" style="background-color:#d97706;">
        <i class="fas fa-ruler-horizontal me-2 text-white"></i>
        <span class="text-uppercase fw-semibold text-white section-eyebrow"><?= t('Assembly Statistics') ?></span>
        <?php if ($as_tx): ?><span class="badge bg-white text-dark ms-2"><?= t('Transcripts, not a genome') ?></span><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($as_tx): ?>
        <p class="small mb-3">
          <i class="fa fa-info-circle me-1" aria-hidden="true"></i>
          These figures describe <strong>transcripts</strong>, one per gene &mdash; this assembly is not a
          genome. N50 here is a transcript length, so it cannot be compared with a genome's scaffold N50.
        </p>
        <?php endif; ?>
        <div class="as-tiles">
          <?php foreach ($as_tiles as [$value, $label, $context]): ?>
          <div class="as-tile">
            <div class="as-tile-value"><?= htmlspecialchars($value) ?></div>
            <div class="as-tile-label"><?= htmlspecialchars($label) ?></div>
            <?php if ($context !== ''): ?><div class="as-tile-context"><?= htmlspecialchars($context) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if ($as_tx): ?>
        <p class="as-sentence mb-0">
          <?= t_markup('Half the bases are in %1$s of at least %2$s.',
                       '<strong>' . ($as['l50'] == 1 ? t('%s transcript', number_format($as['l50'])) : t('%s transcripts', number_format($as['l50']))) . '</strong>',
                       '<strong>' . htmlspecialchars(moop_format_bp($as['n50'])) . '</strong>') . "\n" ?>
        </p>
        <?php else: ?>
        <p class="as-sentence mb-0">
<?php $as_l50 = '<strong>' . ($as['l50'] == 1 ? t('%s sequence', number_format($as['l50'])) : t('%s sequences', number_format($as['l50']))) . '</strong>';
      $as_n50 = '<strong>' . htmlspecialchars(moop_format_bp($as['n50'])) . '</strong>'; ?>
          <?= (isset($as['contig_n50'])
              ? t_markup('Half the genome is in %1$s of at least %2$s; without scaffolding (split at gaps) the pieces reach %3$s.',
                         $as_l50, $as_n50, '<strong>' . htmlspecialchars(moop_format_bp($as['contig_n50'])) . '</strong>')
              : t_markup('Half the genome is in %1$s of at least %2$s.', $as_l50, $as_n50)) . "\n" ?>
        </p>
        <?php endif; ?>

        <a class="small d-inline-block mt-2" data-bs-toggle="collapse" href="#assemblyStatsMore" role="button"
           aria-expanded="false" aria-controls="assemblyStatsMore">
          <?= t('More statistics') ?> <i class="fa fa-chevron-down small"></i>
        </a>
        <div class="collapse" id="assemblyStatsMore">
          <div class="row mt-2">
            <?php foreach ($as_groups as $name => $rows): ?>
            <div class="col-md-4 mb-2">
              <div class="as-group-title"><?= htmlspecialchars($name) ?> <?= field_help($as_group_help[$name], $name) ?></div>
              <dl class="as-group mb-0">
                <?php foreach ($rows as [$label, $value]): ?>
                <dt><?= htmlspecialchars($label) ?></dt><dd><?= htmlspecialchars($value) ?></dd>
                <?php endforeach; ?>
              </dl>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <?php if (empty($as['from_pipeline'])): ?>
        <p class="small text-muted mb-0 mt-2">
          Computed from the genome index. <?= gloss('contig', 'Contig') ?>, gap and GC figures appear once the annotation
          pipeline has produced this assembly's statistics.
        </p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

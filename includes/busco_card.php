<?php
/**
 * BUSCO completeness card -- shared by the gene set page and the assembly page.
 *
 * Expects, from the including page:
 *   $busco          moop_load_busco() result (non-null; the caller skips the include otherwise)
 *   $busco_subject  'gene set' | 'assembly' -- used in the plain-words text
 *   $busco_color    the page's section colour, for the card header
 *
 * One 100% bar per lineage run: single | duplicated | fragmented | missing, always summing to
 * 100. Segments are sized by flex-grow, not width%, so the 2px gaps between them are taken from
 * every segment in proportion instead of clipping the last one (missing) off the end. Every value is also printed beside the bar, so nothing depends on colour or on hover;
 * the segment colours were run through the dataviz palette validator (CVD-safe adjacent pairs).
 */
$busco_subject = $busco_subject ?? 'gene set';
$busco_color   = $busco_color ?? '#e11d48';
$busco_pct     = fn(float $v): string => number_format($v, 1) . '%';
$busco_segments = [
    // key, label, css class
    ['single',     'Complete, single copy', 'busco-seg-single'],
    ['duplicated', 'Complete, duplicated',  'busco-seg-duplicated'],
    ['fragmented', 'Fragmented',            'busco-seg-fragmented'],
    ['missing',    'Missing',               'busco-seg-missing'],
];
?>
<div class="row mb-4" id="buscoSummary">
  <div class="col-12">
    <div class="card shadow-sm">
      <div class="card-header d-flex align-items-center" style="background-color:<?= htmlspecialchars($busco_color) ?>;">
        <i class="fas fa-check-double me-2 text-white"></i>
        <span class="text-uppercase fw-semibold text-white section-eyebrow"><?= htmlspecialchars(ucwords($busco_subject)) ?> Completeness (BUSCO)</span>
        <span class="ms-2"><?= field_help(
            'BUSCO looks for a set of genes that almost every species in a lineage carries, '
            . 'once each. Complete means the gene was found full-length -- as a single copy, '
            . 'or duplicated. Fragmented means only part of it was found; missing, none of it. '
            . 'A high complete score with little missing suggests this ' . $busco_subject
            . ' captures most genes. Duplicated can be real gene duplication, but is also raised '
            . 'when several isoforms of one gene are each counted, or when part of the genome '
            . 'was assembled twice. Each bar uses a different reference lineage: the best-fit '
            . 'one is the most specific BUSCO placed this ' . $busco_subject . ' in; the broadest '
            . '(eukaryota) can be compared across every organism on this site.',
            'BUSCO completeness'
        ) ?></span>
      </div>
      <div class="card-body">
        <?php foreach ($busco['runs'] as $run):
          $role_label = MOOP_BUSCO_ROLES[$run['role']] ?? ($run['role'] !== '' ? ucfirst($run['role']) : '');
          $aria = $run['lineage'] . ': ' . $busco_pct($run['complete']) . ' complete ('
                . $busco_pct($run['single']) . ' single, ' . $busco_pct($run['duplicated']) . ' duplicated), '
                . $busco_pct($run['fragmented']) . ' fragmented, ' . $busco_pct($run['missing'])
                . ' missing, of ' . number_format($run['n']) . ' BUSCO genes';
        ?>
        <div class="busco-run">
          <div class="d-flex flex-wrap align-items-baseline justify-content-between gap-2 mb-1">
            <div>
              <span class="fw-semibold"><?= htmlspecialchars($run['lineage']) ?></span>
              <?php if ($role_label !== ''): ?>
              <span class="badge busco-role ms-1"><?= htmlspecialchars($role_label) ?></span>
              <?php endif; ?>
            </div>
            <div class="small text-muted">
              <strong class="text-body"><?= $busco_pct($run['complete']) ?></strong> complete
              of <?= number_format($run['n']) ?> genes
              <?php if ($run['version'] !== ''): ?>&middot; BUSCO <?= htmlspecialchars($run['version']) ?><?php endif; ?>
            </div>
          </div>
          <div class="busco-bar" role="img" aria-label="<?= htmlspecialchars($aria) ?>">
            <?php foreach ($busco_segments as [$key, $label, $cls]):
              if ($run[$key] <= 0) continue; ?>
            <div class="busco-seg <?= $cls ?>" style="flex-grow:<?= max(0.0, min(100.0, $run[$key])) ?>;"
                 title="<?= htmlspecialchars($label . ': ' . $busco_pct($run[$key]) . ' (' . number_format($run[$key . '_n']) . ' of ' . number_format($run['n']) . ')') ?>"></div>
            <?php endforeach; ?>
          </div>
          <div class="busco-legend small">
            <?php foreach ($busco_segments as [$key, $label, $cls]): ?>
            <span class="busco-legend-item">
              <span class="busco-swatch <?= $cls ?>"></span><?= htmlspecialchars($label) ?>
              <strong><?= $busco_pct($run[$key]) ?></strong>
              <span class="text-muted">(<?= number_format($run[$key . '_n']) ?>)</span>
            </span>
            <?php endforeach; ?>
          </div>
          <div class="busco-meta small text-muted">
            <code class="busco-notation" title="BUSCO's standard notation"><?= htmlspecialchars(moop_busco_notation($run)) ?></code>
            <?php if ($run['date'] !== ''): ?>
            <span>Results dated <?= htmlspecialchars($run['date']) ?></span>
            <?php endif; ?>
            <?php if ($run['dataset_date'] !== ''): ?>
            <span>&middot; lineage dataset <?= htmlspecialchars($run['dataset_date']) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($busco['warnings'])): ?>
        <div class="alert alert-warning small mb-0 mt-3" role="note">
          <i class="fa fa-exclamation-triangle me-1"></i>
          <strong>These results may not match the current gene models.</strong>
          <ul class="mb-0 mt-1">
            <?php foreach ($busco['warnings'] as $w): ?>
            <li><?= htmlspecialchars($w) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

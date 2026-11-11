<?php
/**
 * PAGE results table -- shared by every way into PAGE search (by anatomy term, by publication).
 * Included from tools/pages/anatomy_search.php. Variables: $result (rows), $genes (crosswalk links),
 * $L (id -> name). Evidence, life stage and specimen show the term's icon when the site has one
 * (moop_term_icon(), images/term_icons/).
 */
?>
      <?php if ($result['rows']): ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle" id="pageResults" style="width:100%">
          <thead><tr>
            <th><?= t('Transcript') ?></th><th><?= t('Gene') ?></th><th><?= t('Expressed in') ?></th><th><?= t('Evidence') ?></th>
            <th><?= t('Life stage') ?></th><th><?= t('Specimen') ?></th><th><?= t('Paper') ?></th><th><?= t('Curated') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r): ?>
            <tr>
              <td class="font-monospace small"><?= htmlspecialchars($r['accession']) ?></td>
              <?php $g = $genes[mb_strtolower($r['accession'])] ?? null; ?>
              <td class="font-monospace small"<?= $g && count($g['all']) > 1 ? ' title="' . htmlspecialchars(implode(' ', $g['all'])) . '"' : '' ?>>
                <?php if (!$g): ?><span class="text-muted">&ndash;</span><?php else: foreach (array_slice($g['show'], 0, 2) as $i => $x): ?><?= $i ? ' ' : '' ?><a href="<?= htmlspecialchars($x['url']) ?>"<?= $x['external'] ? ' target="_blank" rel="noopener"' : '' ?>><?= htmlspecialchars($x['id']) ?></a><?php endforeach; ?><?= count($g['show']) > 2 ? ' <span class="text-muted">+' . (count($g['show']) - 2) . '</span>' : '' ?><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($L($r['term_id'])) ?> <span class="text-muted small font-monospace"><?= htmlspecialchars($r['term_id']) ?></span></td>
              <?php foreach (['evidence_id', 'stage_id', 'specimen_id'] as $col): $v = (string) ($r[$col] ?? ''); ?>
              <td class="small"><?= $v === '' ? '' : moop_term_icon($v, $L($v)) . htmlspecialchars($L($v)) ?></td>
              <?php endforeach; ?>
              <td class="small"><a href="https://pubmed.ncbi.nlm.nih.gov/<?= rawurlencode((string) $r['pmid']) ?>/" target="_blank" rel="noopener" title="<?= htmlspecialchars((string) $r['title']) ?>"><?= htmlspecialchars(trim(($r['authors'] ?: 'PMID ' . $r['pmid']) . ' ' . $r['year'])) ?></a></td>
              <td class="small text-nowrap"><?= htmlspecialchars((string) $r['curated_on']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

<?php
/**
 * KEGG pathways table across several gene sets (lib/kegg.php moop_kegg_merge_gene_sets()): the organism page
 * and the assembly page. The caller sets $kegg_tbl = ['data' => moop_kegg_organism_pathways(...),
 * 'id' => table id, 'scope' => ['organism' => ..., 'assembly' => ...?]] -- 'scope' is what the Map link and
 * the Genes search are limited to. Sorting and the class filter: js/modules/kegg-pathways-table.js.
 * Genes are per gene set, never summed (gene sets usually annotate the same genome).
 */
$kid = htmlspecialchars($kegg_tbl['id']);
?>
<div class="small text-muted mb-2">
  <?= t('"Map" opens KEGG\'s map with the boxes coloured:') ?>
  <?= moop_kegg_map_legend_html([isset($kegg_tbl['scope']['assembly']) ? 'in_assembly' : 'in_organism']) ?>
  <?php if (!$kegg_tbl['data']['has_reference']): ?>
    <br><?= t('Coverage is not shown: this site has no copy of the KEGG pathway reference.') ?>
  <?php elseif ($kegg_tbl['data']['reference_date'] !== ''): ?>
    <br><?= t('KEGG pathway reference of %s.', htmlspecialchars($kegg_tbl['data']['reference_date'])) ?>
  <?php endif; ?>
</div>
<div class="mb-2">
  <label for="<?= $kid ?>Filter" class="small text-muted me-1"><?= t('Class:') ?></label>
  <?= '<select id="' . $kid . 'Filter" data-kegg-filter="' . $kid . '" class="form-select form-select-sm d-inline-block w-auto">' ?>
    <option value=""><?= t('All classes') ?></option>
    <?php foreach (array_keys(moop_kegg_sort_classes(array_fill_keys(array_unique(array_column($kegg_tbl['data']['rows'], 'class')), 1))) as $c): ?>
    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
    <?php endforeach; ?>
  </select>
</div>
<div class="table-responsive">
<table id="<?= $kid ?>" class="table table-sm table-striped table-hover kegg-pathways-dt" style="width:100%;">
  <thead><tr>
    <th class="kegg-class-col"><?= t('Class') ?></th>
    <th><?= t('Pathway') ?></th>
    <?php foreach ($kegg_tbl['data']['gene_sets'] as $gsk): [$__a, $__g] = explode('/', $gsk, 2); ?>
    <th class="text-end" title="<?= htmlspecialchars($gsk) ?>"><?= t('Genes in %s', htmlspecialchars($__g)) ?></th>
    <?php endforeach; ?>
    <th class="text-end kegg-sort-col"><?= t('KOs found') ?></th>
    <?php if ($kegg_tbl['data']['has_reference']): ?>
    <th class="text-end"><?= t('KOs in pathway') ?></th>
    <th class="text-end"><?= t('Coverage') ?></th>
    <?php endif; ?>
    <th class="no-sort"><?= t('Links') ?></th>
  </tr></thead>
  <tbody>
  <?php foreach ($kegg_tbl['data']['rows'] as $r):
      $found = count($r['kos_found']);
      $cov   = $r['kos_total'] ? $found / $r['kos_total'] : null;
      $map   = moop_url('api/kegg_map.php?' . http_build_query($kegg_tbl['scope'] + ['pathway' => $r['pathway']]));
      $genes = moop_url('tools/search.php?' . http_build_query($kegg_tbl['scope'] + ['q' => $r['pathway'], 'source' => MOOP_KEGG_PATHWAY_SOURCE])); ?>
    <tr>
      <td data-search="<?= htmlspecialchars($r['class']) ?>"><?= htmlspecialchars($r['class']) ?><?php if ($r['sub'] !== ''): ?><div class="small text-muted"><?= htmlspecialchars($r['sub']) ?></div><?php endif; ?></td>
      <td><?= htmlspecialchars($r['name']) ?> <span class="small text-muted"><?= htmlspecialchars($r['pathway']) ?></span></td>
      <?php foreach ($kegg_tbl['data']['gene_sets'] as $gsk): $n = $r['genes'][$gsk] ?? 0; ?>
      <td class="text-end<?= $n ? '' : ' text-muted' ?>" data-order="<?= (int)$n ?>"><?= $n ? number_format($n) : '—' ?></td>
      <?php endforeach; ?>
      <td class="text-end"><?= $found ?></td>
      <?php if ($kegg_tbl['data']['has_reference']): ?>
      <td class="text-end"><?= $r['kos_total'] === null ? '—' : (int)$r['kos_total'] ?></td>
      <td class="text-end" data-order="<?= $cov === null ? -1 : round($cov, 4) ?>"><?= $cov === null ? '—' : round($cov * 100) . '%' ?></td>
      <?php endif; ?>
      <td class="text-nowrap">
        <a href="<?= htmlspecialchars($map) ?>" target="_blank" rel="noopener"><?= t('Map') ?> <i class="fa fa-external-link-alt link-icon"></i></a>
        · <a href="<?= htmlspecialchars($genes) ?>"><?= t('Genes') ?></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

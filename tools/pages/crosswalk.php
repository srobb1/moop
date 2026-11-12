<?php
/**
 * ID Translator -- content file (controller: tools/crosswalk.php).
 * Variables: $organism, $organisms, $sources [name => label], $source_groups (lib: moop_crosswalk_source_groups),
 *            $ids_text, $into, $result (null before
 *            a search), $too_many, $tool_title, $always [source names], $source_about [name => url],
 *            $max_ids, $max_rows
 */
$self = moop_url_base() . '/tools/crosswalk.php';
$a = function (array $l): string {   // one id, linked when it has somewhere to go
    $id = htmlspecialchars($l['id']);
    if ($l['url'] === '') return $id;
    return '<a href="' . htmlspecialchars($l['url']) . '"' . ($l['external'] ? ' target="_blank" rel="noopener"' : '') . '>' . $id . '</a>';
};
$src_cell = function (string $s) use ($sources, $source_about): string {
    $label = '<span title="' . htmlspecialchars($sources[$s] ?? $s) . '">' . htmlspecialchars($s) . '</span>';
    $about = $source_about[$s] ?? '';
    return $about !== '' && preg_match('#^https://#', $about) ? '<a href="' . htmlspecialchars($about) . '" target="_blank" rel="noopener" title="' . htmlspecialchars($sources[$s] ?? $s) . '">' . htmlspecialchars($s) . '</a>' : $label;
};
?>
<div class="container py-3">
  <div class="card shadow-sm mb-4">
    <div class="card-header text-white d-flex align-items-center gap-2 tool-header">
      <?= page_title(t_plain($tool_title)) ?>
      <?= help_modal_trigger('crosswalk-help', '', 'How the ID Translator works') ?>
      <?= page_purpose('Translate IDs from older transcriptomes, ESTs and arrays into the same gene in other sources of the same species.', '', false, false) ?>
    </div>
  </div>
  <?php
  echo help_modal('crosswalk-help', 'How the ID Translator works', [[
      'heading' => '',
      'cards'   => [
          ['label' => 'Within one species', 'text' => 'Every source here is the organism you chose. The translator finds the same gene under its other names in that species. It never links genes of different species: for those, see Comparative genomics on a gene page.'],
          ['label' => 'What you can paste', 'text' => 'IDs of any kind, one or many: transcripts from older transcriptomes, ESTs, microarray probes, gene models, GenBank accessions. Upper or lower case both work.'],
          ['label' => 'How an ID is translated', 'text' => 'Every source was aligned to the reference transcriptome; an ID maps to a reference gene when it aligns at 95% identity or better. IDs that reach the same reference gene are treated as the same gene.'],
          ['label' => 'What the table shows', 'text' => 'For each ID: the source it comes from, its reference gene with that gene\'s transcripts and description, and the IDs of the same gene in the sources you ticked. Nothing ticked: just the reference genes.'],
          ['label' => 'Links', 'text' => 'IDs of genes on this site open their gene page. IDs from NCBI and WormBase ParaSite open there. Other IDs are plain text: their old websites no longer answer.'],
      ],
  ]], ['intro' => 'One gene, many names, one species: paste IDs and see what each is called in the other sources of that species.']);
  ?>

  <?php if (!$organism): ?>
    <div class="alert alert-light border"><?= t('The ID Translator is not set up on this site yet.') ?></div>
  <?php else: ?>

  <form method="get" action="<?= htmlspecialchars($self) ?>" id="crosswalkForm">
    <?php if (count($organisms) > 1): ?>
      <div class="mb-3" style="max-width:24rem;">
        <label class="form-label fw-semibold" for="cwOrganism"><?= t('Organism') ?></label>
        <select class="form-select" name="organism" id="cwOrganism" data-reload>
          <?php foreach ($organisms as $o): ?>
            <option value="<?= htmlspecialchars($o) ?>"<?= $o === $organism ? ' selected' : '' ?>><?= htmlspecialchars(str_replace('_', ' ', $o)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <input type="hidden" name="organism" value="<?= htmlspecialchars($organism) ?>">
    <?php endif; ?>

    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
        <span class="step-badge me-2">1</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('IDs to translate') ?></span>
      </div>
      <div class="card-body">
        <label class="visually-hidden" for="cwIds"><?= t('IDs to translate') ?></label>
        <textarea class="form-control font-monospace" name="ids" id="cwIds" rows="5" style="max-width:40rem;"
                  placeholder="<?= t('One or more IDs, separated by spaces, commas or new lines') ?>"><?= htmlspecialchars($ids_text) ?></textarea>
        <div class="small text-muted mt-1"><?= t('Up to %d IDs at a time.', $max_ids) ?></div>
      </div>
    </div>

    <div class="card mb-3 shadow-sm">
      <div class="card-header py-2 d-flex align-items-center gap-2 tool-header">
        <span class="step-badge me-2">2</span><span class="fw-semibold" style="font-size:0.9rem;"><?= t('Translate into (optional)') ?></span>
      </div>
      <div class="card-body">
        <div class="small text-muted mb-2"><?= t('Tick the sources whose IDs you want for the same gene. Tick none to see just the reference genes.') ?></div>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
          <label class="visually-hidden" for="cwFilter"><?= t('Find a source') ?></label>
          <input type="search" class="form-control form-control-sm" id="cwFilter" style="max-width:20rem;" placeholder="<?= t('Find a source: lab, author, name') ?>">
          <button type="button" class="btn btn-sm btn-outline-secondary" id="cwNone"><?= t('Clear all') ?></button>
        </div>
        <div class="small mb-2" id="cwPicked" aria-live="polite"></div>
        <div id="cwSources">
          <?php foreach ($source_groups as $g):
              $ticked = count(array_intersect(array_keys($g['sources']), $into)); $gid = 'cwg_' . md5($g['key']); ?>
            <details class="cw-group border rounded mb-2"<?= $ticked ? ' open' : '' ?>>
              <summary class="px-2 py-1 d-flex align-items-center gap-2">
                <input class="form-check-input m-0 cw-group-box" type="checkbox" id="<?= $gid ?>" aria-label="<?= htmlspecialchars(t_plain('Tick every source in %s', t_plain($g['label']))) ?>">
                <span class="fw-semibold"><?= htmlspecialchars(t_plain($g['label'])) ?></span>
                <span class="small text-muted cw-group-count" data-total="<?= count($g['sources']) ?>"><?= t('%d of %d ticked', $ticked, count($g['sources'])) ?></span>
              </summary>
              <div class="px-3 pb-2 pt-1" style="columns: 2 24rem;">
                <?php foreach ($g['sources'] as $name => $label): $hid = 'cw_' . md5($name); ?>
                  <div class="form-check cw-source" style="break-inside:avoid;">
                    <input class="form-check-input" type="checkbox" name="into[]" id="<?= $hid ?>" value="<?= htmlspecialchars($name) ?>"<?= in_array($name, $into, true) ? ' checked' : '' ?>>
                    <label class="form-check-label small" for="<?= $hid ?>"><?= htmlspecialchars($label) ?><?php if ($label !== $name): ?> <span class="text-muted font-monospace">(<?= htmlspecialchars($name) ?>)</span><?php endif; ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
        <div class="small text-muted" id="cwNoMatch" hidden><?= t('No source matches.') ?></div>
      </div>
    </div>

    <button type="submit" class="btn btn-moop-teal"><i class="fa fa-exchange-alt me-1"></i><?= t('Translate') ?></button>
  </form>

  <?php if ($result !== null): ?>
  <div class="card shadow-sm mt-4">
    <div class="card-header py-2 tool-header d-flex flex-wrap align-items-center gap-2">
      <span class="fw-semibold"><?= count($result['rows']) === 1 ? t('1 row') : t('%d rows', count($result['rows'])) ?></span>
      <?php if ($result['rows']): ?>
        <button type="button" class="btn btn-sm btn-light ms-auto" id="cwDownload"><i class="fa fa-download me-1"></i><?= t('Download TSV') ?></button>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($too_many): ?>
        <div class="alert alert-warning py-2"><?= t('Only the first %d IDs were translated.', $max_ids) ?></div>
      <?php endif; ?>
      <?php if ($result['capped']): ?>
        <div class="alert alert-warning py-2"><?= t('Showing the first %d rows. Tick fewer sources or paste fewer IDs to see the rest.', $max_rows) ?></div>
      <?php endif; ?>
      <?php if ($result['not_found']): ?>
        <div class="alert alert-light border py-2 small">
          <?= t('Not found in any source:') ?> <span class="font-monospace"><?= htmlspecialchars(implode(' ', $result['not_found'])) ?></span>
        </div>
      <?php endif; ?>
      <?php if ($result['rows']): ?>
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle" id="cwResults" style="width:100%">
          <thead><tr>
            <th><?= t('Query source') ?></th><th><?= t('Query ID') ?></th><th><?= t('Reference gene') ?></th>
            <th><?= t('Reference transcripts') ?></th>
            <?php foreach ($always as $s): ?><th><?= htmlspecialchars($s) ?></th><?php endforeach; ?>
            <th><?= t('Description') ?></th>
            <?php if ($into): ?><th><?= t('Mapped source') ?></th><th><?= t('Mapped ID') ?></th><?php endif; ?>
          </tr></thead>
          <tbody>
          <?php foreach ($result['rows'] as $r): ?>
            <tr>
              <td class="small"><?= $src_cell($r['query_source']) ?></td>
              <td class="font-monospace small"><?= $a($r['query_link']) ?></td>
              <td class="font-monospace small"><?= $a($r['ref_link']) ?></td>
              <td class="font-monospace small"><?= implode(' ', array_map($a, $r['transcripts'])) ?></td>
              <?php foreach ($always as $s): ?><td class="font-monospace small"><?= implode(' ', array_map($a, $r['always'][$s] ?? [])) ?></td><?php endforeach; ?>
              <td class="small"><?= htmlspecialchars($r['description']) ?></td>
              <?php if ($into): ?>
                <td class="small"><?= $src_cell($r['mapped_source']) ?></td>
                <td class="font-monospace small"><?= $r['mapped'] ? $a($r['mapped']) : '' ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>
</div>

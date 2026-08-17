<?php
/**
 * MANAGE BLAST LINKOUTS - Content Page
 *
 * Variables injected by manage_blast_linkouts.php:
 *   $linkout_config        Full blast_linkouts config array
 *   $db_options            ['org|asm|seq_type' => ['key','display',...], ...]
 *   $per_db_rows           Flat list: [['key','label','url_template'], ...]
 *   $feature_coord_status  Per-assembly feature_coords.tsv status rows
 *   $orphan_registrations  JBrowse registrations with no matching organisms/ directory
 *   $message               Flash message string
 *   $messageType           Bootstrap contextual type (success / warning / danger)
 */
?>

<div class="container py-4">
  <div class="row">
    <div class="col-12">

      <div class="mb-4">
        <a href="admin.php" class="btn btn-outline-secondary btn-sm">
          <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
        </a>
      </div>

      <h2><i class="fa fa-external-link-alt"></i> Manage BLAST Linkouts</h2>
      <p class="text-muted">Configure which buttons appear on each BLAST result hit. Changes take effect immediately for new searches.</p>

      <!-- About -->
      <div class="card mb-4">
        <div class="card-header adm-head" style="cursor:pointer;"
             data-bs-toggle="collapse" data-bs-target="#aboutBlastLinkouts">
          <h5 class="mb-0">
            <i class="fa fa-info-circle"></i> About BLAST Linkouts
            <i class="fa fa-chevron-down float-end"></i>
          </h5>
        </div>
        <div class="collapse" id="aboutBlastLinkouts">
          <div class="card-body">
            <p><strong>Purpose:</strong> Add contextual buttons to every BLAST hit so users can jump directly to related tools.</p>
            <ul>
              <li><strong>Gene Page</strong> — links to the gene detail page for feature databases (mRNA, CDS, protein) when an organism SQLite database is present.</li>
              <li><strong>Genome Browser</strong> — opens JBrowse2 at the hit location with HSPs drawn as colored blocks. For genome BLAST, a hit-level button shows all HSPs and each HSP row has its own zoom button. For feature BLAST, navigates to the gene locus.</li>
              <li><strong>Global External Linkouts</strong> — custom URL buttons added to every BLAST hit across all databases, using placeholders like <code>{fasta_id}</code>.</li>
              <li><strong>Per-Database External Linkouts</strong> — custom URL buttons that appear only for a specific organism / assembly / database combination.</li>
            </ul>
            <p class="mb-0"><small class="text-muted">The Feature Coordinate Index section at the bottom manages the <code>feature_coords.tsv</code> files that power Genome Browser linkouts on feature databases.</small></p>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= htmlspecialchars($messageType) ?> alert-dismissible fade show">
          <?= htmlspecialchars($message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_input_field() ?>

        <!-- Built-in linkouts -->
        <div class="card mb-4">
          <div class="card-header adm-head"><strong>Built-in Linkouts</strong></div>
          <div class="card-body p-0">

            <!-- Gene Page -->
            <div class="p-3 border-bottom">
              <div class="d-flex align-items-center gap-2 mb-1">
                <input class="form-check-input" type="checkbox" name="gene_page" id="gene_page"
                       <?= ($linkout_config['gene_page'] ?? true) ? 'checked' : '' ?>>
                <label class="fw-semibold mb-0" for="gene_page">Gene Page</label>
              </div>
              <p class="text-muted small mb-3">
                Links to the gene detail page when the assembly has an organism database.
                Available for mRNA, CDS, and protein BLAST databases.
              </p>
              <div>
                <label class="form-label small mb-1">Button label</label>
                <input type="text" class="form-control form-control-sm" style="max-width:200px;"
                       name="gene_page_label" placeholder="Gene Page"
                       value="<?= htmlspecialchars($linkout_config['gene_page_label'] ?? 'Gene Page') ?>">
              </div>
            </div>

            <!-- Genome Browser -->
            <div class="p-3">
              <div class="d-flex align-items-center gap-2 mb-1">
                <input class="form-check-input" type="checkbox" name="jbrowse" id="jbrowse"
                       <?= ($linkout_config['jbrowse'] ?? true) ? 'checked' : '' ?>>
                <label class="fw-semibold mb-0" for="jbrowse">Genome Browser (JBrowse2)</label>
              </div>
              <p class="text-muted small mb-3">
                Links to JBrowse2 with HSPs visualized as colored blocks.
                For genome BLAST, HSPs are drawn as a connected match track (one button per hit showing all HSPs, plus a per-HSP zoom button).
                For feature BLAST, navigates to the gene locus.
                Requires <code>feature_coords.tsv</code> — see the Feature Coordinate Index section below.
              </p>
              <div class="row g-3">
                <div class="col-auto">
                  <label class="form-label small mb-1">Button label</label>
                  <input type="text" class="form-control form-control-sm" style="max-width:200px;"
                         name="jbrowse_label" placeholder="Genome Browser"
                         value="<?= htmlspecialchars($linkout_config['jbrowse_label'] ?? 'Genome Browser') ?>">
                </div>
                <div class="col-auto">
                  <label class="form-label small mb-1">Min bit-score to connect HSPs</label>
                  <input type="number" class="form-control form-control-sm" style="max-width:130px;"
                         name="jbrowse_hsp_min_score" min="0" step="1"
                         value="<?= (int)($linkout_config['jbrowse_hsp_min_score'] ?? 0) ?>">
                  <div class="form-text">HSPs below this score shown standalone. 0 = connect all.</div>
                </div>
                <div class="col-auto">
                  <label class="form-label small mb-1">Max span to connect HSPs (bp)</label>
                  <input type="number" class="form-control form-control-sm" style="max-width:130px;"
                         name="jbrowse_hsp_max_span" min="1" step="1"
                         value="<?= (int)($linkout_config['jbrowse_hsp_max_span'] ?? 500000) ?>">
                  <div class="form-text">If total HSP span exceeds this, HSPs shown standalone. Default: 500,000.</div>
                </div>
                <div class="col-auto">
                  <label class="form-label small mb-1">Max HSPs in hit-level browser link</label>
                  <input type="number" class="form-control form-control-sm" style="max-width:130px;"
                         name="jbrowse_hsp_max_link" min="1" step="1"
                         value="<?= (int)($linkout_config['jbrowse_hsp_max_link'] ?? 10) ?>">
                  <div class="form-text">Top N HSPs by score included in the hit-level link. Prevents URLs from getting too long. Default: 10.</div>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- Global external linkouts -->
        <div class="card mb-4">
          <div class="card-header adm-head d-flex justify-content-between align-items-center">
            <div>
              <strong>Global External Linkouts</strong>
              <span class="text-muted ms-2 small">— Apply to every BLAST hit regardless of database</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addLinkoutBtn">
              <i class="fa fa-plus"></i> Add Linkout
            </button>
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Placeholders: <code>{fasta_id}</code> — hit sequence ID &nbsp;|&nbsp;
              <code>{organism}</code> — organism directory name &nbsp;|&nbsp;
              <code>{assembly}</code> — assembly accession
            </p>
            <table class="table table-sm">
              <thead class="table-light">
                <tr>
                  <th style="width:22%">Label</th>
                  <th>URL Template</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="externalLinksBody">
                <?php foreach (($linkout_config['external'] ?? []) as $ext): ?>
                <tr class="linkout-row">
                  <td><input type="text" class="form-control form-control-sm" name="ext_label[]"
                             value="<?= htmlspecialchars($ext['label'] ?? '') ?>" placeholder="Label" required></td>
                  <td><input type="url" class="form-control form-control-sm" name="ext_template[]"
                             value="<?= htmlspecialchars($ext['url_template'] ?? '') ?>"
                             placeholder="https://example.com/gene/{fasta_id}" required></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-linkout-btn">
                    <i class="fa fa-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p id="noLinksMsg" class="text-muted small <?= empty($linkout_config['external']) ? '' : 'd-none' ?>">
              No global linkouts configured.
            </p>
          </div>
        </div>

        <!-- Per-database external linkouts -->
        <div class="card mb-4">
          <div class="card-header adm-head d-flex justify-content-between align-items-center">
            <div>
              <strong>Per-Database External Linkouts</strong>
              <span class="text-muted ms-2 small">— Apply only to a specific organism / assembly / database</span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addPerDbBtn"
                    <?= empty($db_options) ? 'disabled title="No BLAST databases found"' : '' ?>>
              <i class="fa fa-plus"></i> Add Linkout
            </button>
          </div>
          <div class="card-body">
            <p class="text-muted small mb-3">
              Use when different databases have different ID formats or link to different external sites.
              Multiple linkouts per database are supported.
              Same placeholders: <code>{fasta_id}</code>, <code>{organism}</code>, <code>{assembly}</code>
            </p>

            <?php if (empty($db_options)): ?>
              <p class="text-muted small">No BLAST databases found in the organisms directory.</p>
            <?php else: ?>
            <table class="table table-sm" id="perDbTable">
              <thead class="table-light">
                <tr>
                  <th style="width:35%">Database</th>
                  <th style="width:20%">Label</th>
                  <th>URL Template</th>
                  <th style="width:50px"></th>
                </tr>
              </thead>
              <tbody id="perDbBody">
                <?php foreach ($per_db_rows as $row):
                    $display = $db_options[$row['key']]['display'] ?? $row['key'];
                ?>
                <tr class="pdb-row">
                  <td>
                    <small class="text-break"><?= htmlspecialchars($display) ?></small>
                    <input type="hidden" name="pdb_key[]" value="<?= htmlspecialchars($row['key']) ?>">
                  </td>
                  <td><input type="text" class="form-control form-control-sm" name="pdb_label[]"
                             value="<?= htmlspecialchars($row['label']) ?>" placeholder="Label" required></td>
                  <td><input type="url" class="form-control form-control-sm" name="pdb_url[]"
                             value="<?= htmlspecialchars($row['url_template']) ?>"
                             placeholder="https://example.com/{fasta_id}" required></td>
                  <td><button type="button" class="btn btn-sm btn-outline-danger remove-pdb-btn">
                    <i class="fa fa-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p id="noPerDbMsg" class="text-muted small <?= empty($per_db_rows) ? '' : 'd-none' ?>">
              No per-database linkouts configured.
            </p>
            <?php endif; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button>
        <a href="admin.php" class="btn btn-outline-secondary ms-2">Back to Dashboard</a>
      </form>

      <!-- Feature coordinate index (outside form — AJAX buttons) -->
      <div class="card mt-4 mb-4">
        <div class="card-header adm-head">
          <strong>Coordinate Indexes</strong>
          <span class="text-muted ms-2 small">— Two flat files per gene set, both written automatically when an assembly is registered in JBrowse, and both regenerable here for gene sets registered before they existed.
          <code>feature_coords.tsv</code> powers Genome Browser linkouts on feature BLAST databases (protein / mRNA / CDS);
          <code>exon_coords.tsv</code> lets Primer BLAST place a cDNA product on the genome, which is what gives a junction-spanning primer a browser link.</span>
        </div>
        <div class="card-body p-0">

          <?php if (!empty($orphan_registrations)): ?>
            <div class="alert alert-warning rounded-0 border-0 border-bottom mb-0">
              <h6 class="alert-heading">
                <i class="fa fa-exclamation-triangle"></i>
                <?= count($orphan_registrations) ?> JBrowse
                <?= count($orphan_registrations) === 1 ? 'registration is' : 'registrations are' ?>
                not listed below
              </h6>
              <p class="small mb-2">
                These assemblies are registered in JBrowse but have no matching directory under
                <code>organisms/</code>, so no feature coordinate index can be built for them and
                BLAST linkouts will not work. Usually the registration was made under a different
                assembly name than the data actually uses.
              </p>
              <ul class="small mb-0">
                <?php foreach ($orphan_registrations as $o): ?>
                <li>
                  <code><?= htmlspecialchars($o['file']) ?></code> — <?= htmlspecialchars($o['reason']) ?>
                  <?php if (!empty($o['actual'])): ?>
                    <br><span class="text-muted">
                      <code>organisms/<?= htmlspecialchars($o['organism']) ?>/</code> actually contains:
                      <?= htmlspecialchars(implode(', ', $o['actual'])) ?>
                    </span>
                  <?php endif; ?>
                </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (empty($feature_coord_status)): ?>
            <p class="text-muted small p-3 mb-0">No JBrowse-registered assemblies found.</p>
          <?php else: ?>
          <?php
          /**
           * One cell per index file. Both indexes come from the same GFF and the
           * same registration step, so they sit side by side rather than in two
           * tables that would always be regenerated together.
           *
           * Every field the JS updates is named with data-field and scoped to
           * this cell. The previous version addressed them as row.cells[3..5],
           * which is fine until a column is added — and adding a column is
           * exactly what this change does.
           */
          $index_cell = function (array $row, $kind) {
              $has  = $kind === 'exon' ? $row['has_exon'] : $row['has_tsv'];
              $size = $kind === 'exon' ? $row['exon_size'] : $row['tsv_size'];
              $when = $kind === 'exon' ? $row['exon_modified'] : $row['tsv_modified'];
              $endpoint = $kind === 'exon' ? 'generate_exon_coords.php' : 'generate_feature_coords.php';
              ?>
              <td data-index="<?= $kind ?>">
                <span data-field="status">
                  <?php if ($has): ?>
                    <span class="badge bg-success">Ready</span>
                  <?php elseif ($row['has_gff']): ?>
                    <span class="badge bg-warning text-dark">Not generated</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">No <?= genes_gff_filename() ?></span>
                  <?php endif; ?>
                </span>
                <div class="small text-muted">
                  <span data-field="size"><?= htmlspecialchars($size ?? '—') ?></span>
                  · <span data-field="modified"><?= htmlspecialchars($when ?? '—') ?></span>
                </div>
                <?php if ($row['has_gff']): ?>
                  <button type="button" class="btn btn-sm btn-outline-primary mt-1 gen-index-btn"
                          data-endpoint="<?= $endpoint ?>"
                          data-organism="<?= htmlspecialchars($row['organism']) ?>"
                          data-assembly="<?= htmlspecialchars($row['assembly']) ?>"
                          data-gene-set="<?= htmlspecialchars($row['gene_set']) ?>">
                    <i class="fa fa-sync-alt"></i> <?= $has ? 'Regenerate' : 'Generate' ?>
                  </button>
                <?php endif; ?>
              </td>
              <?php
          };
          ?>
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Organism</th>
                <th>Assembly</th>
                <th>Gene Set</th>
                <th>Feature index
                  <div class="fw-normal small text-muted"><code>feature_coords.tsv</code> — BLAST linkouts</div>
                </th>
                <th>Exon index
                  <div class="fw-normal small text-muted"><code>exon_coords.tsv</code> — Primer BLAST cDNA linkouts</div>
                </th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($feature_coord_status as $row): ?>
              <?php $row_id = htmlspecialchars($row['organism'] . '_' . $row['assembly'] . '_' . $row['gene_set']); ?>
              <tr id="fcs-<?= $row_id ?>">
                <td class="small"><?= htmlspecialchars($row['organism']) ?></td>
                <td class="small"><?= htmlspecialchars($row['assembly']) ?></td>
                <td class="small"><?= htmlspecialchars($row['gene_set']) ?></td>
                <?php $index_cell($row, 'feature'); ?>
                <?php $index_cell($row, 'exon'); ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
// --- Global external linkouts ---
const newRowTemplate = `
  <tr class="linkout-row">
    <td><input type="text" class="form-control form-control-sm" name="ext_label[]" placeholder="Label" required></td>
    <td><input type="url" class="form-control form-control-sm" name="ext_template[]" placeholder="https://example.com/gene/{fasta_id}" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-linkout-btn"><i class="fa fa-trash"></i></button></td>
  </tr>`;

function refreshNoLinksMsg() {
  document.getElementById('noLinksMsg').classList.toggle('d-none',
    document.querySelectorAll('.linkout-row').length > 0);
}

document.getElementById('addLinkoutBtn').addEventListener('click', () => {
  document.getElementById('externalLinksBody').insertAdjacentHTML('beforeend', newRowTemplate);
  refreshNoLinksMsg();
});

document.getElementById('externalLinksBody').addEventListener('click', e => {
  if (e.target.closest('.remove-linkout-btn')) {
    e.target.closest('tr').remove();
    refreshNoLinksMsg();
  }
});

// --- Per-database linkouts ---
const perDbOptions = <?= json_encode(array_values($db_options ?? [])) ?>;

function escH(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function makeDbSelect() {
  const opts = perDbOptions.map(o =>
    `<option value="${escH(o.key)}">${escH(o.display)}</option>`
  ).join('');
  return `<select class="form-select form-select-sm" name="pdb_key[]" required>
    <option value="">— select database —</option>
    ${opts}
  </select>`;
}

function refreshNoPerDbMsg() {
  const msg = document.getElementById('noPerDbMsg');
  if (msg) msg.classList.toggle('d-none', document.querySelectorAll('.pdb-row').length > 0);
}

document.getElementById('addPerDbBtn')?.addEventListener('click', () => {
  const tr = document.createElement('tr');
  tr.className = 'pdb-row';
  tr.innerHTML = `
    <td>${makeDbSelect()}</td>
    <td><input type="text" class="form-control form-control-sm" name="pdb_label[]" placeholder="Label" required></td>
    <td><input type="url" class="form-control form-control-sm" name="pdb_url[]" placeholder="https://example.com/{fasta_id}" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-pdb-btn"><i class="fa fa-trash"></i></button></td>`;
  document.getElementById('perDbBody').appendChild(tr);
  refreshNoPerDbMsg();
});

document.getElementById('perDbBody')?.addEventListener('click', e => {
  if (e.target.closest('.remove-pdb-btn')) {
    e.target.closest('tr').remove();
    refreshNoPerDbMsg();
  }
});

// --- Feature coords generation ---
// One handler for both index files: they differ only in which endpoint they
// call. Every element it touches is found INSIDE the button's own cell, so a
// row with two of these cannot update the wrong one — and no part of it depends
// on a column position, which is what made adding this second column safe.
document.querySelectorAll('.gen-index-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const cell = btn.closest('td');
    const origText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';

    try {
      const fd = new FormData();
      fd.append('organism', btn.dataset.organism);
      fd.append('assembly', btn.dataset.assembly);
      fd.append('gene_set', btn.dataset.geneSet);
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

      const res = await fetch(window.sitePath + '/admin/api/' + btn.dataset.endpoint,
                              { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        cell.querySelector('[data-field="status"]').innerHTML = '<span class="badge bg-success">Ready</span>';
        cell.querySelector('[data-field="size"]').textContent = data.tsv_size ?? '—';
        cell.querySelector('[data-field="modified"]').textContent = data.modified ?? '—';
        btn.innerHTML = '<i class="fa fa-sync-alt"></i> Regenerate';
      } else {
        alert('Error: ' + data.message);
        btn.innerHTML = origText;
      }
    } catch (e) {
      alert('Request failed: ' + e.message);
      btn.innerHTML = origText;
    }
    btn.disabled = false;
  });
});
</script>

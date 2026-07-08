<?php
/**
 * MANAGE BLAST LINKOUTS - Content Page
 *
 * Variables injected by manage_blast_linkouts.php:
 *   $linkout_config        Full blast_linkouts config array
 *   $db_options            ['org|asm|seq_type' => ['key','display',...], ...]
 *   $per_db_rows           Flat list: [['key','label','url_template'], ...]
 *   $feature_coord_status  Per-assembly feature_coords.tsv status rows
 *   $message               Flash message string
 *   $messageType           Bootstrap contextual type (success / danger)
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
      <div class="card mb-4 border-info">
        <div class="card-header bg-info bg-opacity-10" style="cursor:pointer;"
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
          <div class="card-header"><strong>Built-in Linkouts</strong></div>
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
          <div class="card-header d-flex justify-content-between align-items-center">
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
          <div class="card-header d-flex justify-content-between align-items-center">
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
        <div class="card-header">
          <strong>Feature Coordinate Index</strong>
          <span class="text-muted ms-2 small">— Required for Genome Browser linkouts on feature BLAST databases (protein / mRNA / CDS). Stored as <code>feature_coords.tsv</code> in each gene set directory. Generated automatically when registering an assembly in JBrowse.</span>
        </div>
        <div class="card-body p-0">
          <?php if (empty($feature_coord_status)): ?>
            <p class="text-muted small p-3 mb-0">No JBrowse-registered assemblies found.</p>
          <?php else: ?>
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Organism</th>
                <th>Assembly</th>
                <th>Gene Set</th>
                <th>Status</th>
                <th>File size</th>
                <th>Last generated</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($feature_coord_status as $row): ?>
              <?php $row_id = htmlspecialchars($row['organism'] . '_' . $row['assembly'] . '_' . $row['gene_set']); ?>
              <tr id="fcs-<?= $row_id ?>">
                <td class="small"><?= htmlspecialchars($row['organism']) ?></td>
                <td class="small"><?= htmlspecialchars($row['assembly']) ?></td>
                <td class="small"><?= htmlspecialchars($row['gene_set']) ?></td>
                <td>
                  <?php if ($row['has_tsv']): ?>
                    <span class="badge bg-success">Ready</span>
                  <?php elseif ($row['has_gff']): ?>
                    <span class="badge bg-warning text-dark">Not generated</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">No <?= genes_gff_filename() ?></span>
                  <?php endif; ?>
                </td>
                <td class="small"><?= $row['tsv_size'] ?? '—' ?></td>
                <td class="small text-muted"><?= htmlspecialchars($row['tsv_modified'] ?? '—') ?></td>
                <td>
                  <?php if ($row['has_gff']): ?>
                  <button type="button" class="btn btn-sm btn-outline-primary gen-feature-coords-btn"
                          data-organism="<?= htmlspecialchars($row['organism']) ?>"
                          data-assembly="<?= htmlspecialchars($row['assembly']) ?>"
                          data-gene-set="<?= htmlspecialchars($row['gene_set']) ?>">
                    <i class="fa fa-sync-alt"></i> <?= $row['has_tsv'] ? 'Regenerate' : 'Generate' ?>
                  </button>
                  <?php endif; ?>
                </td>
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
document.querySelectorAll('.gen-feature-coords-btn').forEach(btn => {
  btn.addEventListener('click', async () => {
    const organism = btn.dataset.organism;
    const assembly = btn.dataset.assembly;
    const geneSet  = btn.dataset.geneSet;
    const row = document.getElementById('fcs-' + organism + '_' + assembly + '_' + geneSet);
    const origText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';

    try {
      const fd = new FormData();
      fd.append('organism', organism);
      fd.append('assembly', assembly);
      fd.append('gene_set', geneSet);
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

      const res = await fetch('/moop/admin/api/generate_feature_coords.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        row.cells[3].innerHTML = '<span class="badge bg-success">Ready</span>';
        row.cells[4].textContent = data.tsv_size ?? '—';
        row.cells[5].textContent = data.modified;
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

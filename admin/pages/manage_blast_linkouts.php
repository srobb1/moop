<?php
/**
 * MANAGE BLAST LINKOUTS - Content Page
 *
 * Variables injected by manage_blast_linkouts.php:
 *   $linkout_config  Full blast_linkouts config array
 *   $db_options      ['org|asm|seq_type' => ['key','display','organism','assembly','db_type','db_name'], ...]
 *   $per_db_rows     Flat list: [['key','label','url_template'], ...]
 *   $message         Flash message string
 *   $messageType     Bootstrap contextual type (success / danger)
 */
?>

<div class="container mt-4">
  <h2><i class="fa fa-external-link-alt"></i> Manage BLAST Linkouts</h2>
  <p class="text-muted">Configure which buttons appear on each BLAST hit. Changes take effect immediately for new searches.</p>

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
      <div class="card-body">
        <div class="d-flex align-items-start gap-3 mb-3">
          <div class="form-check mt-1" style="min-width:1.5rem;">
            <input class="form-check-input" type="checkbox" name="gene_page" id="gene_page"
                   <?= ($linkout_config['gene_page'] ?? true) ? 'checked' : '' ?>>
          </div>
          <div class="flex-grow-1">
            <label class="form-check-label fw-semibold" for="gene_page">Gene Page</label>
            <span class="text-muted ms-2 small">— Links to the gene detail page (<code>tools/parent.php</code>) when the assembly has an organism database. Shown for mRNA, CDS, and protein BLAST databases.</span>
            <div class="mt-1">
              <input type="text" class="form-control form-control-sm" style="max-width:220px;"
                     name="gene_page_label" placeholder="Button label"
                     value="<?= htmlspecialchars($linkout_config['gene_page_label'] ?? 'Gene Page') ?>">
              <small class="text-muted">Button label shown on BLAST results</small>
            </div>
          </div>
        </div>
        <div class="d-flex align-items-start gap-3">
          <div class="form-check mt-1" style="min-width:1.5rem;">
            <input class="form-check-input" type="checkbox" name="jbrowse" id="jbrowse"
                   <?= ($linkout_config['jbrowse'] ?? true) ? 'checked' : '' ?>>
          </div>
          <div class="flex-grow-1">
            <label class="form-check-label fw-semibold" for="jbrowse">Genome Browser</label>
            <span class="text-muted ms-2 small">— Links to JBrowse2 with HSPs visualized as colored blocks. For genome BLAST, HSPs are drawn connected as a match track. For feature BLAST, navigates to the gene locus. Requires <code>feature_coords.tsv</code> (auto-generated when registering an assembly in JBrowse).</span>
            <div class="mt-1 d-flex gap-3 flex-wrap">
              <div>
                <input type="text" class="form-control form-control-sm" style="max-width:220px;"
                       name="jbrowse_label" placeholder="Button label"
                       value="<?= htmlspecialchars($linkout_config['jbrowse_label'] ?? 'Genome Browser') ?>">
                <small class="text-muted">Button label</small>
              </div>
              <div>
                <div class="input-group input-group-sm" style="max-width:220px;">
                  <input type="number" class="form-control form-control-sm" name="jbrowse_hsp_min_score"
                         min="0" step="1"
                         value="<?= (int)($linkout_config['jbrowse_hsp_min_score'] ?? 0) ?>">
                  <span class="input-group-text">min bit-score</span>
                </div>
                <small class="text-muted">HSPs at or above this score drawn connected; below shown standalone. 0 = all connected.</small>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Global external linkouts (apply to all DBs) -->
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
          <span class="text-muted ms-2 small">— Apply only to a specific organism / assembly / database combination</span>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addPerDbBtn"
                <?= empty($db_options) ? 'disabled title="No BLAST databases found"' : '' ?>>
          <i class="fa fa-plus"></i> Add Linkout
        </button>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          Use when different databases have different ID formats or link to different external sites.
          You can add multiple linkouts for the same database.
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
    <a href="admin.php" class="btn btn-outline-secondary ms-2">Cancel</a>
  </form>

  <!-- Feature coordinate index status (outside the form — buttons trigger AJAX) -->
  <div class="card mt-4 mb-4">
    <div class="card-header">
      <strong>Feature Coordinate Index</strong>
      <span class="text-muted ms-2 small">— Required for JBrowse linkouts on protein / mRNA / CDS BLAST hits. Stored in each assembly directory as <code>feature_coords.tsv</code>. Generated automatically on future JBrowse assembly registrations.</span>
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
            <th>Status</th>
            <th>Features</th>
            <th>Last generated</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feature_coord_status as $row): ?>
          <tr id="fcs-<?= htmlspecialchars($row['organism'] . '_' . $row['assembly']) ?>">
            <td class="small"><?= htmlspecialchars($row['organism']) ?></td>
            <td class="small"><?= htmlspecialchars($row['assembly']) ?></td>
            <td>
              <?php if ($row['has_tsv']): ?>
                <span class="badge bg-success">Ready</span>
              <?php elseif ($row['has_gff']): ?>
                <span class="badge bg-warning text-dark">Not generated</span>
              <?php else: ?>
                <span class="badge bg-secondary">No genomic.gff</span>
              <?php endif; ?>
            </td>
            <td class="small"><?= $row['has_tsv'] ? number_format($row['tsv_lines']) : '—' ?></td>
            <td class="small text-muted"><?= htmlspecialchars($row['tsv_modified'] ?? '—') ?></td>
            <td>
              <?php if ($row['has_gff']): ?>
              <button type="button"
                      class="btn btn-sm btn-outline-primary gen-feature-coords-btn"
                      data-organism="<?= htmlspecialchars($row['organism']) ?>"
                      data-assembly="<?= htmlspecialchars($row['assembly']) ?>">
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

<script>
// --- Global external linkouts ---

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
    const row = document.getElementById('fcs-' + organism + '_' + assembly);
    const origText = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating…';

    try {
      const fd = new FormData();
      fd.append('organism', organism);
      fd.append('assembly', assembly);
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      if (csrfMeta) fd.append('csrf_token', csrfMeta.content);

      const res = await fetch('/moop/admin/api/generate_feature_coords.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        row.cells[2].innerHTML = '<span class="badge bg-success">Ready</span>';
        row.cells[3].textContent = Number(data.features).toLocaleString();
        row.cells[4].textContent = data.modified;
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

<?php
/**
 * MANAGE BLAST LINKOUTS - Content Page
 *
 * Variables injected by manage_blast_linkouts.php:
 *   $linkout_config  ['gene_page'=>bool, 'jbrowse'=>bool, 'external'=>[...]]
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
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="gene_page" id="gene_page"
                 <?= ($linkout_config['gene_page'] ?? true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="gene_page">
            <strong>Gene Page</strong>
            <span class="text-muted ms-2">— Links to the gene detail page (<code>tools/parent.php</code>) when the assembly has an organism database. Shown for mRNA, CDS, and protein BLAST databases.</span>
          </label>
        </div>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="jbrowse" id="jbrowse"
                 <?= ($linkout_config['jbrowse'] ?? true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="jbrowse">
            <strong>View in JBrowse</strong>
            <span class="text-muted ms-2">— Links to JBrowse2 at the gene locus when the assembly is registered. For genome BLAST, links directly to the HSP coordinates. Requires <code>feature_coords.tsv</code> (auto-generated when registering an assembly in JBrowse).</span>
          </label>
        </div>
      </div>
    </div>

    <!-- External linkouts -->
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>External Linkouts</strong>
        <button type="button" class="btn btn-sm btn-outline-primary" id="addLinkoutBtn">
          <i class="fa fa-plus"></i> Add Linkout
        </button>
      </div>
      <div class="card-body">
        <p class="text-muted small mb-3">
          URL templates support these placeholders:
          <code>{fasta_id}</code> — BLAST hit sequence ID &nbsp;|&nbsp;
          <code>{organism}</code> — organism directory name &nbsp;|&nbsp;
          <code>{assembly}</code> — assembly accession
        </p>

        <table class="table table-sm" id="externalLinksTable">
          <thead class="table-light">
            <tr>
              <th style="width:20%">Label</th>
              <th>URL Template</th>
              <th style="width:60px"></th>
            </tr>
          </thead>
          <tbody id="externalLinksBody">
            <?php foreach (($linkout_config['external'] ?? []) as $ext): ?>
            <tr class="linkout-row">
              <td>
                <input type="text" class="form-control form-control-sm" name="ext_label[]"
                       value="<?= htmlspecialchars($ext['label'] ?? '') ?>" placeholder="Label" required>
              </td>
              <td>
                <input type="url" class="form-control form-control-sm" name="ext_template[]"
                       value="<?= htmlspecialchars($ext['url_template'] ?? '') ?>"
                       placeholder="https://example.com/gene/{fasta_id}" required>
              </td>
              <td>
                <button type="button" class="btn btn-sm btn-outline-danger remove-linkout-btn">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <p id="noLinksMsg" class="text-muted small <?= empty($linkout_config['external']) ? '' : 'd-none' ?>">
          No external linkouts configured. Click <strong>Add Linkout</strong> to add one.
        </p>
      </div>
    </div>

    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save Settings</button>
    <a href="admin.php" class="btn btn-outline-secondary ms-2">Cancel</a>
  </form>
</div>

<script>
const newRowTemplate = `
  <tr class="linkout-row">
    <td><input type="text" class="form-control form-control-sm" name="ext_label[]" placeholder="Label" required></td>
    <td><input type="url" class="form-control form-control-sm" name="ext_template[]" placeholder="https://example.com/gene/{fasta_id}" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger remove-linkout-btn"><i class="fa fa-trash"></i></button></td>
  </tr>`;

function refreshNoLinksMsg() {
  const rows = document.querySelectorAll('.linkout-row');
  document.getElementById('noLinksMsg').classList.toggle('d-none', rows.length > 0);
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
</script>

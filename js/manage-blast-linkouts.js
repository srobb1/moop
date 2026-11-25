/**
 * Manage BLAST Linkouts (admin/manage_blast_linkouts.php): add/remove rows in the global and
 * per-database linkout tables, and build a gene set's feature/exon coordinate index.
 * Needs perDbOptions (inline_scripts), escapeHtml (js/modules/utilities.js) and window.sitePath.
 */
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
// perDbOptions (the databases for the per-database select) comes from the controller's inline_scripts.

function makeDbSelect() {
  const opts = perDbOptions.map(o =>
    `<option value="${escapeHtml(o.key)}">${escapeHtml(o.display)}</option>`
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

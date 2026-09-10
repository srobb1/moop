/**
 * Taxonomy suggestions on Manage Groups.
 *
 * Curated groups drift from the taxonomy tree because they are maintained by hand.
 * lib/group_taxonomy_check.php finds gene sets that probably belong to a group they are
 * not in; this file makes those suggestions actionable.
 *
 * ⚠️ NOTHING HERE SAVES A GROUP. "Add" puts the row into the existing editor with the
 * group pre-ticked and leaves the admin to press Save. Group membership is editorial, and
 * a suggestion that silently wrote to the groups file would make the file untrustworthy.
 * The only thing this file writes is a DISMISSAL ("not applicable"), which records that a
 * difference is deliberate and never touches group membership.
 *
 * It also deliberately does not reuse .groups-display: the row editor in manage-groups.js
 * builds its tag list from the text of every .tag-chip inside that span, so a suggestion
 * chip in there would be saved as a group literally named "+ Bats?".
 */
document.addEventListener('DOMContentLoaded', function () {

  /**
   * Find the main-table row for an organism/assembly/gene set, and put it on the page.
   *
   * ⚠️ #groupsTable is a paginated DataTable: rows on other pages, or hidden by the filter
   * box, are DETACHED from the document, so document.querySelector cannot see them. The
   * first version of this did exactly that and failed for every suggestion whose row was
   * not on the current page. Look the row up through the DataTables instance instead,
   * clear a filter that hides it, and turn to the page that holds it.
   *
   * @return {HTMLTableRowElement|null} the row, attached to the document, or null
   */
  function findRow(organism, assembly, geneSet) {
    function matches(tr) {
      return tr.dataset.organism === organism &&
             tr.dataset.assembly === assembly &&
             tr.dataset.geneSet === geneSet;
    }

    if (!(window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#groupsTable'))) {
      return Array.from(document.querySelectorAll('#assemblies-tbody tr')).find(matches) || null;
    }

    const dt   = jQuery('#groupsTable').DataTable();
    const node = dt.rows().nodes().toArray().find(matches);
    if (!node) { return null; }

    // Where the row falls among the rows the table would show now, in display order.
    function position() {
      return dt.rows({ order: 'current', search: 'applied' }).nodes().toArray().indexOf(node);
    }

    let pos = position();
    if (pos === -1) {                            // hidden by the filter box
      dt.search('').draw();
      pos = position();
      if (pos === -1) { return null; }
    }

    const len = dt.page.len();                   // -1 means "All": nothing to turn to
    if (len > 0) {
      dt.page(Math.floor(pos / len)).draw('page');
    }
    return document.body.contains(node) ? node : null;
  }

  /**
   * Open a row's group editor with `group` pre-ticked but UNSAVED.
   *
   * Works by driving the existing UI rather than reaching into manage-groups.js: click
   * Edit, then click the matching "available" chip the editor renders. That keeps this
   * feature out of the editor's closure state (selectedTags is private to it), so the
   * two cannot fall out of step.
   */
  function openEditorWith(row, group) {
    if (!row) { return false; }

    const editBtn = row.querySelector('.edit-groups');
    if (!editBtn) { return false; }              // stale rows have a Delete button instead

    // A permission problem turns Edit into a modal trigger; let that modal do its job.
    if (editBtn.getAttribute('data-bs-toggle') === 'modal') {
      editBtn.click();
      return false;
    }

    const editor = row.querySelector('.tag-editor');
    if (!editor || editor.style.display !== 'block') {
      editBtn.click();
    }

    const chips = row.querySelectorAll('.tag-editor .tag-chip.available');
    let ticked = false;
    chips.forEach(function (chip) {
      if (!ticked && chip.textContent.trim() === group) {
        chip.click();                            // moves it into "Selected tags"
        ticked = true;
      }
    });

    // Already selected (someone ticked it by hand first) counts as success — the goal is
    // "the editor is open with this group on", not "this click did the ticking".
    if (!ticked) {
      const selected = row.querySelectorAll('.tag-editor .selected-tags-display .tag-chip');
      selected.forEach(function (chip) {
        if (chip.textContent.replace('×', '').trim() === group) { ticked = true; }
      });
    }

    row.querySelectorAll('.groups-suggestions').forEach(function (s) { s.style.display = 'none'; });

    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    row.classList.add('table-warning');
    setTimeout(function () { row.classList.remove('table-warning'); }, 2500);

    return ticked;
  }

  // ── Clicks inside the main table: the chip, its ×, and the editor's Cancel ─────
  // ONE listener delegated from the tbody, not one per element. #groupsTable is a paginated
  // DataTable and rows on other pages are detached from the document; binding per element
  // with querySelectorAll only reached them because jQuery 3 happens to run DataTables' init
  // (a $(document).ready in manage-groups.js) AFTER this DOMContentLoaded handler. The tbody
  // itself is never detached, so delegation does not depend on that ordering.
  const tbody = document.getElementById('assemblies-tbody');
  if (tbody) {
    tbody.addEventListener('click', function (event) {
      const target = event.target;

      // The × sits inside the chip, so test it first: it records the difference as
      // deliberate and must not also open the editor.
      const dismissX = target.closest('.suggestion-dismiss');
      if (dismissX) {
        event.stopPropagation();
        const suggested = dismissX.closest('.tag-chip.suggested');
        dismiss(suggested.getAttribute('data-organism'), suggested.getAttribute('data-group'));
        return;
      }

      const chip = target.closest('.tag-chip.suggested');
      if (chip) {
        openEditorWith(chip.closest('tr'), chip.getAttribute('data-group'));
        return;
      }

      // Cancelling out of the editor brings the suggestion chips back.
      if (target.closest('.cancel-btn')) {
        target.closest('tr').querySelectorAll('.groups-suggestions').forEach(function (s) { s.style.display = ''; });
      }
    });
  }

  // ── "Add in editor" from the summary card ─────────────────────────────────────
  document.querySelectorAll('.suggestion-goto').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.getAttribute('data-bs-toggle') === 'modal') { return; }
      const tr  = btn.closest('tr');
      const row = findRow(tr.dataset.organism, tr.dataset.assembly, tr.dataset.geneSet);
      if (!openEditorWith(row, tr.dataset.group)) {
        showAlert('Could not open the editor for ' + tr.dataset.organism +
                  '. Add the group from its row in the table above.', 'warning');
      }
    });
  });

  // ── Dismissals ────────────────────────────────────────────────────────────────
  function showAlert(message, type) {
    const box = document.getElementById('taxonomy-suggestions-alert');
    if (!box) { return; }
    box.innerHTML = '<div class="alert alert-' + type + ' alert-dismissible fade show">' +
      message.replace(/</g, '&lt;') +
      '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  }

  function postDismissal(organism, group, reason, remove, onDone) {
    const body = new URLSearchParams();
    body.set('organism', organism);
    body.set('group', group);
    body.set('reason', reason || '');
    body.set('remove', remove ? '1' : '0');

    // js/modules/csrf.js wraps window.fetch and adds the token, so no header is set here.
    fetch(sitePath + '/admin/api/dismiss_group_suggestion.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data && data.success) {
          onDone();
        } else {
          // A write the server could not do must be reported, never swallowed — an
          // "OK" on a failed write is the recurring bug shape in this codebase.
          showAlert('Could not save: ' + ((data && data.error) || 'unknown error'), 'danger');
        }
      })
      .catch(function (err) {
        showAlert('Could not reach the server: ' + err, 'danger');
      });
  }

  function dismiss(organism, group) {
    const reason = window.prompt(
      'Mark "' + organism + '" as deliberately NOT in "' + group + '".\n\n' +
      'Why? (optional — recorded so the decision is not a mystery later)', '');
    if (reason === null) { return; }             // cancelled

    postDismissal(organism, group, reason, false, function () {
      showAlert('Recorded: ' + organism + ' is deliberately not in ' + group +
                '. Reloading…', 'success');
      setTimeout(function () { window.location.reload(); }, 700);
    });
  }

  document.querySelectorAll('.suggestion-dismiss-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.getAttribute('data-bs-toggle') === 'modal') { return; }
      const tr = btn.closest('tr');
      dismiss(tr.dataset.organism, tr.dataset.group);
    });
  });

  document.querySelectorAll('.suggestion-restore-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const tr = btn.closest('tr');
      postDismissal(tr.dataset.organism, tr.dataset.group, '', true, function () {
        window.location.reload();
      });
    });
  });
});

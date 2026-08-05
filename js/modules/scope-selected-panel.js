/**
 * The "Selected" panel that sits beside an organism → assembly → gene-set scope list.
 *
 * ONE implementation, used by Annotation Search and MOOPmart. Those two pages had already
 * drifted apart badly enough that notes/SHARED_SCOPE_SELECTOR_PLAN.md recorded the wrong
 * page as the more advanced one — each had gained fixes the other never got, because every
 * scope change was two edits and the second was easy to forget. This module exists so the
 * panel, at least, cannot drift again.
 *
 * It reads everything it needs from the CHECKBOXES, never from sibling DOM. That is
 * deliberate: scraping neighbouring elements is what ties a component to one page's markup,
 * and it is the "DOM reads done page-wide" shape CLAUDE.md §9b calls out. Each checkbox
 * carries data-org / data-asm / data-gs / data-label / data-cn / data-asm-display.
 *
 * @param {Object}  opts
 * @param {string}  opts.checkbox  selector for the gene-set checkboxes (e.g. '.scope-gs-cb')
 * @param {string}  opts.row       selector for the row a checkbox lives in, for the
 *                                 'selected' highlight class
 * @param {string}  opts.panel     id of the panel container
 * @param {string}  opts.count     id of the count badge (optional)
 * @param {Function} [opts.onChange] called after any change, for page-specific side effects
 *                                 (MOOPmart refreshes annotation sources; search reloads types)
 */
function initScopeSelectedPanel(opts) {
    const { checkbox, row, panel, count, onChange } = opts;
    const panelEl = document.getElementById(panel);
    if (!panelEl) return;                 // page without the panel — nothing to wire
    const countEl = count ? document.getElementById(count) : null;

    function render() {
        // Group the ticked gene sets by organism, preserving DOM order so the panel reads in
        // the same order as the list beside it.
        const byOrg = {};
        document.querySelectorAll(checkbox + ':checked').forEach((cb) => {
            const org = cb.dataset.org;
            if (!byOrg[org]) {
                byOrg[org] = {
                    label: cb.dataset.label || String(org).replace(/_/g, ' '),
                    cn:    cb.dataset.cn || '',
                    rows:  [],
                };
            }
            byOrg[org].rows.push((cb.dataset.asmDisplay || cb.dataset.asm) + ' › ' + cb.dataset.gs);
        });

        const orgs = Object.keys(byOrg);
        if (countEl) countEl.textContent = orgs.length;

        if (!orgs.length) {
            // Says what to DO. It must not say "none = all organisms": with nothing selected
            // neither page can proceed, and an unscoped fan-out across every organism is the
            // query we least want run by accident.
            panelEl.innerHTML =
                '<div class="text-muted small p-2 fst-italic">None yet — pick at least one organism</div>';
            return;
        }

        panelEl.innerHTML = orgs.map((org) => {
            const d = byOrg[org];
            // .sci-name, not <em>: an organism is always sentence-case italic, site-wide, and
            // hard-coding <em> here would opt this component out of that rule.
            return `<div class="px-2 py-1 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                  <span><strong class="sci-name">${esc(d.label)}</strong>${
                      d.cn ? ' <span class="text-muted small">· ' + esc(d.cn) + '</span>' : ''
                  }</span>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-danger flex-shrink-0 scope-panel-remove"
                          data-org="${esc(org)}" title="Remove"
                          aria-label="Remove ${esc(d.label)} from selection"><i class="fas fa-times"></i></button>
                </div>
                ${d.rows.map((r) => `<div class="text-muted ps-2" style="font-size:0.75rem;">› ${esc(r)}</div>`).join('')}
              </div>`;
        }).join('');
    }

    function esc(v) {
        return String(v == null ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Keep the row highlight in step with the checkbox, then re-render.
    document.addEventListener('change', (e) => {
        if (!e.target.matches(checkbox)) return;
        const rowEl = e.target.closest(row);
        if (rowEl) rowEl.classList.toggle('selected', e.target.checked);
        render();
        if (onChange) onChange();
    });

    // The × unticks every gene set for that organism — scoped to THIS panel's checkbox
    // class, so a page rendering more than one scope control cannot clear the wrong one.
    panelEl.addEventListener('click', (e) => {
        const btn = e.target.closest('.scope-panel-remove');
        if (!btn) return;
        document.querySelectorAll(`${checkbox}[data-org="${CSS.escape(btn.dataset.org)}"]`).forEach((cb) => {
            cb.checked = false;
            const rowEl = cb.closest(row);
            if (rowEl) rowEl.classList.remove('selected');
        });
        render();
        if (onChange) onChange();
    });

    render();       // reflect any server-rendered pre-selection
    return { render };
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initScopeSelectedPanel };
}

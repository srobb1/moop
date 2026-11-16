/**
 * The organism → assembly → gene-set scope selector -- behaviour. ONE implementation, used by
 * Annotation Search and the Data Exporter. Markup: includes/scope_selector.php. Data and
 * context: lib/scope_selector.php. Plan and history: notes/SHARED_SCOPE_SELECTOR_PLAN.md.
 *
 * Until 2026-11-16 each page carried its own copy (different class names, same logic), and every
 * scope fix had to be made twice -- the second copy was repeatedly missed. Page-specific work
 * goes in the onChange callback, never in here.
 *
 *   const scope = initScopeSelector({ context: scopeContext, onChange: () => { ... } });
 *   scope.checked()   // the ticked gene-set checkboxes (each carries data-org/-asm/-gs)
 *   scope.refresh()   // after ticking boxes from page code
 *
 * Every path that changes the selection -- a row click, the Selected panel's ×, the
 * select-all toggle, the context -- ends in changed(), which re-renders the panel, the counts
 * and the toggle's label, then calls onChange. That matters: those paths assign .checked, and
 * assigning .checked fires no change event, so a listener alone would miss most of them.
 *
 * Reads everything it needs from the CHECKBOXES (data-org / -asm / -gs / -label / -cn /
 * -asm-display), never from sibling DOM (CLAUDE.md §9b).
 *
 * @param {Object}   [opts]
 * @param {Object}   [opts.context]   {organisms: [...], assembly?, gene_set?} from
 *                                    moop_scope_context_from_request(), or null
 * @param {Function} [opts.onChange]  called after every change of selection, including the
 *                                    context's, with no arguments
 */
function initScopeSelector(opts = {}) {
    const list = document.getElementById('scope-org-list');
    if (!list) return null;

    const panelEl  = document.getElementById('scope-selected-panel');
    const countEl  = document.getElementById('scope-selected-count');
    const countsEl = document.getElementById('scope-counts');
    const filterEl = document.getElementById('scope-filter');
    const detailEl = document.getElementById('scope-show-detail');
    const onChange = opts.onChange || (() => {});

    const boxes   = () => Array.from(list.querySelectorAll('.scope-gs-cb'));
    const checked = () => boxes().filter((cb) => cb.checked);
    const detailOn = () => !!(detailEl && detailEl.checked);

    function tick(cb, on) {
        cb.checked = on;
        cb.closest('.scope-row')?.classList.toggle('selected', on);
    }

    function changed() {
        render();
        onChange();
    }

    // ── Read-outs: Selected panel, counts line, toggle label ────────────────────────────
    function render() {
        const all = boxes();
        const on  = all.filter((cb) => cb.checked);
        renderPanel(on);

        const allOn = all.length > 0 && on.length === all.length;
        const toggleLabel = document.querySelector('.scope-toggle-all-label');
        if (toggleLabel) toggleLabel.textContent = allOn ? moopT('Deselect all') : moopT('Select all');

        if (!countsEl) return;
        if (!on.length) {
            countsEl.textContent = moopT('Select at least one organism above');
            return;
        }
        // Organisms / assemblies / gene sets in full: one click on a collapsed row can take
        // four gene sets with it, and this is where that shows. Each count is its own phrase
        // (singular and plural as two literals, CLAUDE.md §7b) placed into one sentence.
        const orgs = new Set(on.map((c) => c.dataset.org)).size;
        const asms = new Set(on.map((c) => c.dataset.org + '|' + c.dataset.asm)).size;
        const gs   = on.length;
        countsEl.textContent = moopT('%s, %s, %s selected',
            orgs === 1 ? moopT('%d organism', orgs) : moopT('%d organisms', orgs),
            asms === 1 ? moopT('%d assembly', asms) : moopT('%d assemblies', asms),
            gs   === 1 ? moopT('%d gene set', gs)   : moopT('%d gene sets', gs));
    }

    function renderPanel(on) {
        if (!panelEl) return;
        // Grouped by organism, in list order, so the panel reads like the list beside it.
        const byOrg = {};
        on.forEach((cb) => {
            const org = cb.dataset.org;
            byOrg[org] ??= { label: cb.dataset.label || String(org).replace(/_/g, ' '), cn: cb.dataset.cn || '', rows: [] };
            byOrg[org].rows.push((cb.dataset.asmDisplay || cb.dataset.asm) + ' › ' + cb.dataset.gs);
        });
        const orgs = Object.keys(byOrg);
        if (countEl) countEl.textContent = orgs.length;

        if (!orgs.length) {
            // Says what to DO -- never "none = all organisms" (see the partial).
            panelEl.innerHTML =
                '<div class="text-muted small p-2 fst-italic">' + moopTh('None yet — pick at least one organism') + '</div>';
            return;
        }
        panelEl.innerHTML = orgs.map((org) => {
            const d = byOrg[org];
            // .sci-name, not <em>: organism names follow the site-wide italic rule.
            return `<div class="px-2 py-1 border-bottom">
                <div class="d-flex justify-content-between align-items-start">
                  <span><strong class="sci-name">${escapeHtml(d.label)}</strong>${
                      d.cn ? ' <span class="text-muted small">· ' + escapeHtml(d.cn) + '</span>' : ''
                  }</span>
                  <button type="button" class="btn btn-link btn-sm p-0 ms-2 text-danger flex-shrink-0 scope-panel-remove"
                          data-org="${escapeHtml(org)}" title="${moopTh('Remove')}"
                          aria-label="${moopTh('Remove %s from selection', d.label)}"><i class="fas fa-times"></i></button>
                </div>
                ${d.rows.map((r) => `<div class="text-muted ps-2" style="font-size:0.75rem;">› ${escapeHtml(r)}</div>`).join('')}
              </div>`;
        }).join('');
    }

    // ── Selecting ───────────────────────────────────────────────────────────────────────
    // Row click. In SIMPLE view a row stands for the whole organism (its gene-set rows are
    // collapsed into one), so a click takes or drops ALL of that organism's gene sets -- off
    // only when every one is already on; from a partial state (some picked in Details) it
    // takes them all. In DETAIL view each row is one gene set.
    list.addEventListener('click', (e) => {
        const row = e.target.closest('.scope-row');
        if (!row) return;
        const cb = row.querySelector('.scope-gs-cb');
        if (!cb || e.target === cb) return;
        if (detailOn()) {
            tick(cb, !cb.checked);
        } else {
            const orgCbs = boxes().filter((c) => c.dataset.org === cb.dataset.org);
            const allOn  = orgCbs.every((c) => c.checked);
            orgCbs.forEach((c) => tick(c, !allOn));
        }
        changed();
    });
    // A checkbox reached by keyboard / label.
    list.addEventListener('change', (e) => {
        if (!e.target.classList.contains('scope-gs-cb')) return;
        tick(e.target, e.target.checked);
        changed();
    });

    // The panel's × drops every gene set of that organism.
    panelEl?.addEventListener('click', (e) => {
        const btn = e.target.closest('.scope-panel-remove');
        if (!btn) return;
        boxes().filter((c) => c.dataset.org === btn.dataset.org).forEach((c) => tick(c, false));
        changed();
    });

    // One toggle instead of an All / None pair. Deselecting is immediate; selecting all goes
    // through the warning, because an every-organism run is the one we want chosen deliberately.
    document.querySelector('.scope-toggle-all')?.addEventListener('click', () => {
        const all = boxes();
        if (all.length && all.every((c) => c.checked)) {
            all.forEach((c) => tick(c, false));
            changed();
            return;
        }
        const n = document.getElementById('scope-select-all-count');
        if (n) n.textContent = all.length === 1 ? moopT('%d gene set', 1) : moopT('%d gene sets', all.length);
        const modalEl = document.getElementById('scope-select-all-modal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.getElementById('scope-select-all-confirm').onclick = () => {
            modal.hide();
            all.forEach((c) => tick(c, true));
            changed();
        };
        modal.show();
    });

    // ── Filter + Details ───────────────────────────────────────────────────────────────
    // Always matches the FULL text -- organism, common name, group, assembly AND gene set --
    // because that is what the placeholder promises. In simple view the assembly / gene-set
    // text is hidden, so a row matched only there is force-revealed (.scope-detail-forced) and
    // the match highlighted; without the highlight the row appears for no visible reason.
    function runFilter() {
        const q = (filterEl ? filterEl.value : '').trim().toLowerCase();
        const simpleView = !detailOn();
        list.querySelectorAll('.scope-row').forEach((row) => {
            const show = (display, forced, hl) => {
                row.style.display = display;
                row.classList.toggle('scope-detail-forced', forced);
                // Cleared on every non-matching branch too: a stale <mark> from the previous
                // keystroke would highlight text the current query never matched.
                highlightScopeDetail(row, hl);
            };
            // '' hands display back to the stylesheet, which keeps the secondary rows hidden
            // in simple view -- 'flex' here would undo the collapse.
            if (!q) return show('', false, '');
            const all    = row.dataset.search || '';
            const simple = row.dataset.searchSimple || all;
            const detail = row.dataset.searchDetail || '';
            if (!all.includes(q)) return show('none', false, '');
            const detailMatch = detail.includes(q);
            const detailOnly  = detailMatch && !simple.includes(q);
            // Simple view: a SECONDARY row shows only when the query hit its own assembly or gene
            // set; filtering by organism name must not re-expose the rows the collapse hid.
            if (simpleView && row.classList.contains('scope-row-secondary') && !detailOnly) {
                return show('none', false, '');
            }
            show('flex', simpleView && detailOnly, detailMatch ? q : '');
        });
    }
    filterEl?.addEventListener('input', runFilter);
    detailEl?.addEventListener('change', () => {
        list.classList.toggle('scope-detail-hidden', !detailEl.checked);
        runFilter();   // force-reveals depend on the view
    });

    // ── Context: arrived from an organism / assembly / gene-set / group page ────────────
    const ctx = opts.context;
    if (ctx && ctx.organisms && ctx.organisms.length) {
        const keep = new Set(ctx.organisms);
        boxes().forEach((cb) => {
            let on = keep.has(cb.dataset.org);
            if (on && ctx.assembly) on = cb.dataset.asm === ctx.assembly;
            if (on && ctx.gene_set) on = cb.dataset.gs  === ctx.gene_set;
            tick(cb, on);
        });
    }
    render();   // context or not: the read-outs start in step with the boxes

    return { checked, refresh: changed };
}

/**
 * Highlight the filter match inside a scope row's (normally hidden) assembly › gene-set text.
 *
 * Restores the ORIGINAL html when the query is empty. That original is captured once, on the
 * element itself, the first time the row is highlighted -- re-reading innerHTML after a
 * previous highlight would capture the <mark> tags and compound them on every keystroke. The
 * replace runs only on text OUTSIDE tags, so a query matching an attribute cannot corrupt markup.
 *
 * @param {Element} row    the scope row
 * @param {string}  query  the (lowercased) filter text; '' restores the original
 */
function highlightScopeDetail(row, query) {
    const detail = row ? row.querySelector('.scope-row-detail') : null;
    if (!detail) return;

    if (detail._moopOrigHtml === undefined) {
        if (!query) return;   // never highlighted: nothing to restore
        detail._moopOrigHtml = detail.innerHTML;
    }
    const orig = detail._moopOrigHtml;
    if (!query) { detail.innerHTML = orig; return; }

    const pattern = escapeRegExp(query);
    detail.innerHTML = orig.replace(/([^<>]+)/g, (text) =>
        text.replace(new RegExp(pattern, 'gi'), (m) => `<mark class="scope-hl">${m}</mark>`)
    );
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { initScopeSelector, highlightScopeDetail };
}

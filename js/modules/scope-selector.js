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
 * SINGLE mode (Retrieve Sequences, BLAST, Primer BLAST -- tools that work on exactly one gene
 * set): the inputs are radios named selected_source; a click MOVES the one selection; clicking a
 * collapsed organism that has several gene sets turns on Details instead, so the exact gene set
 * is picked; the readout is one line ("Selected: organism › assembly › gene set"). What to
 * pre-select comes checked from the server (includes/scope_selector.php), not from `context`.
 *
 * @param {Object}   [opts]
 * @param {string}   [opts.mode]      'multi' (default) or 'single'
 * @param {Object}   [opts.context]   multi mode: {organisms: [...], assembly?, gene_set?} from
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
    const deselectEl = document.getElementById('scope-deselect');   // single mode only
    const onChange = opts.onChange || (() => {});
    const single   = opts.mode === 'single';

    const boxes   = () => Array.from(list.querySelectorAll('.scope-gs-cb'));
    const checked = () => boxes().filter((cb) => cb.checked);
    const detailOn = () => !!(detailEl && detailEl.checked);

    function tick(cb, on) {
        cb.checked = on;
        cb.closest('.scope-row')?.classList.toggle('selected', on);
    }

    // Single mode: make cb the one selection.
    function selectOnly(cb) {
        boxes().forEach((c) => tick(c, c === cb));
    }

    // Single mode, simple view: the chosen gene set may sit in a hidden secondary row, so its
    // organism's visible row carries the highlight instead.
    function syncSingleHighlight() {
        if (!single) return;
        const on = checked()[0];
        list.querySelectorAll('.scope-row').forEach((row) => {
            const cb = row.querySelector('.scope-gs-cb');
            const own = cb === on;
            const viaOrg = !detailOn() && on && !row.classList.contains('scope-row-secondary')
                && row.dataset.org === on.dataset.org;
            row.classList.toggle('selected', own || !!viaOrg);
        });
    }

    function setDetails(on) {
        if (!detailEl) return;
        detailEl.checked = on;
        list.classList.toggle('scope-detail-hidden', !on);
        runFilter();
        syncSingleHighlight();
    }

    // Scroll the list (not the page) so a row sits in the middle of it.
    function scrollToRow(row) {
        if (!row) return;
        list.scrollTop += row.getBoundingClientRect().top - list.getBoundingClientRect().top
            - (list.clientHeight - row.offsetHeight) / 2;
    }

    function changed() {
        render();
        onChange();
    }

    // ── Read-outs: Selected panel, counts line, toggle label ────────────────────────────
    function render() {
        const all = boxes();
        const on  = all.filter((cb) => cb.checked);
        if (single) {
            syncSingleHighlight();
            if (!countsEl) return;
            const cb = on[0];
            deselectEl?.classList.toggle('d-none', !cb);
            if (!cb) { countsEl.textContent = moopT('None selected'); return; }
            countsEl.innerHTML = moopTm('Selected: %s',
                '<em>' + escapeHtml(cb.dataset.label) + '</em> › ' + escapeHtml(cb.dataset.asmDisplay || cb.dataset.asm)
                + ' › ' + escapeHtml(cb.dataset.gsDisplay || cb.dataset.gs));
            return;
        }
        renderPanel(on);
        renderSummary(all, on);

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

    // The closed "Refine selection" bar (collapsible multi mode) says what the search covers.
    function renderSummary(all, on) {
        const el = document.getElementById('scope-summary');
        if (!el) return;
        if (el.dataset.unit === 'gene_set') {   // one organism: count its gene sets
            el.textContent = !on.length ? moopT('Nothing selected — pick at least one gene set')
                : on.length === all.length ? moopT('Searching all %d gene sets', all.length)
                : moopT('Searching %d of %d gene sets', on.length, all.length);
            return;
        }
        const total = new Set(all.map((c) => c.dataset.org)).size;
        const n     = new Set(on.map((c) => c.dataset.org)).size;
        el.textContent = !n ? moopT('Nothing selected — pick at least one organism')
            : (n === total && on.length === all.length) ? moopT('Searching all %d organisms', total)
            : moopT('Searching %d of %d organisms', n, total);
    }

    // "assembly › gene set" as the panel prints it.
    const gsText = (cb) => (cb.dataset.asmDisplay || cb.dataset.asm) + ' › ' + (cb.dataset.gsDisplay || cb.dataset.gs);

    function renderPanel(on) {
        if (!panelEl) return;
        // Grouped by organism, in list order, so the panel reads like the list beside it.
        // An organism with several gene sets lists ALL of them, each with a checkbox, so one can
        // be dropped or added back here without the Details switch (Sofia, 2026-11-20). One with
        // a single gene set keeps a plain line: a checkbox there would only repeat the ×.
        const byOrg = {};
        on.forEach((cb) => {
            const org = cb.dataset.org;
            byOrg[org] ??= { label: cb.dataset.label || String(org).replace(/_/g, ' '), cn: cb.dataset.cn || '',
                             all: boxes().filter((c) => c.dataset.org === org) };
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
                ${d.all.length === 1
                    ? `<div class="text-muted ps-2" style="font-size:0.75rem;">› ${escapeHtml(gsText(d.all[0]))}</div>`
                    : d.all.map((c) => `<label class="d-flex align-items-start gap-1 ps-2 mb-0 ${c.checked ? '' : 'text-muted'}" style="font-size:0.75rem; cursor:pointer;">
                        <input type="checkbox" class="form-check-input mt-0 flex-shrink-0 scope-panel-gs"
                               data-org="${escapeHtml(org)}" data-asm="${escapeHtml(c.dataset.asm)}" data-gs="${escapeHtml(c.dataset.gs)}"${c.checked ? ' checked' : ''}>
                        <span>${escapeHtml(gsText(c))}</span></label>`).join('')}
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
        if (single) {
            const orgCbs = boxes().filter((c) => c.dataset.org === cb.dataset.org);
            if (!detailOn() && orgCbs.length > 1) {
                // An organism with several gene sets: show them, and let the visitor pick one.
                setDetails(true);
                scrollToRow(orgCbs.find((c) => c.checked)?.closest('.scope-row') || orgCbs[0].closest('.scope-row'));
                return;
            }
            // Clicking the chosen row again deselects it (nothing chosen: each tool refuses to run
            // and says so). It used to do nothing, which on a page that arrived filtered to a
            // one-gene-set organism left no way to clear the choice (Sofia, 2026-11-16).
            if (cb.checked) tick(cb, false); else selectOnly(cb);
        } else if (detailOn() || row.classList.contains('scope-detail-forced')) {
            // A row the filter revealed for its assembly / gene set ("S3h1") SHOWS one gene
            // set, so a click means that one -- taking the whole organism here selected six
            // gene sets behind a row naming one (Sofia, 2026-11-20).
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
        if (single) selectOnly(e.target); else tick(e.target, e.target.checked);
        changed();
    });

    // A gene-set checkbox in the panel ticks the same gene set in the list. Unticking an
    // organism's last one drops the organism from the panel, as the × does.
    panelEl?.addEventListener('change', (e) => {
        const box = e.target.closest('.scope-panel-gs');
        if (!box) return;
        const cb = boxes().find((c) => c.dataset.org === box.dataset.org
            && c.dataset.asm === box.dataset.asm && c.dataset.gs === box.dataset.gs);
        if (!cb) return;
        tick(cb, box.checked);
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
    filterEl?.addEventListener('input', () => {
        runFilter();
        // Single mode: emptying the filter brings the whole list back -- show where the
        // chosen row is in it.
        if (single && !filterEl.value.trim()) scrollToRow(list.querySelector('.scope-row.selected'));
    });

    // "Show all", beside the filter on every page: clears the filter, and in single mode lifts an
    // organisms-list limit ("chosen on the previous page") too.
    function showAll() {
        list.classList.remove('scope-limited');
        document.getElementById('scope-only-note')?.remove();
        if (filterEl && filterEl.value) { filterEl.value = ''; runFilter(); }
        if (single) scrollToRow(list.querySelector('.scope-row.selected'));
    }
    document.getElementById('scope-show-all')?.addEventListener('click', showAll);

    // Single mode: the Deselect button beside the readout.
    deselectEl?.addEventListener('click', () => {
        boxes().forEach((c) => tick(c, false));
        changed();
    });
    detailEl?.addEventListener('change', () => {
        list.classList.toggle('scope-detail-hidden', !detailEl.checked);
        runFilter();   // force-reveals depend on the view
        syncSingleHighlight();
    });

    // ── Context: arrived from an organism / assembly / gene-set / group page ────────────
    const ctx = single ? null : opts.context;
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
    if (filterEl && filterEl.value.trim()) runFilter();   // a filter pre-filled by the server
    if (single) scrollToRow(list.querySelector('.scope-row.selected'));

    // The organisms with at least one gene set selected.
    function organisms() {
        return [...new Set(checked().map((c) => c.dataset.org))];
    }

    // The selection as AnnotationSearch's selectedScope: {organism: {assembly: {gene_set: bool}}}
    // for the selected organisms, or null when nothing is excluded (= no gene-set filter).
    function scope() {
        const on = organisms();
        const out = {};
        let excluded = false;
        boxes().forEach((cb) => {
            if (!on.includes(cb.dataset.org)) return;
            ((out[cb.dataset.org] ??= {})[cb.dataset.asm] ??= {})[cb.dataset.gs] = cb.checked;
            if (!cb.checked) excluded = true;
        });
        return excluded ? out : null;
    }

    return { checked, organisms, scope, refresh: changed };
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

/**
 * MOOPmart — Gene List Builder UI
 *
 * Requires globals injected via inline_scripts:
 *   annotationSources — [source_name, ...]
 *   moopSite          — '/moop' (no trailing slash)
 *   scopeContext      — {organisms:[...], assembly?, gene_set?} | null (lib/scope_selector.php)
 */

(function () {
    'use strict';

    // A whole sentence per count form, in the visitor's language (moopT, js/modules/utilities.js):
    // n === 1 picks the singular. Plain text; nT() for text going into HTML.
    const nT  = (n, one, many, ...args) => moopT(n === 1 ? one : many, ...args);
    const nTh = (n, one, many, ...args) => moopTh(n === 1 ? one : many, ...args);

    // Preview fans out one request per organism so results fill in progressively.
    const PREVIEW_ORG_URL = moopSite + '/api/moopmart_preview_organism.php';
    const EXPORT_URL      = moopSite + '/api/moopmart_export.php';
    const CHRS_URL        = moopSite + '/api/moopmart_chrs.php';
    const EXPR_GENES_URL  = moopSite + '/api/moopmart_expression_genes.php';
    const EXPRESSION_URL  = moopSite + '/tools/expression.php';

    // Max rows rendered in the progressive preview table (count stays exact).
    const PREVIEW_ROW_CAP = 100;

    // -------------------------------------------------------
    // Step 1 — Organism scope (flat list)
    // -------------------------------------------------------

    // The list, filter, Details, select-all, Selected panel and counts are the shared
    // component (js/modules/scope-selector.js, also Annotation Search's). This page only
    // reacts to a changed selection: Step 3's availability counts, the Send-to-Expression
    // gate, and the location filter (single assembly only).
    function onScopeChange() {
        updateExprState();
        refreshAnnotationCounts();
        updateCoordState();
    }

    // -------------------------------------------------------
    // Step 3 — annotation availability counts for the selected organisms
    // -------------------------------------------------------

    let annCountTimer = null;
    let annCountSeq   = 0;   // discard out-of-order responses
    // Last computed availability, cached so a criterion row added AFTER counting still gets
    // its dropdown labelled without re-fetching.
    let lastAnnCounts     = null;   // { sourceName: n }
    let lastAnnTypeTotals = null;   // { typeName: n }

    // Write availability counts into the By-Annotation source dropdowns, so a user can see
    // whether a type/source has many annotations or none for the organisms they picked —
    // choosing an empty one silently shrinks their list, which is the whole reason for this.
    // Options keep their value (the source name); only the visible text changes, and a
    // zero-count source is disabled so it cannot be chosen unknowingly (unless already picked).
    // The full type -> sources list, read once from the page's markup (the server's labels and
    // order). Each row's source list is rebuilt from it, never from another row's current state.
    let annModel = null;
    function getAnnModel() {
        if (annModel) return annModel;
        annModel = [];
        const sel = document.querySelector('.mm-ann-src-select');
        sel?.querySelectorAll('optgroup').forEach(og => annModel.push({
            type: og.dataset.type || og.label,
            sources: Array.from(og.querySelectorAll('option')).map(o => ({ value: o.value, label: o.dataset.label || o.value })),
        }));
        return annModel;
    }

    // A row's source list: only the chosen type's sources, and once the organisms are known only
    // those they have (a source already picked stays, so a choice never vanishes under the user).
    // No type chosen = no source list: "any annotation type" needs no second choice.
    function rebuildSourceSelect(row) {
        const typeSel = row.querySelector('.mm-ann-type-select');
        const srcSel  = row.querySelector('.mm-ann-src-select');
        if (!typeSel || !srcSel) return;
        const keep  = srcSel.value;
        const entry = getAnnModel().find(e => e.type === typeSel.value);
        srcSel.innerHTML = '';
        const any = document.createElement('option');
        any.value = ''; any.textContent = moopT('Any source');
        srcSel.appendChild(any);
        if (!entry) { srcSel.value = ''; srcSel.hidden = true; return; }
        entry.sources.forEach(src => {
            const n = lastAnnCounts ? (lastAnnCounts[src.value] || 0) : null;
            if (n === 0 && src.value !== keep) return;
            const opt = document.createElement('option');
            opt.value = src.value;   // the stored name, exactly (never trimmed)
            opt.textContent = n === null ? src.label : `${src.label} (${n.toLocaleString()})`;
            srcSel.appendChild(opt);
        });
        srcSel.value = Array.from(srcSel.options).some(o => o.value === keep) ? keep : '';
        srcSel.hidden = false;
    }

    // Gene counts on the type list for the organisms picked in Step 1; a type they lack is
    // disabled (unless already chosen) so it cannot be picked unknowingly. Then every row's
    // source list follows.
    function applyAnnDropdownCounts() {
        document.querySelectorAll('.mm-ann-type-select').forEach(sel => {
            sel.querySelectorAll('option').forEach(opt => {
                if (!opt.value) return;                     // "Any annotation type"
                const label = opt.dataset.label || opt.value;
                if (lastAnnTypeTotals) {
                    const t = lastAnnTypeTotals[opt.value] || 0;
                    opt.textContent = t === 1 ? moopT('%s (1 gene)').replace('%s', label)
                                              : moopT('%s (%d genes)').replace('%s', label).replace('%d', t.toLocaleString());
                    opt.disabled = (t === 0 && !opt.selected);
                } else {
                    opt.textContent = label;
                    opt.disabled = false;
                }
            });
        });
        document.querySelectorAll('.mm-ann-criterion').forEach(rebuildSourceSelect);
    }

    // Update the per-source count badges in Step 3 to reflect the organisms selected
    // in Step 1. Counts are across ALL genes of those organisms (not the gene filter),
    // so a source with a count but an empty preview means the current gene subset lacks
    // it, while 0 means the selected organisms have none. Reuses the annotation-search
    // pages' endpoint. Debounced so bulk (de)selection fires a single request.
    function refreshAnnotationCounts() {
        clearTimeout(annCountTimer);
        annCountTimer = setTimeout(() => {
            const badges  = document.querySelectorAll('.mm-ann-count');
            const summary = document.getElementById('mm-ann-counts');
            const orgs    = getSelectedOrganisms();
            if (!orgs.length) {
                // No selection yet — clear counts and show the full list (subject to text filter)
                badges.forEach(b => { b.textContent = ''; b.classList.add('d-none'); });
                document.querySelectorAll('.mm-ann-item').forEach(it => it.classList.remove('mm-ann-zerocount'));
                lastAnnCounts = null; lastAnnTypeTotals = null;
                applyAnnDropdownCounts();   // reset dropdowns to plain source names
                applyAnnTypeGenes();
                applyAnnVisibility();
                if (summary) summary.textContent = '';
                return;
            }
            if (summary) summary.textContent = moopT('Counting annotations…');
            const seq = ++annCountSeq;
            // Counts follow the GENE SETS ticked, not just the organisms: for an organism with
            // only some of its gene sets ticked, name those (same parameter as the search page).
            const partial = partialGeneSets();
            const url = `${moopSite}/tools/get_annotation_sources_grouped.php?organisms=`
                      + encodeURIComponent(orgs.join(','))
                      + (partial.length ? '&gene_sets=' + encodeURIComponent(JSON.stringify(partial)) : '');
            fetch(url)
                .then(r => r.json())
                .then(data => {
                    if (seq !== annCountSeq) return;   // superseded by a newer selection
                    // Counts are GENES carrying each source; a type's total is its own
                    // distinct figure (td.genes), never the sum of its sources -- one gene with
                    // both Pfam and InterPro is one Domains gene. data.counts is null while the
                    // background rebuild is unfinished: show names only, disable nothing.
                    const known = data.counts === 'genes';
                    const counts = {};
                    const typeTotals = {};
                    Object.entries(data.source_types || {}).forEach(([typeName, td]) => {
                        typeTotals[typeName] = td.genes || 0;
                        (td.sources || []).forEach(s => { counts[s.name] = s.count || 0; });
                    });
                    lastAnnCounts = known ? counts : null;
                    lastAnnTypeTotals = known ? typeTotals : null;
                    applyAnnDropdownCounts();
                    badges.forEach(b => {
                        if (!known) { b.textContent = ''; b.classList.add('d-none'); return; }
                        const n = counts[b.dataset.src] || 0;
                        // Mark sources with no annotations for this selection; applyAnnVisibility() hides them
                        b.closest('.mm-ann-item')?.classList.toggle('mm-ann-zerocount', n === 0);
                        if (n > 0) {
                            b.textContent = n.toLocaleString();
                            b.classList.remove('d-none', 'bg-light', 'text-muted');
                            b.classList.add('bg-secondary');
                        } else {
                            b.textContent = '';
                            b.classList.add('d-none');
                        }
                    });
                    // A column from outside organism.sqlite (curated anatomical expression) has no
                    // count: offer it when a selected organism has that data (data-orgs).
                    document.querySelectorAll('.mm-ann-col[data-orgs]').forEach(cb => {
                        const has = cb.dataset.orgs.split(',').some(o => orgs.includes(o));
                        cb.closest('.mm-ann-item')?.classList.toggle('mm-ann-zerocount', !has);
                    });
                    applyAnnTypeGenes();
                    applyAnnVisibility();
                    // The source total is in the heading (updateAnnSummary); this line only says what
                    // the numbers mean, or that there is nothing.
                    if (summary) {
                        summary.textContent = (data.total_sources || 0) === 0
                            ? moopT('No annotations for the selected organisms')
                            : (known ? moopT('Numbers are genes in the selected organisms that carry each type or source.') : '');
                    }
                })
                .catch(() => { if (summary) summary.textContent = ''; /* keep prior badges */ });
        }, 250);
    }

    // In-scope = the organisms picked in Step 1 have it (.mm-ann-zerocount marks the rest). Only
    // in-scope sources are listed, ticked by a type, counted, or sent -- in the wide layout every
    // sent source is two columns, so an out-of-scope one would be an empty pair.
    function inScopeSources(type) {
        const sel = type ? `.mm-ann-col[data-type="${CSS.escape(type)}"]` : '.mm-ann-col';
        return Array.from(document.querySelectorAll(sel))
            .filter(cb => !cb.closest('.mm-ann-item')?.classList.contains('mm-ann-zerocount'));
    }

    // "(N genes)" beside each type, from the same count the Step 2 type list shows.
    function applyAnnTypeGenes() {
        document.querySelectorAll('.mm-ann-group').forEach(group => {
            const el = group.querySelector('.mm-ann-type-genes');
            if (!el) return;
            const t = lastAnnTypeTotals ? (lastAnnTypeTotals[group.dataset.type] || 0) : null;
            el.textContent = (t === null || t === 0) ? ''
                : (t === 1 ? moopT('1 gene') : moopT('%d genes').replace('%d', t.toLocaleString()));
        });
    }

    // Types are listed closed (11 rows, not ~185). A source shows when it is in scope and passes
    // the text filter; a type shows when any source does. While a filter is typed, the types
    // with a matching SOURCE open so the match is visible; a match on the type name alone does
    // not open it. Clearing the filter returns each type to the state the user left it in.
    function applyAnnVisibility() {
        const q = (document.getElementById('mm-ann-filter')?.value || '').trim().toLowerCase();
        document.querySelectorAll('.mm-ann-group').forEach(group => {
            const typeText = (group.querySelector('.mm-ann-type-row label')?.textContent || '').toLowerCase();
            const typeHit  = q !== '' && typeText.includes(q);
            let inScope = 0, srcHit = false;
            group.querySelectorAll('.mm-ann-item').forEach(item => {
                const ok = !item.classList.contains('mm-ann-zerocount');
                if (ok) inScope++;
                const passText = !q || typeHit || (item.querySelector('label')?.textContent.toLowerCase() || '').includes(q);
                if (ok && q && !typeHit && passText) srcHit = true;
                item.classList.toggle('d-none', !(ok && passText));
            });
            group.classList.toggle('d-none', inScope === 0 || (q !== '' && !typeHit && !srcHit));
            const nsrc = group.querySelector('.mm-ann-nsrc');
            if (nsrc) nsrc.textContent = inScope === 1 ? moopT('1 source') : moopT('%d sources').replace('%d', inScope);
            const open = group.classList.contains('mm-ann-open') || srcHit;
            group.querySelector('.mm-ann-sources')?.classList.toggle('d-none', !open);
            const btn = group.querySelector('.mm-ann-open-btn');
            if (btn) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                btn.querySelector('i')?.classList.toggle('fa-rotate-180', open);
            }
            syncAnnTypeCb(group.dataset.type);
        });
        updateAnnSummary();
    }

    // -------------------------------------------------------
    // Step 1 — Coordinate filter (single-assembly only)
    // -------------------------------------------------------

    let lastChrSource = null;

    function getSelectedAssemblies() {
        const seen = new Set();
        document.querySelectorAll('.scope-gs-cb:checked').forEach(cb => {
            seen.add(cb.dataset.org + '|' + cb.dataset.asm);
        });
        return Array.from(seen);
    }

    function updateCoordState() {
        const asms   = getSelectedAssemblies();
        const single = asms.length === 1;
        const note   = document.getElementById('mm-coord-note');
        ['mm-coord-chr', 'mm-coord-start', 'mm-coord-end'].forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = !single;
            if (!single) el.value = '';
        });
        if (note) {
            // innerHTML so "Step 1" renders with the small step badge; content is static.
            note.innerHTML = single
                ? ''
                : moopTm('Select exactly one assembly in Step %s to enable location search.', '<span class="step-ref">1</span>');
        }
        if (!single) { lastChrSource = null; return; }

        // Load chr names for the selected assembly
        const cb = Array.from(document.querySelectorAll('.scope-gs-cb:checked'))[0];
        const key = cb ? `${cb.dataset.org}|${cb.dataset.asm}|${cb.dataset.gs}` : null;
        if (!key || key === lastChrSource) return;
        lastChrSource = key;

        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd   = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('source', key);
        fetch(CHRS_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(r => r.json())
            .then(data => {
                const dl = document.getElementById('mm-chr-datalist');
                if (!dl) return;
                dl.innerHTML = (data.chrs || []).map(c => `<option value="${c}">`).join('');
            })
            .catch(() => {});
    }

    // -------------------------------------------------------
    // Step 2 — Annotation criteria rows (add / remove)
    // -------------------------------------------------------

    function initAnnCriteria() {
        const container = document.getElementById('mm-ann-criteria');
        if (!container) return;

        document.getElementById('mm-add-criterion')?.addEventListener('click', function () {
            // A copy of the first row, emptied -- one source for the row's markup (translated, and
            // it cannot drift from the server's), plus a remove button.
            const newRow = container.querySelector('.mm-ann-criterion').cloneNode(true);
            newRow.querySelectorAll('select').forEach(sel => { sel.value = ''; sel.querySelectorAll('option').forEach(o => o.removeAttribute('selected')); });
            newRow.querySelectorAll('input[type=text]').forEach(inp => { inp.value = ''; inp.removeAttribute('value'); });
            newRow.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = false; cb.removeAttribute('checked'); });
            const last = newRow.lastElementChild;
            last.className = 'col-sm-1 text-end';
            last.innerHTML = '<button type="button" class="btn btn-sm btn-outline-danger py-0 mm-remove-criterion"><i class="fa fa-times"></i></button>';
            last.firstChild.title = moopT('Remove');
            container.appendChild(newRow);
            // The cloned dropdown starts label-less; give it the counts already computed for
            // the current organism selection so it matches the rows above it.
            applyAnnDropdownCounts();
        });

        container.addEventListener('click', function (e) {
            const btn = e.target.closest('.mm-remove-criterion');
            if (btn) btn.closest('.mm-ann-criterion').remove();
        });
        // A new type: its own sources, starting from "Any source".
        container.addEventListener('change', function (e) {
            if (!e.target.classList.contains('mm-ann-type-select')) return;
            const row = e.target.closest('.mm-ann-criterion');
            const src = row.querySelector('.mm-ann-src-select');
            if (src) src.value = '';
            rebuildSourceSelect(row);
        });
        getAnnModel();   // before any row is rebuilt
        container.querySelectorAll('.mm-ann-criterion').forEach(rebuildSourceSelect);
    }

    // -------------------------------------------------------
    // Step 3 — Feature column list (ordered, toggleable)
    // -------------------------------------------------------

    function renumberColList(list, startAt = 1) {
        let n = startAt;
        list.querySelectorAll('.mm-col-item').forEach(item => {
            const badge = item.querySelector('.mm-col-num');
            const label = item.querySelector('.mm-col-label');
            if (item.classList.contains('selected')) {
                badge.textContent = n++;
                badge.style.visibility = 'visible';
                item.style.background = 'rgba(var(--moop-main-rgb),0.09)';
                item.style.borderColor = 'var(--moop-tool-header)';
                label.style.color = '';
            } else {
                badge.style.visibility = 'hidden';
                item.style.background = '#fff';
                item.style.borderColor = '#dee2e6';
                label.style.color = '#adb5bd';
            }
        });
        return n; // next available number
    }

    function renumberBothColLists() {
        const featList = document.getElementById('mm-feat-col-list');
        const annList  = document.getElementById('mm-ann-col-list');
        const next = featList ? renumberColList(featList, 1) : 1;
        if (annList) renumberColList(annList, next);
    }

    function initColList() {
        ['mm-feat-col-list', 'mm-ann-col-list'].forEach(id => {
            const list = document.getElementById(id);
            if (!list) return;

            list.querySelectorAll('.mm-col-item').forEach(item => item.classList.add('selected'));

            list.addEventListener('click', function (e) {
                const item = e.target.closest('.mm-col-item');
                if (!item) return;

                if (e.target.closest('.mm-col-up')) {
                    const prev = item.previousElementSibling;
                    if (prev) list.insertBefore(item, prev);
                } else if (e.target.closest('.mm-col-down')) {
                    const next = item.nextElementSibling;
                    if (next) list.insertBefore(next, item);
                } else {
                    item.classList.toggle('selected');
                }
                renumberBothColLists();
            });
        });
        renumberBothColLists();
    }

    // -------------------------------------------------------
    // Step 2 — Accordion header → panel connection
    // -------------------------------------------------------

    function initAccordionHeaders() {
        // Sync browse-select-header aria-expanded → square bottom corners on its panel
        document.querySelectorAll('.browse-select-header[data-bs-toggle="collapse"]').forEach(header => {
            const targetId = header.dataset.bsTarget?.replace('#', '');
            if (!targetId) return;
            const panel = document.getElementById(targetId);
            if (!panel) return;
            panel.addEventListener('show.bs.collapse', () => header.classList.add('header-open'));
            panel.addEventListener('hide.bs.collapse', () => header.classList.remove('header-open'));
        });
    }

    // -------------------------------------------------------
    // Step 3 — Wide / Long switch label colours
    // -------------------------------------------------------

    // Long/Wide is a segmented pill now (name="mm-layout"); the selected radio is styled by
    // CSS, so there is nothing to sync in JS. Kept as a no-op so the call site is untouched.
    function initWideLongSwitch() {}

    // Read the current table layout from the segmented pill.
    function annLayout() {
        return document.querySelector('input[name="mm-layout"]:checked')?.value === 'wide' ? 'wide' : 'long';
    }
    // Read the current output format from the segmented pill.
    function outputFormat() {
        return document.querySelector('input[name="mm-format"]:checked')?.value === 'fasta' ? 'fasta' : 'tsv';
    }

    // -------------------------------------------------------
    // Step 3 — Output format toggle (TSV / FASTA)
    // -------------------------------------------------------

    function initFormatToggle() {
        const radios    = document.querySelectorAll('input[name="mm-format"]');
        const tsvOpts   = document.getElementById('mm-tsv-options');
        const fastaOpts = document.getElementById('mm-fasta-options');
        const dlLabel   = document.getElementById('mm-dl-label');
        if (!radios.length) return;

        function updateFormat() {
            const isFasta = outputFormat() === 'fasta';
            tsvOpts?.classList.toggle('d-none', isFasta);
            fastaOpts?.classList.toggle('d-none', !isFasta);
            if (dlLabel) dlLabel.textContent = isFasta ? moopT('Download FASTA') : moopT('Download TSV');
            // Clear stale preview when switching formats
            document.getElementById('mm-results-section')?.classList.add('d-none');
            document.getElementById('mm-fasta-preview-section')?.classList.add('d-none');
        }

        radios.forEach(r => r.addEventListener('change', updateFormat));
        updateFormat();

        // Wide <-> Long changes the table's shape: a preview in the other layout is stale.
        document.querySelectorAll('input[name="mm-layout"]').forEach(r => r.addEventListener('change', () => {
            document.getElementById('mm-results-section')?.classList.add('d-none');
        }));

        // FASTA sequence type → show/hide flank input
        document.querySelectorAll('.mm-fasta-mode').forEach(radio => {
            radio.addEventListener('change', function () {
                const flankWrap = document.getElementById('mm-flank-wrap');
                if (flankWrap) {
                    flankWrap.classList.toggle('d-none', !['upstream', 'downstream'].includes(this.value));
                }
            });
        });
    }

    // -------------------------------------------------------
    // Step 3 — Annotation sources panel
    // -------------------------------------------------------

    // "3 of 24 sources" beside the heading. Its own element: the line under the panel belongs to
    // the availability count (refreshAnnotationCounts), and the two used to overwrite each other.
    function updateAnnSummary() {
        const el = document.getElementById('mm-ann-selected');
        if (!el) return;
        const srcs    = inScopeSources();
        const checked = srcs.filter(c => c.checked).length;
        el.textContent = !srcs.length ? ''
            : (checked === 0 ? moopT('none ticked')
               : moopT('%1$d of %2$d sources ticked', checked, srcs.length));
    }

    function syncAnnTypeCb(type) {
        const sources = inScopeSources(type);
        const typeCb  = document.querySelector(`.mm-ann-type-cb[data-type="${CSS.escape(type)}"]`);
        if (!typeCb || !sources.length) return;
        const allOn  = sources.every(c => c.checked);
        const allOff = sources.every(c => !c.checked);
        typeCb.checked       = allOn;
        typeCb.indeterminate = !allOn && !allOff;
    }

    function initAnnSources() {
        const panel = document.getElementById('mm-ann-panel');
        if (!panel) return;

        panel.addEventListener('change', function (e) {
            const cb = e.target;
            if (cb.classList.contains('mm-ann-type-cb')) {
                inScopeSources(cb.dataset.type).forEach(c => { c.checked = cb.checked; });
                cb.indeterminate = false;
            } else if (cb.classList.contains('mm-ann-col')) {
                syncAnnTypeCb(cb.dataset.type);
            }
            updateAnnSummary();
        });

        // Open / close one type's source list
        panel.addEventListener('click', function (e) {
            const btn = e.target.closest('.mm-ann-open-btn');
            if (!btn) return;
            btn.closest('.mm-ann-group')?.classList.toggle('mm-ann-open');
            applyAnnVisibility();
        });

        document.getElementById('mm-ann-all')?.addEventListener('click', () => {
            inScopeSources().forEach(c => c.checked = true);
            document.querySelectorAll('.mm-ann-type-cb').forEach(c => syncAnnTypeCb(c.dataset.type));
            updateAnnSummary();
        });
        document.getElementById('mm-ann-none')?.addEventListener('click', () => {
            document.querySelectorAll('.mm-ann-col, .mm-ann-type-cb').forEach(c => { c.checked = false; c.indeterminate = false; });
            updateAnnSummary();
        });

        document.getElementById('mm-ann-filter')?.addEventListener('input', applyAnnVisibility);

        applyAnnVisibility();
    }

    // -------------------------------------------------------
    // Payload builders
    // -------------------------------------------------------

    function getSelectedSources() {
        return Array.from(document.querySelectorAll('.scope-gs-cb:checked'))
            .map(c => `${c.dataset.org}|${c.dataset.asm}|${c.dataset.gs}`);
    }

    // Distinct organism dirs among the selected sources — the fan-out unit for the
    // progressive preview (one request per organism).
    // "org/accession/gene_set" for every ticked gene set of an organism ticked only in part.
    // A fully ticked organism is left out, so a broad selection keeps the URL short.
    function partialGeneSets() {
        const byOrg = {};
        document.querySelectorAll('.scope-gs-cb').forEach(cb => (byOrg[cb.dataset.org] ??= []).push(cb));
        const out = [];
        Object.values(byOrg).forEach(boxes => {
            const on = boxes.filter(cb => cb.checked);
            if (on.length && on.length < boxes.length) {
                on.forEach(cb => out.push(`${cb.dataset.org}/${cb.dataset.asm}/${cb.dataset.gs}`));
            }
        });
        return out;
    }

    function getSelectedOrganisms() {
        const seen = new Set();
        document.querySelectorAll('.scope-gs-cb:checked').forEach(c => seen.add(c.dataset.org));
        return Array.from(seen);
    }

    function parseIds(raw) {
        // Accept comma, whitespace, or newline separated IDs
        return raw.split(/[\s,]+/).map(s => s.trim()).filter(Boolean);
    }

    function buildFormData(extra = {}) {
        const fd   = new FormData();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        fd.append('csrf_token', csrf);

        getSelectedSources().forEach(s => fd.append('sources[]', s));

        // Section: Feature IDs
        const rawIds = document.getElementById('mm-feature-ids')?.value?.trim();
        if (rawIds) parseIds(rawIds).forEach(id => fd.append('feature_ids[]', id));

        // Section: Name
        const name = document.getElementById('mm-gene-name')?.value?.trim();
        if (name) fd.append('gene_name', name);

        // Section: Description
        const desc = document.getElementById('mm-gene-description')?.value?.trim();
        if (desc) fd.append('gene_description', desc);

        // Section: Wide/Long toggle
        const annFormat = annLayout();
        fd.append('ann_format', annFormat);

        // Section: Annotation — repeatable criteria rows (AND between them)
        document.querySelectorAll('.mm-ann-criterion').forEach(row => {
            // The source name exactly as listed -- not trimmed: a name is matched as stored, and
            // eight databases told two sources apart only by a trailing space.
            const src  = row.querySelector('.mm-ann-src-select')?.value || '';
            const type = row.querySelector('.mm-ann-type-select')?.value || '';
            const acc = row.querySelector('.mm-ann-accession')?.value?.trim() || '';
            const kw  = row.querySelector('.mm-ann-keyword')?.value?.trim() || '';
            const sub = row.querySelector('.mm-ann-sub')?.checked ? '1' : '0';
            if (src || type || acc || kw) {
                fd.append('ann_criteria_type[]', type);
                fd.append('ann_criteria_src[]', src);
                fd.append('ann_criteria_acc[]', acc);
                fd.append('ann_criteria_kw[]',  kw);
                fd.append('ann_criteria_sub[]', sub);
            }
        });

        // Section: Location
        const chr   = document.getElementById('mm-coord-chr')?.value?.trim();
        const start = document.getElementById('mm-coord-start')?.value;
        const end   = document.getElementById('mm-coord-end')?.value;
        if (chr)   fd.append('coord_chr',   chr);
        if (start) fd.append('coord_start', start);
        if (end)   fd.append('coord_end',   end);

        // Feature columns — ordered by list position, selected only
        document.querySelectorAll('#mm-feat-col-list .mm-col-item.selected').forEach(item => fd.append('feature_columns[]', item.dataset.col));

        // Annotation basic columns — ordered by list position, selected only
        document.querySelectorAll('#mm-ann-col-list .mm-col-item.selected').forEach(item => fd.append('ann_columns[]', item.dataset.col));

        // Annotation sources
        inScopeSources().filter(c => c.checked).forEach(c => fd.append('annotation_columns[]', c.value));

        Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
        return fd;
    }

    // -------------------------------------------------------
    // Preview
    // -------------------------------------------------------

    let resultsTable = null;

    // Fully tear down the preview DataTable: destroy the instance if one exists (checking
    // the DOM, not just our module var, in case they desync) and empty the <table> so the
    // next build can use a different column set. Re-initialising DataTables on leftover
    // header/body DOM throws "Cannot reinitialise". Safe to call when no table exists.
    function destroyResultsTable() {
        if ($.fn.dataTable.isDataTable('#mm-results-table')) {
            $('#mm-results-table').DataTable().clear().destroy();
        }
        $('#mm-results-table').empty();
        resultsTable = null;
    }

    function previewResults() {
        const btn     = document.getElementById('mm-preview-btn');
        const spinner = document.getElementById('mm-count-spinner');
        const result  = document.getElementById('mm-count-result');

        if (!getSelectedSources().length) {
            if (result) { result.textContent = moopT('Select at least one organism in Step 1 first.'); result.className = 'small text-warning'; }
            return;
        }

        spinner?.classList.remove('d-none');
        if (btn) btn.disabled = true;
        if (result) result.textContent = '';

        const csrf   = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const format = outputFormat();

        if (format === 'fasta') {
            const fastaMode = document.querySelector('input[name="mm-fasta-type"]:checked')?.value || 'gene';
            const flank     = document.getElementById('mm-flank-bp')?.value || '1000';
            const fd = buildFormData({ output_format: 'fasta', fasta_mode: fastaMode, flank_bp: flank, fasta_preview: '1' });
            showFastaNotes(fastaMode, flank);
            fetch(EXPORT_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
                .then(r => r.text())
                .then(text => {
                    if (result) {
                        result.textContent = text.trim() ? moopT('Showing first 10 sequences') : moopT('No sequences matched');
                        result.className = 'small ' + (text.trim() ? 'text-success' : 'text-muted');
                    }
                    renderFastaPreview(text);
                })
                .catch(() => {
                    if (result) { result.textContent = moopT('Error fetching FASTA preview.'); result.className = 'small text-danger'; }
                })
                .finally(() => {
                    spinner?.classList.add('d-none');
                    if (btn) btn.disabled = false;
                });
        } else {
            hideFastaNotes();
            runTsvPreviewFanout(csrf, btn, spinner, result);
        }
    }

    // Progressive TSV preview: fan out one request per selected organism (concurrency
    // 5, mirroring the AnnotationSearch pattern) so the count and table fill in as each
    // organism reports, instead of blocking on one all-organisms request. The running
    // total stays exact; the table shows the first PREVIEW_ROW_CAP rows.
    function runTsvPreviewFanout(csrf, btn, spinner, result) {
        const organisms = getSelectedOrganisms();
        const total = organisms.length;

        resetResultsTable();
        const accRows = [];
        let totalRows = 0, totalGenes = 0, completed = 0, failed = 0, annHeaders = null;

        // id -> how many organisms failed to resolve it. An id absent from one organism may
        // exist in the next, so nothing is reported until the run finishes and the count
        // equals the number of organisms that actually ANSWERED. Reporting per-organism
        // misses would flag almost every id in a multi-organism search.
        const unresolvedTally = new Map();

        const showStatus = (done) => {
            if (!result) return;
            // A partial run must never look complete: the totals below only cover the
            // organisms that answered, so say so rather than reporting an exact-looking
            // number that silently omits the ones that failed.
            const failNote = failed ? ' · ' + nT(failed, '%d organism failed', '%d organisms failed', failed) : '';

            // Ids that matched nothing in ANY organism that answered. Shown only when the run
            // is complete, because a miss in organism 1 of 40 means nothing on its own.
            const answered = completed - failed;
            const missing  = (done && answered > 0)
                ? [...unresolvedTally.entries()].filter(([, n]) => n >= answered).map(([id]) => id)
                : [];
            const missNote = missing.length
                ? ' · ' + nT(missing.length, '%1$d ID not found: %2$s', '%1$d IDs not found: %2$s', missing.length,
                             missing.slice(0, 5).join(', ') + (missing.length > 5 ? ' ' + moopT('+%d more', missing.length - 5) : ''))
                : '';
            if (totalRows > 0) {
                result.textContent =
                    nT(totalGenes, '%s gene', '%s genes', totalGenes.toLocaleString()) + ' → '
                    + nT(totalRows, '%s transcript row', '%s transcript rows', totalRows.toLocaleString())
                    + (done ? '' : ' · ' + nT(total, '%1$d/%2$d organism…', '%1$d/%2$d organisms…', completed, total))
                    + failNote + missNote;
                result.className = 'small ' + ((failed || missNote) ? 'text-warning' : 'text-success');
            } else if (done && failed) {
                result.textContent = nT(failed, 'Preview failed for all %d organism.', 'Preview failed for all %d organisms.', failed);
                result.className = 'small text-danger';
            } else {
                result.textContent = (done
                    ? moopT('No features matched')
                    : nT(total, 'Searching… %1$d/%2$d organism', 'Searching… %1$d/%2$d organisms', completed, total)) + missNote;
                result.className = 'small ' + (missNote ? 'text-warning' : 'text-muted');
            }
        };

        const onData = (data) => {
            totalGenes += data.gene_count ?? 0;
            totalRows  += data.count ?? 0;
            for (const id of (data.unresolved_ids || [])) {
                unresolvedTally.set(id, (unresolvedTally.get(id) || 0) + 1);
            }
            if (annHeaders === null) annHeaders = data.ann_col_headers ?? [];

            const before = accRows.length;
            for (const r of (data.rows || [])) {
                if (accRows.length >= PREVIEW_ROW_CAP) break;
                accRows.push(r);
            }
            if (!accRows.length) return;
            if (!resultsTable) buildResultsTable(accRows, totalRows, annHeaders || []);
            else               updateResultsTable(accRows, totalRows, accRows.length !== before);
        };

        let nextIndex = 0;
        const launchNext = () => {
            if (nextIndex >= total) return;
            const organism = organisms[nextIndex++];
            fetch(PREVIEW_ORG_URL, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: buildFormData({ organism }),
            })
                .then(r => {
                    // A 500 returns an HTML error page, which r.json() would reject on with a
                    // parse error that says nothing useful. Fail loudly on the status instead.
                    if (!r.ok) throw new Error(`HTTP ${r.status}`);
                    return r.json();
                })
                .then(onData)
                .catch(err => {
                    // One organism failing shouldn't abort the run, but it must be counted so
                    // showStatus() can flag the totals as incomplete.
                    failed++;
                    console.error(`MOOPmart preview failed for ${organism}:`, err);
                })
                .finally(() => {
                    completed++;
                    const done = completed >= total;
                    showStatus(done);
                    if (done) {
                        spinner?.classList.add('d-none');
                        if (btn) btn.disabled = false;
                    } else {
                        launchNext();
                    }
                });
        };

        showStatus(false);
        const concurrency = Math.min(5, total);
        for (let i = 0; i < concurrency; i++) launchNext();
    }

    // What a FASTA export of the current selection leaves out, and why (genes with no
    // coordinates, a genome missing, ...). The file is pure FASTA, so these are shown here
    // instead; asked for alongside the preview or download, never before it, so nothing
    // waits on it. A failed check just shows nothing: it must never stop a download.
    let fastaNotesSeq = 0;
    function showFastaNotes(fastaMode, flank) {
        const box = document.getElementById('mm-fasta-notes');
        if (!box) return;
        const seq = ++fastaNotesSeq;   // a slow answer to an older click must not win
        box.classList.add('d-none');
        box.replaceChildren();
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd = buildFormData({ output_format: 'fasta', fasta_mode: fastaMode, flank_bp: flank, fasta_check: '1' });
        fetch(EXPORT_URL, { method: 'POST', headers: { 'X-CSRF-Token': csrf }, body: fd })
            .then(r => r.ok ? r.json() : { notes: [] })
            .then(({ notes }) => {
                if (seq !== fastaNotesSeq || !notes?.length) return;
                const head = document.createElement('div');
                head.className = 'fw-semibold mb-1';
                head.textContent = moopT('Not in this FASTA:');
                const list = document.createElement('ul');
                list.className = 'mb-0 ps-3';
                for (const note of notes) {
                    const li = document.createElement('li');
                    li.textContent = note;
                    list.appendChild(li);
                }
                box.replaceChildren(head, list);
                box.classList.remove('d-none');
            })
            .catch(() => {});
    }

    function hideFastaNotes() {
        fastaNotesSeq++;
        document.getElementById('mm-fasta-notes')?.classList.add('d-none');
    }

    function renderFastaPreview(text) {
        const tableSection = document.getElementById('mm-results-section');
        const fastaSection = document.getElementById('mm-fasta-preview-section');
        const pre          = document.getElementById('mm-fasta-preview-text');
        tableSection?.classList.add('d-none');
        destroyResultsTable();
        if (!text.trim()) { fastaSection?.classList.add('d-none'); return; }
        if (pre) pre.textContent = text;
        fastaSection?.classList.remove('d-none');
        fastaSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Column specs keyed by the UI data-col value — annotation basic cols omitted (not in preview data)
    const FEAT_COL_SPECS = {
        organism:         { title: 'Organism',          data: 'organism_dir',     defaultContent: '', render: (v) => v?.replace(/_/g, ' ') || '' },
        assembly:         { title: 'Assembly',          data: 'genome_accession', defaultContent: '' },
        gene_set:         { title: 'Gene Set',          data: 'gene_set_name',    defaultContent: '' },
        gene_id:          { title: 'Gene ID',           data: 'uniquename',       defaultContent: '',
                            render: (val, type, row) => type !== 'display' ? val
                                : `<a href="${moopSite}/tools/parent.php?uniquename=${encodeURIComponent(val)}&organism=${encodeURIComponent(row.organism_dir)}`
                                  + (row.genome_accession ? `&assembly=${encodeURIComponent(row.genome_accession)}` : '')
                                  + (row.gene_set_name ? `&gene_set=${encodeURIComponent(row.gene_set_name)}` : '')
                                  + `" target="_blank">${val}</a>` },
        gene_name:        { title: 'Gene Name',         data: 'name',             defaultContent: '' },
        gene_description: { title: 'Gene Description',  data: 'description',      defaultContent: '',
                            render: (v, t) => t === 'display' && v?.length > 60 ? `<span title="${v.replace(/"/g, '&quot;')}">${v.slice(0, 60)}…</span>` : (v || '') },
        mrna_id:          { title: 'mRNA ID',           data: 'mrna_id',          defaultContent: '' },
        protein_id:       { title: 'Protein ID',        data: 'protein_id',       defaultContent: '' },
        chr:              { title: 'Chr',               data: 'chr',              defaultContent: '' },
        start:            { title: 'Start',             data: 'start',            defaultContent: '' },
        stop:             { title: 'Stop',              data: 'end',              defaultContent: '' },
        strand:           { title: 'Strand',            data: 'strand',           defaultContent: '' },
        why_included:     { title: 'Inclusion Criteria', data: 'match_reason',     defaultContent: '',
                            render: (v, t) => t === 'display' && v?.length > 80
                                ? `<span title="${v.replace(/"/g, '&quot;')}">${v.slice(0, 80)}…</span>`
                                : (v || '') },
    };

    function getPreviewColumns() {
        const items = document.querySelectorAll('#mm-feat-col-list .mm-col-item.selected');
        const cols = Array.from(items)
            .map(item => FEAT_COL_SPECS[item.dataset.col])
            .filter(Boolean);
        // Fall back to all columns in default order if nothing selected
        return cols.length ? cols : Object.values(FEAT_COL_SPECS);
    }

    // Long layout with annotation columns: the preview has one line per annotation, so its line
    // count and the feature total are different units -- say so instead of "first N of M".
    let previewIsLong = false;

    function setResultsCaption(shown, total) {
        const caption = document.getElementById('mm-results-caption');
        if (!caption) return;
        if (previewIsLong) {
            caption.textContent = '— ' + nT(total, '%s feature row', '%s feature rows', total.toLocaleString()) + ', '
                + nT(shown, 'one line per annotation (preview: %s line)', 'one line per annotation (preview: %s lines)', shown.toLocaleString());
            return;
        }
        caption.textContent = shown < total
            ? moopT('— showing first %1$s of %2$s', shown.toLocaleString(), total.toLocaleString())
            : '— ' + nT(total, '%s result', '%s results', total.toLocaleString());
    }

    // Tear down any existing preview table and hide the section — call at the start of
    // a fresh preview run so progressive updates begin from a clean slate.
    function resetResultsTable() {
        destroyResultsTable();
        document.getElementById('mm-results-section')?.classList.add('d-none');
    }

    // Build the preview DataTable once, revealing and scrolling to the section a single
    // time. Columns (feature cols + annotation headers) are fixed for the run, so later
    // organisms only add rows via updateResultsTable().
    function buildResultsTable(rows, total, annColHeaders = []) {
        const section = document.getElementById('mm-results-section');
        previewIsLong = annLayout() === 'long' && annColHeaders.length > 0;
        setResultsCaption(rows.length, total);
        section?.classList.remove('d-none');
        section?.scrollIntoView({ behavior: 'smooth', block: 'start' });

        const annCols = annColHeaders.map(header => ({
            title: header,
            data: (row) => row[header] ?? '',
            defaultContent: '',
        }));

        destroyResultsTable();
        resultsTable = $('#mm-results-table').DataTable({
            data: rows,
            columns: [...getPreviewColumns(), ...annCols],
            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [[0, 'asc']],
            autoWidth: false,
            dom: 'ltipr',
            language: { info: moopT('Showing _START_ to _END_ of _TOTAL_ preview rows'), infoEmpty: moopT('No results'), lengthMenu: moopT('Show _MENU_ rows') },
        });
    }

    // Refresh the preview table as more organisms report. Re-adds rows only when the
    // row set actually grew (preserving paging); always refreshes the caption so the
    // running total stays live even after the row cap is reached.
    function updateResultsTable(rows, total, rowsChanged) {
        if (!resultsTable) { buildResultsTable(rows, total); return; }
        if (rowsChanged) {
            resultsTable.clear();
            resultsTable.rows.add(rows);
            resultsTable.draw(false);
        }
        setResultsCaption(rows.length, total);
    }

    // -------------------------------------------------------
    // Download
    // -------------------------------------------------------

    function submitDownload(extraFields) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = EXPORT_URL;
        form.style.display = 'none';
        for (const [key, value] of buildFormData(extraFields).entries()) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = key; inp.value = value;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
        setTimeout(() => form.remove(), 5000);
    }

    // -------------------------------------------------------
    // Send to Expression
    // -------------------------------------------------------
    // A heatmap is ONE gene set (one quantification against one set of gene models); an
    // exporter list may span many. So the list is split by gene set, each gene set with
    // expression data gets its own "Open heatmap", and the rest are named as left out --
    // never merged, never dropped silently.

    // Selected gene-set checkboxes, split by whether their gene set has expression data.
    function exprSplitSelection() {
        const withData = new Set(typeof expressionGeneSets !== 'undefined' ? expressionGeneSets : []);
        const on = Array.from(document.querySelectorAll('.scope-gs-cb:checked'));
        const key = cb => `${cb.dataset.org}|${cb.dataset.asm}|${cb.dataset.gs}`;
        return { send: on.filter(cb => withData.has(key(cb))), without: on.filter(cb => !withData.has(key(cb))) };
    }

    // Show the row only while the selection includes a gene set with data; any earlier answer
    // described a different selection, so it is cleared.
    function updateExprState() {
        const row = document.getElementById('mm-expr-row');
        if (!row) return;
        row.classList.toggle('d-none', exprSplitSelection().send.length === 0);
        const out = document.getElementById('mm-expr-result');
        if (out) out.innerHTML = '';
    }

    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    const gsLabel = cb => `${(cb.dataset.label || cb.dataset.org).replace(/_/g, ' ')} · ${cb.dataset.asmDisplay || cb.dataset.asm} · ${cb.dataset.gs}`;

    function sendToExpression() {
        const btn     = document.getElementById('mm-expr-btn');
        const spinner = document.getElementById('mm-expr-spinner');
        const out     = document.getElementById('mm-expr-result');
        const { send, without } = exprSplitSelection();
        if (!send.length || !out) return;

        btn.disabled = true;
        spinner?.classList.remove('d-none');
        out.innerHTML = '<span class="text-muted">' + moopTh('Finding the matching genes…') + '</span>';
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        Promise.all(send.map(cb =>
            fetch(EXPR_GENES_URL, {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrf },
                body: buildFormData({ organism: cb.dataset.org, assembly: cb.dataset.asm, gene_set: cb.dataset.gs }),
            })
                .then(r => r.json().then(j => { if (!r.ok) throw new Error(j.error || `HTTP ${r.status}`); return j; }))
                .then(data => ({ cb, data }))
                .catch(err => ({ cb, error: err.message }))
        )).then(results => {
            const lines = results.map(({ cb, data, error }, i) => {
                const name = `<strong>${escHtml(gsLabel(cb))}</strong>`;
                if (error)            return `<div class="text-danger">${moopTm('%1$s — could not be read (%2$s).', name, escHtml(error))}</div>`;
                if (!data.gene_count) return `<div class="text-muted">${moopTm('%s — no matching genes.', name)}</div>`;
                const capped = data.gene_count > data.genes.length
                    ? ` <span class="text-warning">${moopTh('(the first %1$s of %2$s are sent — a heatmap shows at most %3$s)', data.genes.length, data.gene_count.toLocaleString(), expressionMaxGenes)}</span>`
                    : '';
                return `<div class="d-flex align-items-center flex-wrap gap-2 mb-1">`
                    + `<button type="button" class="btn btn-sm btn-outline-primary py-0 mm-expr-open" data-i="${i}">`
                    + `<i class="fa fa-external-link-alt me-1"></i>${moopTh('Open heatmap')}</button>`
                    + `<span>${data.gene_count === 1 ? moopTm('%1$s — %2$s gene', name, '1') : moopTm('%1$s — %2$s genes', name, data.gene_count.toLocaleString())}${capped}</span></div>`;
            });
            if (without.length) {
                lines.push(`<div class="text-muted mt-1">${nTh(without.length,
                    '%d other selected gene set has no expression data; its genes are not sent.',
                    '%d other selected gene sets have no expression data; their genes are not sent.', without.length)}</div>`);
            }
            out.innerHTML = lines.join('');
            out.querySelectorAll('.mm-expr-open').forEach(b => b.addEventListener('click', () => {
                const { cb, data } = results[+b.dataset.i];
                openExpression(cb, data.genes);
            }));
        }).finally(() => {
            btn.disabled = false;
            spinner?.classList.add('d-none');
        });
    }

    // POST (a long list does not fit a URL) into a new tab, so the exporter keeps its state and
    // the other gene sets' heatmaps are still one click away.
    function openExpression(cb, genes) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = EXPRESSION_URL;
        form.target = '_blank';
        form.style.display = 'none';
        const fields = { organism: cb.dataset.org, assembly: cb.dataset.asm, gene_set: cb.dataset.gs, genes: genes.join('\n') };
        for (const [k, v] of Object.entries(fields)) {
            const inp = document.createElement('input');
            inp.type = 'hidden'; inp.name = k; inp.value = v;
            form.appendChild(inp);
        }
        document.body.appendChild(form);
        form.submit();
        form.remove();
    }

    // -------------------------------------------------------
    // Init
    // -------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        // Pre-selects from the page the visitor came from (?organism= / ?assembly= /
        // ?gene_set= / ?group= / ?organisms[]=), then reports every change to onScopeChange.
        initScopeSelector({
            context:  typeof scopeContext !== 'undefined' ? scopeContext : null,
            onChange: onScopeChange,
        });
        initAnnCriteria();
        initColList();
        initAccordionHeaders();
        initWideLongSwitch();
        initFormatToggle();
        initAnnSources();
        onScopeChange();   // Step 3 counts etc. for the pre-selection, now the steps exist

        // Field-level help is now field_help(), initialised once globally by
        // js/modules/field-help.js, and the how-to help is help_modal() which needs no
        // init at all — so the page-local popover init that used to live here is gone.

        // Preview
        document.getElementById('mm-preview-btn')?.addEventListener('click', previewResults);
        document.getElementById('mm-expr-btn')?.addEventListener('click', sendToExpression);
        updateExprState();
        // The listed genes answer the query as it was when asked: any later edit to the form
        // would leave "Open heatmap" sending a stale list, so the answer is cleared instead.
        const clearExprResult = e => {
            if (e.target.closest?.('#mm-expr-row')) return;
            const out = document.getElementById('mm-expr-result');
            if (out) out.innerHTML = '';
        };
        document.addEventListener('input', clearExprResult, true);
        document.addEventListener('change', clearExprResult, true);
        // The FASTA notes describe the last export; once the selection changes they may not hold
        document.addEventListener('input', hideFastaNotes, true);
        document.addEventListener('change', hideFastaNotes, true);

        // True if ANY Step 2 section is filled — an ID list, a name, a description, an
        // annotation criterion, or a chromosomal range. When none are, the export is every
        // feature of the selected organisms, which is the case worth warning about at scale.
        function hasStep2Filters() {
            if (document.getElementById('mm-feature-ids')?.value?.trim()) return true;
            if (document.getElementById('mm-gene-name')?.value?.trim()) return true;
            if (document.getElementById('mm-gene-description')?.value?.trim()) return true;
            if (document.getElementById('mm-coord-chr')?.value?.trim()) return true;
            return Array.from(document.querySelectorAll('.mm-ann-criterion')).some(row =>
                (row.querySelector('.mm-ann-src-select')?.value?.trim()) ||
                (row.querySelector('.mm-ann-type-select')?.value) ||
                (row.querySelector('.mm-ann-accession')?.value?.trim()) ||
                (row.querySelector('.mm-ann-keyword')?.value?.trim()));
        }

        // Download (single button, format from radio)
        document.getElementById('mm-dl-btn')?.addEventListener('click', function () {
            const result = document.getElementById('mm-count-result');
            if (!getSelectedSources().length) {
                if (result) { result.textContent = moopT('Select at least one organism in Step 1 first.'); result.className = 'small text-warning'; }
                return;
            }
            const format    = outputFormat();
            const fastaMode = document.querySelector('input[name="mm-fasta-type"]:checked')?.value || 'gene';
            const flank     = document.getElementById('mm-flank-bp')?.value || '1000';
            const go = () => {
                if (format === 'fasta') showFastaNotes(fastaMode, flank); else hideFastaNotes();
                submitDownload({ output_format: format, fasta_mode: fastaMode, flank_bp: flank });
            };

            // Guard the genuinely big case: every feature (no Step 2 filter) across many
            // organisms. A few unfiltered organisms is a normal export and passes straight
            // through; the confirm only fires when it would be large.
            const BIG_EXPORT_ORGS = 10;
            const orgCount = getSelectedOrganisms().length;
            if (!hasStep2Filters() && orgCount >= BIG_EXPORT_ORGS) {
                const modalEl = document.getElementById('mm-big-export-modal');
                modalEl.querySelector('.mm-be-orgs').textContent = orgCount.toLocaleString();
                modalEl.querySelector('.mm-be-gs').textContent = getSelectedSources().length.toLocaleString();
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalEl.querySelector('.mm-be-confirm').onclick = () => { modal.hide(); go(); };
                modal.show();
                return;
            }
            go();
        });

        updateCoordState();

        // Chevron rotation for step 3 collapse
        const designCollapse = document.getElementById('mm-design-body');
        const designChevron  = document.getElementById('mm-design-chevron');
        if (designCollapse && designChevron) {
            designCollapse.addEventListener('show.bs.collapse',  () => designChevron.style.transform = 'rotate(180deg)');
            designCollapse.addEventListener('hide.bs.collapse',  () => designChevron.style.transform = 'rotate(0deg)');
        }
    });

})();

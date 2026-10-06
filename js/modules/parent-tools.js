// Parent Feature Display JavaScript

$(document).ready(function() {
    // Initialize DataTables for annotation tables with export buttons
    $('table[id^="annotTable_"]').each(function() {
        var tableId = '#' + $(this).attr('id');
        DataTableExportConfig.reinitialize(tableId);
    });

    // Initialize Bootstrap 5 tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Toggle icons on collapse (Bootstrap 5)
    $('.collapse').on('show.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-bs-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-plus')
            .addClass('fa-minus');
    });

    $('.collapse').on('hide.bs.collapse', function(e) {
        if (e.target !== this) return;
        $('[data-bs-target="#' + this.id + '"] .toggle-icon')
            .removeClass('fa-minus')
            .addClass('fa-plus');
    });
});


/* ── Collapse / expand every transcript at once ───────────────────────────────
 *
 * A gene with 17 transcripts puts every annotation table between the first and the
 * last, so reaching the bottom means scrolling past all of them. There was no way to
 * fold them and no keyboard shortcut either.
 *
 * Works by CLICKING each existing trigger rather than toggling .show directly, so the
 * caret icons, aria-expanded and anything else collapse-handler.js does stay correct —
 * that file removes data-bs-toggle and drives the collapse itself, so writing classes
 * here would leave the icons contradicting the state.
 */
$(document).ready(function () {
    var $btn = $('#toggle-all-transcripts');
    if (!$btn.length) return;

    // Scoped to #pnav-annotations. `.annotation-card` is NOT unique to transcripts — the
    // Sequences box builds its Protein / mRNA / CDS sub-boxes with the same class, so a
    // page-wide selector matched 20 things on a 17-transcript gene and "Collapse all"
    // folded the sequence boxes too. Reading the DOM page-wide in a UI that renders the
    // same component in several places is the recurring bug here; scope every such read.
    function triggers() {
        return $('#pnav-annotations .annotation-card > .card-header .collapse-section');
    }

    $btn.on('click', function () {
        var collapsing = $btn.attr('data-state') !== 'collapsed';

        triggers().each(function () {
            var target = document.querySelector(this.getAttribute('data-bs-target'));
            if (!target) return;
            var open = target.classList.contains('show');
            // Click only the ones not already in the state we want, so a half-collapsed
            // page converges instead of inverting.
            if (open === collapsing) this.click();
        });

        $btn.attr('data-state', collapsing ? 'collapsed' : 'expanded');
        $btn.find('.label').text(collapsing ? 'Expand all' : 'Collapse all');
        $btn.find('i').attr('class', collapsing ? 'fas fa-expand me-1' : 'fas fa-compress me-1');
        $btn.attr('title', collapsing
            ? 'Expand every transcript again'
            : 'Collapse every transcript so the list fits on one screen');
    });
});


/* ── Reaching into a collapsed section ────────────────────────────────────────
 *
 * The Annotations section starts collapsed (user, 2026-10-06), so every way into it has to
 * open it first, or the jump lands on a hidden element and nothing visibly happens. The
 * ways in: the gene-structure rows (gene-model-viewer.js), the Feature Hierarchy's
 * href="#..." links, a #hash in the URL, and the "Jump to" sidebar (parent-nav.js, which
 * opens its own targets).
 *
 * collapse-handler.js toggles `.show` directly and fires no Bootstrap events, so the icon
 * is set here, and DataTables are re-measured by watching the class instead: a table drawn
 * while its section was hidden has no width to measure, and its header comes out squashed.
 */
(function () {
    function setIcon(collapseEl) {
        if (!collapseEl.id) return;
        document.querySelectorAll('[data-bs-target="#' + collapseEl.id + '"] .toggle-icon').forEach(function (i) {
            i.classList.toggle('fa-minus', collapseEl.classList.contains('show'));
            i.classList.toggle('fa-plus', !collapseEl.classList.contains('show'));
        });
    }

    window.moopOpenCollapsedAncestors = function (el) {
        for (var p = el; p; p = p.parentElement) {
            if (p.classList && p.classList.contains('collapse') && !p.classList.contains('show')) {
                p.classList.add('show');
                setIcon(p);
            }
        }
    };

    function adjustTables() {
        if (window.jQuery && jQuery.fn.dataTable) {
            jQuery.fn.dataTable.tables({ visible: true, api: true }).columns.adjust();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var section = document.getElementById('annotationsSection');
        if (section && window.MutationObserver) {
            var wasOpen = section.classList.contains('show');
            new MutationObserver(function () {
                var open = section.classList.contains('show');
                if (open && !wasOpen) adjustTables();
                wasOpen = open;
            }).observe(section, { attributes: true, attributeFilter: ['class'] });
        }

        // In-page links: open the way, then let the browser follow the link as usual.
        document.addEventListener('click', function (e) {
            var a = e.target.closest && e.target.closest('a[href^="#"]');
            if (!a || a.getAttribute('href').length < 2) return;
            var target = document.getElementById(decodeURIComponent(a.getAttribute('href').slice(1)));
            if (target) window.moopOpenCollapsedAncestors(target);
        }, true);

        // Arriving with a #hash that points inside a collapsed section.
        if (location.hash.length > 1) {
            var target = document.getElementById(decodeURIComponent(location.hash.slice(1)));
            if (target && target.closest('.collapse:not(.show)')) {
                window.moopOpenCollapsedAncestors(target);
                target.scrollIntoView({ block: 'start' });
            }
        }
    });
})();


/* ── Naming statements: "more" on the ones clamped to two lines ───────────────
 * Shown only where the text actually overflows — measured, since line length depends on
 * the card's width, not on the character count. */
document.addEventListener('DOMContentLoaded', function () {
    var items = document.querySelectorAll('.gene-naming-list dd');
    function measure() {
        items.forEach(function (dd) {
            if (dd.classList.contains('gn-open')) return;
            var t = dd.querySelector('.gn-text'), b = dd.querySelector('.gn-more');
            if (t && b) b.hidden = t.scrollHeight <= t.clientHeight + 1;
        });
    }
    items.forEach(function (dd) {
        var b = dd.querySelector('.gn-more');
        if (!b) return;
        b.addEventListener('click', function () {
            var open = dd.classList.toggle('gn-open');
            b.textContent = open ? 'less' : 'more';
        });
    });
    measure();
    window.addEventListener('resize', measure);
});


/* ── Big genes: one transcript's annotations, on demand ───────────────────────
 *
 * A gene with hundreds of transcripts lists them (tools/parent.php, MOOP_BIG_GENE_TRANSCRIPTS)
 * instead of rendering every annotation card, which ran PHP out of memory. "Show annotations"
 * asks the gene page itself for that one transcript's card (…&transcript=ID) -- the same
 * access checks as the full page -- and adds it below the list.
 */
document.addEventListener('DOMContentLoaded', function () {
    var list = document.getElementById('bigGeneTranscripts');
    var holder = document.getElementById('bigGeneCards');
    if (!list || !holder) return;

    // Rows arrive as data (pages/parent.php #bigGeneRows): [id, annotations, protein aa|null, longest]
    var rows = [];
    try { rows = JSON.parse(document.getElementById('bigGeneRows').textContent); } catch (e) {}
    var esc = function (t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; };
    // Explicit data indexes, because the optional Structure column shifts the positions.
    // Columns: Transcript | [Structure] | Annotations | Protein | button.
    var hasStructure = !!list.querySelector('th.col-structure');
    var anchorOf = function (id) { return 'annot_card_' + String(id).replace(/[^a-zA-Z0-9_]/g, '_'); };
    var cols = [{ data: 0, render: function (v, type) { return type === 'display' ? esc(v) : v; } }];
    if (hasStructure) {
        // The isoform's thumbnail (isoform-minimap.js), only for genes with a gene model --
        // transcriptome clusters have none. The cell is an empty placeholder; fillThumbs()
        // draws the rows on screen only, so 360 transcripts cost 25 thumbnails per page.
        cols.push({ data: null, orderable: false, render: function (v, type, row) {
            return type === 'display' ? '<span class="iso-thumb" data-anchor="' + esc(anchorOf(row[0])) + '"></span>' : '';
        } });
    }
    var proteinCol = cols.length + 1;
    cols.push(
        { data: 1, render: function (v, type) { return type === 'display' ? v.toLocaleString() : v; } },
        { data: 2, render: function (v, type, row) {
            if (type !== 'display') return v === null ? -1 : v;
            if (v === null) return '<span class="text-muted">—</span>';
            return v.toLocaleString() + ' aa' + (row[3] ? ' <span class="text-muted small">longest</span>' : '');
        } },
        { data: null, orderable: false, className: 'text-end', render: function (v, type, row) {
            if (type !== 'display' || row[1] === 0) return '';
            return '<button type="button" class="btn btn-sm moop-data-btn big-gene-load" data-transcript="'
                 + esc(row[0]) + '">Show annotations</button>';
        } }
    );

    function fillThumbs() {
        if (!window.moopIsoformMinimap) return;
        list.querySelectorAll('.iso-thumb:empty').forEach(function (cell) {
            var svg = window.moopIsoformMinimap(cell.getAttribute('data-anchor'));
            if (svg) cell.appendChild(svg);
        });
    }

    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery(list).on('draw.dt', fillThumbs);
        jQuery(list).DataTable({
            data: rows,
            pageLength: 25,
            // Longest protein first: the same isoform the Gene Structure diagram shows, so the
            // top of the list matches the picture above it. (Sorting by annotation count read
            // as arbitrary when nearly every transcript had the same count.)
            order: [[proteinCol, 'desc']],
            deferRender: true,
            columns: cols
        });
        // Once more after every DOMContentLoaded handler has run: isoform-minimap.js defines
        // moopIsoformMinimap in its own handler, which runs AFTER this one.
        setTimeout(fillThumbs, 0);
    }

    function initCard(card) {
        // DataTables for the new tables, as parent-tools does for the page's own on load.
        card.querySelectorAll('table[id^="annotTable_"]').forEach(function (t) {
            // typeof, not window.X: DataTableExportConfig is a top-level `const`, which is a
            // global NAME but never a window property -- window.DataTableExportConfig is
            // always undefined, and the tables were silently left uninitialised.
            if (typeof DataTableExportConfig !== 'undefined') DataTableExportConfig.reinitialize('#' + t.id);
        });
    }

    // Delegated: DataTables redraws the rows on every page and sort, so buttons come and go.
    list.addEventListener('click', function (e) {
        var btn = e.target.closest('.big-gene-load');
        if (!btn) return;
        var id = btn.getAttribute('data-transcript');
        var existing = holder.querySelector('[data-loaded-transcript="' + CSS.escape(id) + '"]');
        if (existing) { existing.scrollIntoView({ behavior: 'smooth', block: 'start' }); return; }

        btn.disabled = true;
        var label = btn.textContent;
        btn.textContent = 'Loading…';
        var url = location.pathname + '?organism=' + encodeURIComponent(holder.dataset.organism)
                + '&uniquename=' + encodeURIComponent(holder.dataset.gene)
                + '&transcript=' + encodeURIComponent(id);
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.text(); })
            .then(function (html) {
                var wrap = document.createElement('div');
                wrap.className = 'mb-3';
                wrap.setAttribute('data-loaded-transcript', id);
                wrap.innerHTML = html;
                holder.insertBefore(wrap, holder.firstChild);
                initCard(wrap);
                // A close, so opened cards do not just pile up. Removing it resets the list's
                // button (if that row is on the current page) so the transcript can be reopened.
                var header = wrap.querySelector('.annotation-card > .card-header');
                if (header) {
                    var close = document.createElement('button');
                    close.type = 'button';
                    close.className = 'btn btn-sm moop-data-btn ms-auto';
                    close.title = 'Close this transcript';
                    close.innerHTML = '<i class="fas fa-times me-1"></i>Close';
                    close.addEventListener('click', function (ev) {
                        ev.stopPropagation();
                        wrap.remove();
                        // Through the table's own rows, not the document: rows on other pages are
                        // detached from the DOM, so a page-wide lookup would miss them.
                        var sel = '.big-gene-load[data-transcript="' + CSS.escape(id) + '"]';
                        var rowsNodes = (window.jQuery && jQuery.fn.DataTable && jQuery.fn.DataTable.isDataTable(list))
                            ? jQuery(list).DataTable().rows().nodes().toArray() : [list];
                        rowsNodes.forEach(function (n) { var b = n.querySelector(sel); if (b) b.textContent = label; });
                    });
                    header.appendChild(close);
                }
                // Its structure, drawn here because the big-gene diagram shows only one isoform.
                // Same anchor rule as moop_annotation_card_anchor() in lib/parent_functions.php.
                // Inside the card's body, under its header and above the first table, so it
                // reads as part of that transcript and folds with it -- drawn on the wrapper,
                // it sat outside the card's border, jammed against whatever was above.
                if (window.moopDrawIsoform) {
                    var body = wrap.querySelector('.annotation-card .card-body') || wrap;
                    window.moopDrawIsoform('annot_card_' + id.replace(/[^a-zA-Z0-9_]/g, '_'), body);
                }
                btn.textContent = 'Shown below ↓';
                btn.disabled = false;
                wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function () {
                btn.textContent = label;
                btn.disabled = false;
                window.alert('Could not load the annotations for ' + id + '.');
            });
    });
});

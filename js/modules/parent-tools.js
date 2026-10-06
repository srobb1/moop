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

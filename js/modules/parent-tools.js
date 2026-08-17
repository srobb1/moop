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

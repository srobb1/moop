/**
 * Content pages (page.php): a table an admin writes with class="moop-table" becomes searchable,
 * sortable and downloadable (CSV / Excel). The page cleaner (lib/content_pages.php) removes any
 * script a page carries, so this behaviour lives here, keyed on a class the cleaner keeps.
 *
 * Downloads contain the table's text (links become their visible text). The file is named after
 * the page's address, e.g. nematostella-ucne.csv; with several such tables on one page, the
 * second is nematostella-ucne-2.csv, and so on.
 */
(function () {
    'use strict';
    if (!window.jQuery || !jQuery.fn.DataTable) return;

    const slug = new URLSearchParams(window.location.search).get('p') || 'table';

    jQuery('.content-page-body table.moop-table').each(function (i) {
        const name = i === 0 ? slug : slug + '-' + (i + 1);
        jQuery(this).DataTable({
            paging: false,
            order: [],                    // keep the order the page was written in
            dom: '<"d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"Bf>rti',
            // dom.button.className REPLACES DataTables' default "btn btn-secondary". A per-button
            // className is ADDED to it, so these were btn-secondary + btn-outline-secondary: grey
            // text on a grey fill, unreadable (2026-11-25). btn-outline-moop = the site's quiet
            // secondary control (css/moop.css), in the site colour.
            buttons: {
                dom: { button: { className: 'btn btn-sm btn-outline-moop' } },
                buttons: [
                    { extend: 'csvHtml5',   text: moopT('Download CSV'),   filename: name },
                    { extend: 'excelHtml5', text: moopT('Download Excel'), filename: name },
                ],
            },
        });
    });
})();

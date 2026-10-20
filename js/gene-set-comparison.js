/**
 * Organism page: make the gene set comparison table sortable (includes/gene_set_comparison.php).
 *
 * Sorting only -- no paging, no search box, no "showing 1 to 3" line: a handful of rows does not
 * need them. Numeric columns sort on their data-order value, so "—" (stored as -1) sorts last
 * descending and "1.9 Mb" sorts by base pairs, not as text. Initial order is the server's.
 */
(function () {
    'use strict';
    const table = document.getElementById('geneSetComparisonTable');
    if (!table || !window.jQuery || !jQuery.fn.DataTable) return;
    if (table.tBodies[0].rows.length < 2) return;   // nothing to sort
    jQuery(table).DataTable({
        paging: false,
        searching: false,
        info: false,
        order: [],
        autoWidth: false
    });
})();

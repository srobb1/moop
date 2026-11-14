/**
 * Gene-set page: the KEGG pathways table (tools/pages/gene_set.php, lib/kegg.php).
 * Sortable and searchable; most genes first; the Class select narrows to one KEGG class (exact match on
 * the cell's data-search, which is the class alone, not its subclass). Columns are found by class name,
 * never by position.
 */
$(function () {
    const $t = $('#keggPathwaysTable');
    if (!$t.length || !$.fn.DataTable) return;
    const col = cls => $t.find('thead th.' + cls).index();
    const dt = $t.DataTable({
        order: col('kegg-genes-col') >= 0 ? [[col('kegg-genes-col'), 'desc']] : [],
        pageLength: 25,
        columnDefs: [{ targets: 'no-sort', orderable: false }],
    });
    $('#keggClassFilter').on('change', function () {
        const v = this.value;
        dt.column(col('kegg-class-col'))
          .search(v ? '^' + v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : '', true, false)
          .draw();
    });
});

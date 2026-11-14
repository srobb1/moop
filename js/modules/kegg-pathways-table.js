/**
 * KEGG pathway tables: gene-set page and organism page (lib/kegg.php). Every table.kegg-pathways-dt is
 * sortable and searchable, first ordered by its th.kegg-sort-col (descending); a select[data-kegg-filter]
 * naming the table narrows it to one KEGG class (exact match on the cell's data-search: the class alone,
 * not its subclass). Columns are found by class name, never by position.
 */
$(function () {
    if (!$.fn.DataTable) return;
    $('table.kegg-pathways-dt').each(function () {
        const $t  = $(this);
        const col = cls => $t.find('thead th.' + cls).index();
        const sortCol = col('kegg-sort-col');
        const dt = $t.DataTable({
            order: sortCol >= 0 ? [[sortCol, 'desc']] : [],
            pageLength: 25,
            columnDefs: [{ targets: 'no-sort', orderable: false }],
        });
        // A table built inside a closed card has no width yet: fit the columns when the card opens
        $t.closest('.collapse').on('shown.bs.collapse', () => dt.columns.adjust());
        $('select[data-kegg-filter="' + this.id + '"]').on('change', function () {
            const v = this.value;
            dt.column(col('kegg-class-col'))
              .search(v ? '^' + v.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$' : '', true, false)
              .draw();
        });
    });
});

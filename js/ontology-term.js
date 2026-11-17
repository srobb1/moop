/* ontology-term.js -- the ontology term page (tools/ontology_term.php).
 * The GO "Genes" table: searchable and sortable (DataTables, loaded on every page), biggest first.
 * Small tables stay plain -- a search box over three rows is clutter. */
(function () {
  "use strict";
  if (typeof jQuery === "undefined" || !jQuery.fn.DataTable) return;
  var t = jQuery("#obGenes");
  if (!t.length || t.find("tbody tr").length <= 10) return;
  t.DataTable({ order: [[3, "desc"]], pageLength: 25, lengthMenu: [10, 25, 50, 100], autoWidth: false });
})();

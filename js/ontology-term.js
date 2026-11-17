/* ontology-term.js -- the ontology term page (tools/ontology_term.php).
 * The GO "Genes" table: searchable and sortable (DataTables, loaded on every page) when it is long;
 * small tables stay plain -- a search box over three rows is clutter.
 * The switch "Include more specific terms" swaps the Genes column: .ob-g-all (the term or a more
 * specific one) or .ob-g-own (the term itself). Each column carries its own exporter links and
 * downloads, so whatever is shown is exactly what a click gives. */
(function () {
  "use strict";
  var table = document.getElementById("obGenes");
  if (!table) return;
  var sw = document.getElementById("obGenesSub");
  var dt = null;
  if (typeof jQuery !== "undefined" && jQuery.fn.DataTable && table.querySelectorAll("tbody tr").length > 10) {
    dt = jQuery(table).DataTable({ order: [[3, "desc"]], pageLength: 25, lengthMenu: [10, 25, 50, 100], autoWidth: false });
  }
  function apply() {
    var all = !sw || sw.checked;
    if (dt) {
      dt.column(3).visible(all); dt.column(4).visible(!all);
      dt.order([[all ? 3 : 4, "desc"]]).draw(false);
    } else {
      table.querySelectorAll(".ob-g-all").forEach(function (el) { el.hidden = !all; });
      table.querySelectorAll(".ob-g-own").forEach(function (el) { el.hidden = all; });
    }
    document.querySelectorAll("#pnav-genes p .ob-g-all").forEach(function (el) { el.hidden = !all; });
    document.querySelectorAll("#pnav-genes p .ob-g-own").forEach(function (el) { el.hidden = all; });
  }
  if (sw) sw.addEventListener("change", apply);
  apply();
})();

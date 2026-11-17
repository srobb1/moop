/* ontology-term.js -- the ontology term page (tools/ontology_term.php), GO "Genes" card.
 *
 * The table (one row per gene set): searchable and sortable (DataTables, loaded on every page) when
 * it is long; small tables stay plain -- a search box over three rows is clutter.
 * The switch "Include more specific terms" swaps the Genes column: .ob-g-all (the term or a more
 * specific one) or .ob-g-own (the term itself). Each column carries its own links and downloads.
 *
 * The gene list: clicking a number (a.ob-genes-open) opens that gene set's genes below the table
 * (api/go_term_genes.php) instead of going to the Data Exporter -- the link's href, still used for
 * a new tab / middle click. With a single gene set the list opens by itself. The switch reloads
 * an open list. Names and descriptions are data: built with textContent, never as HTML.
 */
(function () {
  "use strict";
  var table = document.getElementById("obGenes");
  if (!table) return;
  var T = function (s) { return typeof moopT === "function" ? moopT(s) : s; };
  var fmt = function (n) { return Number(n).toLocaleString(); };
  var sw = document.getElementById("obGenesSub");
  var dt = null;
  if (typeof jQuery !== "undefined" && jQuery.fn.DataTable && table.querySelectorAll("tbody tr").length > 10) {
    dt = jQuery(table).DataTable({ order: [[3, "desc"]], pageLength: 25, lengthMenu: [10, 25, 50, 100], autoWidth: false });
  }
  var subOn = function () { return !sw || sw.checked; };

  function applySwitch() {
    var all = subOn();
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

  // ---- the gene list -------------------------------------------------------------------------
  var panel = document.getElementById("obGeneList");
  var body = document.getElementById("obGeneListBody");
  var title = document.getElementById("obGeneListTitle");
  var actions = document.getElementById("obGeneListActions");
  var openRow = null;        // the <tr> whose genes are shown
  var listDt = null;
  var request = 0;           // only the latest load may draw

  function el(tag, cls, text) {
    var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e;
  }

  // The row's cell for the current switch state: its exporter link and download form.
  function rowCell(tr) { return tr.querySelector(subOn() ? "td.ob-g-all" : "td.ob-g-own"); }

  function openList(tr) {
    if (!panel || !tr) return;
    openRow = tr;
    var mine = ++request;
    var cell = rowCell(tr);
    var link = cell && cell.querySelector("a.ob-genes-open");
    var form = cell && cell.querySelector("form");
    panel.hidden = false;
    title.textContent = tr.dataset.label;
    actions.textContent = "";
    if (link) {
      var ex = el("a", "small", T("Open in the Data Exporter")); ex.href = link.href; actions.appendChild(ex);
    }
    if (form) {
      var f = form.cloneNode(true);
      var b = f.querySelector("button"); b.className = "btn btn-sm btn-outline-secondary py-0";
      b.appendChild(document.createTextNode(" " + T("Download (TSV)")));
      actions.appendChild(f);
    }
    if (listDt) { listDt.destroy(); listDt = null; }
    body.textContent = "";
    if (!link) { body.appendChild(el("p", "text-muted small mb-0", T("No genes here."))); return; }
    body.appendChild(el("p", "text-muted small mb-0", T("Loading the genes…")));
    var q = "?term=" + encodeURIComponent(panel.dataset.term) + "&organism=" + encodeURIComponent(tr.dataset.organism)
          + "&assembly=" + encodeURIComponent(tr.dataset.assembly) + "&gene_set=" + encodeURIComponent(tr.dataset.geneSet)
          + (subOn() ? "&sub=1" : "");
    fetch(panel.dataset.api + q, { credentials: "same-origin" })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (data) { if (mine === request) drawList(tr, data); })
      .catch(function () { if (mine === request) { body.textContent = ""; body.appendChild(el("p", "text-danger small mb-0", T("The genes could not be loaded."))); } });
  }

  function drawList(tr, data) {
    body.textContent = "";
    var n = data.total || 0;
    title.textContent = tr.dataset.label + " — " + (n === 1 ? T("1 gene") : T("%s genes").replace("%s", fmt(n)));
    var t = el("table", "table table-sm table-hover align-middle w-100 ob-gene-list-table");
    var head = el("thead"), hr = el("tr");
    [T("Gene"), T("Name"), T("Description"), subOn() ? T("GO terms that matched") : T("GO term")].forEach(function (h) { hr.appendChild(el("th", "", h)); });
    head.appendChild(hr); t.appendChild(head); t.appendChild(el("tbody"));
    body.appendChild(t);
    var labels = data.labels || {};
    var geneBase = panel.dataset.geneUrl + "?organism=" + encodeURIComponent(tr.dataset.organism)
                 + "&assembly=" + encodeURIComponent(tr.dataset.assembly) + "&gene_set=" + encodeURIComponent(tr.dataset.geneSet) + "&uniquename=";
    var termBase = panel.dataset.termUrl + "?o=GO&id=";
    // Rows as data; cells drawn by render functions that only ever set text (DataTables escapes
    // nothing for us, so the renderers build the HTML from escaped pieces).
    var esc = function (s) { var d = document.createElement("div"); d.textContent = s == null ? "" : String(s); return d.innerHTML; };
    listDt = jQuery(t).DataTable({
      data: data.genes || [], deferRender: true, pageLength: 25, lengthMenu: [10, 25, 50, 100], autoWidth: false, order: [[0, "asc"]],
      columns: [
        { data: 0, render: function (v, type) { return type === "display" ? '<a href="' + esc(geneBase + encodeURIComponent(v)) + '">' + esc(v) + "</a>" : v; } },
        { data: 1, render: function (v, type) { return type === "display" ? esc(v) : v; } },
        { data: 2, render: function (v, type) { return type === "display" ? esc(v) : v; } },
        { data: 3, orderable: false, render: function (v, type, row) {
            var names = v.map(function (id) { return labels[id] || id; });
            if (type !== "display") return names.join(" ");
            var html = v.map(function (id) {
              return '<a href="' + esc(termBase + encodeURIComponent(id)) + '" title="' + esc(id) + '">' + esc(labels[id] || id) + "</a>";
            }).join(", ");
            var more = row[4] - v.length;
            return html + (more > 0 ? ' <span class="text-muted small">' + esc(T("+%s more").replace("%s", fmt(more))) + "</span>" : "");
        } }
      ]
    });
  }

  if (panel) {
    table.addEventListener("click", function (e) {
      var a = e.target.closest("a.ob-genes-open");
      if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;   // new tab: the exporter
      e.preventDefault();
      openList(a.closest("tr"));
      panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
    });
    document.getElementById("obGeneListClose").addEventListener("click", function () {
      panel.hidden = true; openRow = null; request++;
      if (listDt) { listDt.destroy(); listDt = null; } body.textContent = "";
    });
  }

  if (sw) sw.addEventListener("change", function () { applySwitch(); if (openRow) openList(openRow); });
  applySwitch();

  // A single gene set: its genes are what the visitor came for -- show them straight away.
  var rowsAll = table.querySelectorAll("tbody tr");
  if (panel && rowsAll.length === 1) openList(rowsAll[0]);
})();

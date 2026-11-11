/* ============================================================================
 * ontology-browser.js -- the Ontology Browser tree (tools/ontology_browser.php)
 *
 * Opening a row loads the terms directly below it from api/ontology_children.php (once; then it
 * just shows/hides). Rows are built with textContent -- term names and definitions are data.
 * Also: the ontology picker and "show parts too" submit their forms on change, and picking a term
 * in the search box (js/modules/ontology-term-box.js, event 'onto:pick') opens its term page.
 * ==========================================================================*/
(function () {
  "use strict";
  if (typeof obConfig === "undefined") return;
  var T = function (s) { return typeof moopT === "function" ? moopT(s) : s; };

  function row(term, depth) {
    var li = document.createElement("li");
    li.className = "ob-d" + Math.min(depth, 12);
    li.dataset.id = term.id;
    var r = document.createElement("div"); r.className = "ob-row";
    var t = document.createElement("span"); t.className = "ob-term";
    if (term.children > 0) {
      var b = document.createElement("button");
      b.type = "button"; b.className = "ob-toggle"; b.setAttribute("aria-expanded", "false");
      b.setAttribute("aria-label", T("Show the terms below") + " " + term.label);
      t.appendChild(b);
    } else {
      var leaf = document.createElement("span"); leaf.className = "ob-leaf"; t.appendChild(leaf);
    }
    var a = document.createElement("a"); a.href = term.url; a.textContent = term.label; t.appendChild(a);
    if (!term.is_a) {
      var rel = document.createElement("span"); rel.className = "ob-rel"; rel.textContent = term.relation; t.appendChild(rel);
    }
    var c = document.createElement("span"); c.className = "ob-counts";
    c.dataset.counts = JSON.stringify(term); t.appendChild(c); drawCounts(c, false);
    var d = document.createElement("span"); d.className = "ob-def"; d.textContent = term.definition || "";
    r.appendChild(t); r.appendChild(d); li.appendChild(r);
    return li;
  }

  // A row's two counts. CLOSED: the term and everything below it ("52 terms", "7,794 records").
  // OPENED: its own share -- the terms directly below it by relationship ("6 part of"), and the
  // records on the term itself -- because its children now show the rest.
  var fmt = function (n) { return Number(n).toLocaleString(); };
  function drawCounts(el, open) {
    var d; try { d = JSON.parse(el.dataset.counts || "{}"); } catch (e) { return; }
    el.textContent = "";
    if (open) {
      var parts = Object.keys(d.below || {}).map(function (k) { return fmt(d.below[k]) + " " + k; });
      if (parts.length) badge(el, "ob-n ob-n-open", parts.join(" · "), T("Terms directly below it, by relationship"));
    } else if (d.terms_all > 0) {
      badge(el, "ob-n", d.terms_all === 1 ? T("1 term") : T("%s terms").replace("%s", fmt(d.terms_all)),
            T("All the terms below it, at any depth"));
    }
    if (d.records_all === null || d.records_all === undefined) return;
    var n = open ? d.records_own : d.records_all;
    if (!n) { badge(el, "ob-rec-none", open ? T("no records on this term itself") : T("no records"), ""); return; }
    var a = document.createElement("a"); a.className = "ob-rec" + (open ? " ob-rec-open" : ""); a.href = d.records_url;
    a.title = open ? T("Records on this term itself; the terms below show theirs. Click for all of them.")
                   : T("Published expression records on this term and everything below it");
    var i = document.createElement("i"); i.className = "fa fa-microscope"; i.setAttribute("aria-hidden", "true"); a.appendChild(i);
    a.appendChild(document.createTextNode(" " + (open ? T("%s on this term").replace("%s", fmt(n))
                                                      : (n === 1 ? T("1 record") : T("%s records").replace("%s", fmt(n))))));
    el.appendChild(a);
  }
  function badge(el, cls, text, title) {
    var b = document.createElement("span"); b.className = cls; b.textContent = text; if (title) b.title = title; el.appendChild(b);
  }

  function depthOf(li) { var m = /ob-d(\d+)/.exec(li.className); return m ? parseInt(m[1], 10) : 1; }

  function toggle(btn) {
    var li = btn.closest("li");
    var open = btn.getAttribute("aria-expanded") === "true";
    var sub = li.querySelector(":scope > ul");
    var counts = li.querySelector(":scope > .ob-row .ob-counts");
    if (open) { btn.setAttribute("aria-expanded", "false"); if (sub) sub.hidden = true; if (counts) drawCounts(counts, false); return; }
    btn.setAttribute("aria-expanded", "true");
    if (counts) drawCounts(counts, true);
    if (sub) { sub.hidden = false; return; }
    sub = document.createElement("ul"); sub.className = "ob-tree";
    var wait = document.createElement("li"); wait.className = "ob-loading ob-d" + (depthOf(li) + 1);
    wait.textContent = T("Loading…"); sub.appendChild(wait); li.appendChild(sub);
    var url = obConfig.api + "?o=" + encodeURIComponent(obConfig.prefix) + "&id=" + encodeURIComponent(li.dataset.id)
            + (obConfig.parts ? "&parts=1" : "");
    fetch(url, { credentials: "same-origin" })
      .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
      .then(function (data) {
        sub.innerHTML = "";
        (data.terms || []).forEach(function (term) { sub.appendChild(row(term, depthOf(li) + 1)); });
        if (!sub.children.length) { wait.textContent = T("No terms below this one."); sub.appendChild(wait); }
      })
      .catch(function () { wait.textContent = T("Could not load the terms below this one."); });
  }

  document.querySelectorAll("#obTree .ob-counts").forEach(function (el) { drawCounts(el, false); });

  document.addEventListener("click", function (e) {
    var b = e.target.closest(".ob-toggle");
    if (b && document.getElementById("obTree").contains(b)) { e.preventDefault(); toggle(b); }
  });
  ["obOntology", "obParts"].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener("change", function () { el.form.submit(); });
  });
  var box = document.getElementById("obSearch");
  if (box) box.addEventListener("onto:pick", function (e) {
    window.location.href = obConfig.termUrl + "?o=" + encodeURIComponent(obConfig.prefix) + "&id=" + encodeURIComponent(e.detail.id);
  });
})();

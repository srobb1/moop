/* ============================================================================
 * image-gallery.js — the image gallery's filter box (tools/pages/image_gallery.php)
 *
 * Filters the tiles as you type on what each tile carries (data-search, written by the
 * page): gene IDs and names, anatomy term names and IDs, caption, kind. Every word typed
 * must appear. A stage with no match is hidden; each stage's count bubble and its
 * "Jump to" row (js/modules/parent-nav.js, via "moop:nav-refresh") show the matching count.
 *
 * A tile knows only the IDs printed on it. Another ID for the same gene will not match,
 * so the no-match note links to the gallery's server-side gene filter (?gene=), which
 * looks a gene up under all of its IDs.
 * ==========================================================================*/
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", function () {
    var box = document.getElementById("galleryFilter");
    if (!box) return;
    var status = document.getElementById("galleryFilterStatus");
    var none = document.getElementById("galleryFilterNone");
    var geneLink = document.getElementById("galleryFilterGeneLink");
    var geneBase = geneLink ? geneLink.getAttribute("href") : "";
    var sections = Array.prototype.slice.call(document.querySelectorAll(".gallery-stage"));
    var total = document.querySelectorAll(".gallery-stage .gene-image-tile").length;

    function imagesWords(n) {
      return n === 1 ? moopT("%s image", n) : moopT("%s images", n);
    }

    function apply() {
      var words = box.value.toLowerCase().trim().split(/\s+/).filter(Boolean);
      var shown = 0;
      sections.forEach(function (sec) {
        var n = 0;
        sec.querySelectorAll(".gene-image-tile").forEach(function (tile) {
          var hay = tile.getAttribute("data-search") || "";
          var hit = words.every(function (w) { return hay.indexOf(w) !== -1; });
          tile.hidden = !hit;
          if (hit) n++;
        });
        shown += n;
        // Keep the section's own count (and the menu's, which reads these) in step.
        sec.hidden = n === 0;
        sec.setAttribute("data-nav-count", String(n));
        sec.setAttribute("data-nav-count-words", imagesWords(n));
        var pill = sec.querySelector(".gallery-stage-count");
        if (pill) {
          pill.textContent = String(n);
          pill.title = imagesWords(n);
          pill.setAttribute("aria-label", imagesWords(n));
        }
      });
      status.textContent = words.length ? moopT("Showing %s of %s images", shown, total) : "";
      none.hidden = !(words.length && shown === 0);
      if (geneLink && words.length) {
        geneLink.href = geneBase + (geneBase.indexOf("?") === -1 ? "?" : "&") + "gene=" + encodeURIComponent(box.value.trim());
      }
      document.dispatchEvent(new CustomEvent("moop:nav-refresh"));
    }

    var timer = null;
    box.addEventListener("input", function () {
      clearTimeout(timer);
      timer = setTimeout(apply, 150);
    });
    if (box.value) apply();    // the browser restored a typed value (back button)
  });
})();

/**
 * Gene page "Images" card: a thumbnail opens a quick view (Bootstrap modal) with the larger
 * image or the movie, and its facts. Everything shown comes from the tile's data-* attributes,
 * which the server filled after its access check; files load through api/gene_image.php.
 * Loaded only on gene pages that have images (tools/parent.php).
 */
(function () {
  "use strict";
  var modalEl = document.getElementById("geneImageModal");
  if (!modalEl || !window.bootstrap) return;
  var modal = new bootstrap.Modal(modalEl);
  var full  = modalEl.querySelector(".gene-image-full");
  var facts = modalEl.querySelector(".gene-image-facts");
  var open  = modalEl.querySelector(".gene-image-open");
  var title = modalEl.querySelector(".modal-title");

  function fact(label, value) {
    if (!value) return;
    var dt = document.createElement("dt"); dt.className = "col-sm-3"; dt.textContent = label;
    var dd = document.createElement("dd"); dd.className = "col-sm-9"; dd.textContent = value;
    facts.appendChild(dt); facts.appendChild(dd);
  }

  document.addEventListener("click", function (e) {
    var tile = e.target.closest(".gene-image-tile");
    if (!tile) return;
    var d = tile.dataset;
    title.textContent = d.title || "";
    full.textContent = ""; facts.textContent = "";
    if (d.movie) {
      var v = document.createElement("video");
      v.controls = true; v.preload = "metadata"; v.src = d.full; v.className = "gene-image-media";
      full.appendChild(v);
    } else {
      var img = document.createElement("img");
      img.src = d.full; img.alt = d.title || ""; img.className = "gene-image-media";
      full.appendChild(img);
    }
    fact(moopT("Caption"), d.caption);
    fact(moopT("Genes"), d.genes);
    fact(moopT("Anatomy"), d.terms);
    fact(moopT("Credit"), d.credit);
    open.href = d.full;
    modal.show();
  });

  // Stop a movie when the quick view closes.
  modalEl.addEventListener("hidden.bs.modal", function () { full.textContent = ""; });
})();

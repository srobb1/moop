/* ============================================================================
 * isoform-minimap.js — a thumbnail of each isoform in its annotation card header
 *
 * A gene page with 17 transcripts is 63,000px tall. By the time a reader has
 * scrolled into one transcript's annotation tables, the gene structure diagram is
 * far off-screen and the only thing identifying which isoform they are reading is
 * an accession that differs from its neighbours by one digit. This puts the shape
 * back beside the name.
 *
 * DELIBERATELY NOT INTERACTIVE. It is a reminder, not a control: no click, no
 * hover, no tooltip, aria-hidden. The card header is already a collapse toggle,
 * so anything clickable here would compete with it.
 *
 * Costs nothing to serve: geneModelData is already inlined for the main diagram,
 * so this adds no request, no query and no markup from PHP. It draws after
 * DOMContentLoaded from data already in memory.
 *
 * ALL MINIMAPS SHARE ONE SCALE — the gene's full span. Scaling each to its own
 * width would make a two-exon isoform look identical to a twenty-exon one, and
 * make a short transcript look full length, which is worse than showing nothing.
 * ==========================================================================*/
(function () {
    "use strict";

    var W = 132;   // px — fits beside the badge without pushing the header taller
    var H = 14;

    // Same palette as the main diagram (js/modules/gene-model-viewer.js). Kept in
    // step by eye, not by import, because the two files are loaded independently —
    // if the diagram's colours change, change them here too.
    var COLOR_BACKBONE = "#c8ccd4";
    var COLOR_EXON     = "#e8833a";   // UTR-ish exon on a coding transcript
    var COLOR_CDS      = "#2171b5";
    var COLOR_NOCDS    = "#17becf";   // exon on a transcript with no CDS

    document.addEventListener("DOMContentLoaded", function () {
        if (typeof geneModelData === "undefined" || !geneModelData) return;

        var isoforms = geneModelData.isoforms || [];
        var gene     = geneModelData.gene || {};
        if (!isoforms.length) return;

        // Gene span, falling back to the isoform extent when the gene record is thin.
        var gStart = Number(gene.start);
        var gEnd   = Number(gene.end);
        if (!isFinite(gStart) || !isFinite(gEnd) || gEnd <= gStart) {
            gStart = Math.min.apply(null, isoforms.map(function (i) { return i.start; }));
            gEnd   = Math.max.apply(null, isoforms.map(function (i) { return i.end; }));
        }
        var span = gEnd - gStart;
        if (!(span > 0)) return;

        // Draw 5'->3' left to right, matching the main diagram: on the minus strand
        // the whole picture mirrors, so a reader is not asked to hold two conventions.
        var flip = gene.strand === "-";
        function x(pos) {
            var f = (pos - gStart) / span;
            return (flip ? 1 - f : f) * W;
        }
        function rect(a, b, y, h, fill) {
            var x1 = x(a), x2 = x(b);
            var left = Math.min(x1, x2);
            var w = Math.max(Math.abs(x2 - x1), 0.8);   // never vanish entirely
            return '<rect x="' + left.toFixed(1) + '" y="' + y + '" width="' + w.toFixed(1) +
                   '" height="' + h + '" fill="' + fill + '" rx="0.5"/>';
        }

        isoforms.forEach(function (iso) {
            if (!iso.anchor) return;
            var card = document.getElementById(iso.anchor);
            if (!card) return;
            var header = card.querySelector(".card-header");
            if (!header || header.querySelector(".iso-minimap")) return;

            var hasCds   = iso.cds && iso.cds.length > 0;
            var exonFill = hasCds ? COLOR_EXON : COLOR_NOCDS;

            var parts = [];
            // Backbone spans only this isoform, so a short transcript reads as short.
            parts.push(rect(iso.start, iso.end, H / 2 - 0.75, 1.5, COLOR_BACKBONE));
            (iso.exons || []).forEach(function (e) {
                parts.push(rect(e.start, e.end, 4, H - 8, exonFill));
            });
            // CDS drawn over the exons and taller, exactly as the main diagram does.
            (iso.cds || []).forEach(function (c) {
                parts.push(rect(c.start, c.end, 2, H - 4, COLOR_CDS));
            });

            var svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
            svg.setAttribute("class", "iso-minimap");
            svg.setAttribute("width", W);
            svg.setAttribute("height", H);
            svg.setAttribute("viewBox", "0 0 " + W + " " + H);
            svg.setAttribute("aria-hidden", "true");     // decorative; the name carries the meaning
            svg.setAttribute("focusable", "false");
            svg.style.pointerEvents = "none";            // never intercept the collapse toggle
            svg.innerHTML = parts.join("");

            // Order is ID, count, then picture. The count is what a reader scans when
            // deciding whether to open a collapsed card, so it should not sit behind the
            // drawing. Falls back to the name badge if a transcript has no count element.
            var after = header.querySelector(".child-annot-count") ||
                        header.querySelector(".child-feature-badge");
            if (after && after.parentNode) after.parentNode.insertBefore(svg, after.nextSibling);
            else header.appendChild(svg);
        });
    });
})();

/**
 * Gene Model Viewer
 * Renders an SVG gene structure diagram (isoforms, exons, CDS, intron direction)
 * Data comes from window.geneModelData injected inline by parent.php
 *
 * Display is always 5′→3′ left-to-right:
 *   - Forward (+) strand: genomic left = 5′
 *   - Reverse (−) strand: coordinates are flipped so 5′ (high coord) appears on the left
 */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const VIRTUAL_WIDTH = 800;
    const ROW_HEIGHT    = 34;
    const LABEL_HEIGHT  = 16;
    const PAD_TOP       = 4;
    const PAD_BOTTOM    = 18;   // room for strand label
    const PAD_LEFT      = 14;
    const PAD_RIGHT     = 14;
    const EXON_H        = 10;
    const CDS_H         = 16;

    const COLOR_BACKBONE = '#aaa';
    const COLOR_EXON     = '#e8833a';   // warm orange — UTR / exon background
    const COLOR_CDS      = '#2171b5';   // dark blue — CDS
    const COLOR_LABEL    = '#555';

    function init() {
        if (typeof geneModelData === 'undefined' || !geneModelData) return;
        const svg = document.getElementById('gene-model-svg');
        if (!svg) return;
        render(geneModelData, svg);
    }

    function render(data, svg) {
        const { gene, isoforms } = data;
        if (!isoforms || isoforms.length === 0) return;

        const trackW = VIRTUAL_WIDTH - PAD_LEFT - PAD_RIGHT;
        const totalH = PAD_TOP + isoforms.length * (ROW_HEIGHT + LABEL_HEIGHT) + PAD_BOTTOM;
        const flip   = gene.strand === '-';

        svg.setAttribute('viewBox', `0 0 ${VIRTUAL_WIDTH} ${totalH}`);
        svg.setAttribute('height', totalH);

        // Map a genomic position to a screen x, always placing 5′ on the left.
        // For minus-strand genes, high genomic coords (5′ end) map to the left.
        function toX(pos) {
            const frac = flip
                ? (gene.end - pos) / (gene.end - gene.start)
                : (pos - gene.start) / (gene.end - gene.start);
            return PAD_LEFT + frac * trackW;
        }

        isoforms.forEach((iso, i) => {
            const rowTop = PAD_TOP + i * (ROW_HEIGHT + LABEL_HEIGHT);
            const cy     = rowTop + LABEL_HEIGHT + ROW_HEIGHT / 2;

            const g = makeSvgEl('g');

            // Label above the track
            const label = makeSvgEl('text');
            label.setAttribute('x', PAD_LEFT);
            label.setAttribute('y', rowTop + LABEL_HEIGHT - 2);
            label.setAttribute('font-size', '11');
            label.setAttribute('fill', COLOR_LABEL);
            label.textContent = iso.id;
            g.appendChild(label);

            // Backbone — full isoform span (min/max toX to stay correct after flip)
            const xL = Math.min(toX(iso.start), toX(iso.end));
            const xR = Math.max(toX(iso.start), toX(iso.end));
            g.appendChild(makeLine(xL, xR, cy, cy, COLOR_BACKBONE, 1.5));

            // Sort exons/CDS by visual (screen) position
            const exons   = [...iso.exons].sort((a, b) => toX(a.start) - toX(b.start));
            const cdsList = [...iso.cds  ].sort((a, b) => toX(a.start) - toX(b.start));

            // Exon boxes (orange — UTR / exon background)
            exons.forEach(ex => {
                const x1 = Math.min(toX(ex.start), toX(ex.end));
                const w  = Math.max(2, Math.abs(toX(ex.end) - toX(ex.start)));
                g.appendChild(makeRect(x1, cy - EXON_H / 2, w, EXON_H, COLOR_EXON, 2));
            });

            // CDS boxes (blue, taller — drawn on top of exon boxes)
            cdsList.forEach(cds => {
                const x1 = Math.min(toX(cds.start), toX(cds.end));
                const w  = Math.max(2, Math.abs(toX(cds.end) - toX(cds.start)));
                g.appendChild(makeRect(x1, cy - CDS_H / 2, w, CDS_H, COLOR_CDS, 2));
            });

            // Tooltip
            const title = document.createElementNS(NS, 'title');
            title.textContent = iso.id;
            g.appendChild(title);

            svg.appendChild(g);
        });

        // Strand label bottom-right
        const strandText = makeSvgEl('text');
        strandText.setAttribute('x', VIRTUAL_WIDTH - PAD_RIGHT);
        strandText.setAttribute('y', totalH - 4);
        strandText.setAttribute('text-anchor', 'end');
        strandText.setAttribute('font-size', '11');
        strandText.setAttribute('fill', COLOR_LABEL);
        strandText.textContent = flip
            ? '→ 5′ to 3′  (reverse strand, flipped)'
            : '→ 5′ to 3′  (forward strand)';
        svg.appendChild(strandText);
    }

    // SVG element helpers
    function makeSvgEl(tag) { return document.createElementNS(NS, tag); }

    function makeLine(x1, x2, y1, y2, stroke, sw) {
        const el = makeSvgEl('line');
        el.setAttribute('x1', x1); el.setAttribute('x2', x2);
        el.setAttribute('y1', y1); el.setAttribute('y2', y2);
        el.setAttribute('stroke', stroke);
        el.setAttribute('stroke-width', sw);
        return el;
    }

    function makeRect(x, y, w, h, fill, rx) {
        const el = makeSvgEl('rect');
        el.setAttribute('x', x);     el.setAttribute('y', y);
        el.setAttribute('width', w);  el.setAttribute('height', h);
        el.setAttribute('fill', fill);
        if (rx) el.setAttribute('rx', rx);
        return el;
    }

    document.addEventListener('DOMContentLoaded', init);
})();

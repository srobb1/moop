/**
 * Gene Model Viewer
 * Renders an SVG gene structure diagram (isoforms, exons, CDS, intron direction)
 * Data comes from window.geneModelData injected inline by parent.php
 */
(function () {
    'use strict';

    const NS = 'http://www.w3.org/2000/svg';
    const VIRTUAL_WIDTH = 800;
    const ROW_HEIGHT    = 34;
    const LABEL_HEIGHT  = 16;
    const PAD_TOP       = 4;
    const PAD_BOTTOM    = 8;
    const PAD_LEFT      = 14;
    const PAD_RIGHT     = 14;
    const EXON_H        = 10;
    const CDS_H         = 16;

    const COLOR_BACKBONE = '#aaa';
    const COLOR_EXON     = '#90b8d8';   // UTR / exon background
    const COLOR_CDS      = '#2171b5';   // CDS fill
    const COLOR_CHEVRON  = '#888';
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

        const trackW  = VIRTUAL_WIDTH - PAD_LEFT - PAD_RIGHT;
        const totalH  = PAD_TOP + isoforms.length * (ROW_HEIGHT + LABEL_HEIGHT) + PAD_BOTTOM;

        svg.setAttribute('viewBox', `0 0 ${VIRTUAL_WIDTH} ${totalH}`);
        svg.setAttribute('height', totalH);

        function toX(pos) {
            return PAD_LEFT + ((pos - gene.start) / (gene.end - gene.start)) * trackW;
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

            // Backbone line across the full isoform span
            g.appendChild(makeLine(toX(iso.start), toX(iso.end), cy, cy, COLOR_BACKBONE, 1.5));

            // Sort exons by coordinate
            const exons = [...iso.exons].sort((a, b) => a.start - b.start);
            const cdsList = [...iso.cds].sort((a, b) => a.start - b.start);

            // Strand chevrons in intron gaps (between consecutive exons)
            for (let e = 0; e < exons.length - 1; e++) {
                drawChevrons(g, toX(exons[e].end), toX(exons[e + 1].start), cy, gene.strand);
            }
            // Arrowhead at 3′ end for single-exon genes (no intron to put chevrons on)
            if (exons.length === 1) {
                drawArrowhead(g, toX(iso.start), toX(iso.end), cy, gene.strand);
            }

            // Exon boxes (lighter — shows UTR region)
            exons.forEach(ex => {
                const w = Math.max(2, toX(ex.end) - toX(ex.start));
                g.appendChild(makeRect(toX(ex.start), cy - EXON_H / 2, w, EXON_H, COLOR_EXON, 2));
            });

            // CDS boxes (taller, darker — drawn on top)
            cdsList.forEach(cds => {
                const w = Math.max(2, toX(cds.end) - toX(cds.start));
                g.appendChild(makeRect(toX(cds.start), cy - CDS_H / 2, w, CDS_H, COLOR_CDS, 2));
            });

            // Tooltip
            const title = document.createElementNS(NS, 'title');
            title.textContent = iso.id;
            g.appendChild(title);

            svg.appendChild(g);
        });

        // Strand indicator bottom-right
        const strandText = makeSvgEl('text');
        strandText.setAttribute('x', VIRTUAL_WIDTH - PAD_RIGHT);
        strandText.setAttribute('y', totalH - 2);
        strandText.setAttribute('text-anchor', 'end');
        strandText.setAttribute('font-size', '11');
        strandText.setAttribute('fill', COLOR_LABEL);
        strandText.textContent = gene.strand === '+' ? '→ forward strand' : '← reverse strand';
        svg.appendChild(strandText);
    }

    // Draw small direction chevrons spaced across an intron region
    function drawChevrons(g, x1, x2, cy, strand) {
        const span = x2 - x1;
        if (span < 10) return;
        const size = 5;
        // Place one chevron at midpoint; add more for long introns
        const count = Math.max(1, Math.min(5, Math.floor(span / 60)));
        for (let i = 0; i < count; i++) {
            const cx = x1 + (span / (count + 1)) * (i + 1);
            const poly = makeSvgEl('polyline');
            if (strand === '+') {
                poly.setAttribute('points',
                    `${cx - size},${cy - size} ${cx},${cy} ${cx - size},${cy + size}`);
            } else {
                poly.setAttribute('points',
                    `${cx + size},${cy - size} ${cx},${cy} ${cx + size},${cy + size}`);
            }
            poly.setAttribute('fill', 'none');
            poly.setAttribute('stroke', COLOR_CHEVRON);
            poly.setAttribute('stroke-width', '1.5');
            g.appendChild(poly);
        }
    }

    // Small filled arrowhead at the 3′ tip (for single-exon genes)
    function drawArrowhead(g, x1, x2, cy, strand) {
        const size = 6;
        const ax   = strand === '+' ? x2 : x1;
        const dir  = strand === '+' ? 1 : -1;
        const poly = makeSvgEl('polygon');
        poly.setAttribute('points',
            `${ax},${cy} ${ax - dir * size},${cy - size / 2} ${ax - dir * size},${cy + size / 2}`);
        poly.setAttribute('fill', COLOR_CHEVRON);
        g.appendChild(poly);
    }

    // SVG helpers
    function makeSvgEl(tag) {
        return document.createElementNS(NS, tag);
    }
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
        el.setAttribute('x', x);    el.setAttribute('y', y);
        el.setAttribute('width', w); el.setAttribute('height', h);
        el.setAttribute('fill', fill);
        if (rx) el.setAttribute('rx', rx);
        return el;
    }

    document.addEventListener('DOMContentLoaded', init);
})();

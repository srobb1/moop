/**
 * Gene Model Viewer
 * Renders an SVG gene structure diagram (isoforms, exons, CDS, intron direction)
 * Data comes from window.geneModelData injected inline by parent.php
 *
 * Display is always 5′→3′ left-to-right:
 *   - Forward (+) strand: genomic left = 5′
 *   - Reverse (−) strand: coordinates are flipped so 5′ (high coord) appears on the left
 *
 * Clickable regions (SVG image-map style):
 *   - Exon rect   → fetch & show that exon's DNA sequence
 *   - CDS rect    → fetch & show that CDS's DNA sequence
 *   - Intron gap  → fetch & show that intron's DNA sequence
 *   - Row bg/label → smooth-scroll to the isoform's annotation section
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

    // Simple fetch cache: key = "seqname:start:end:strand" → sequence string
    const seqCache = new Map();
    let   currentRegion   = null;
    let   currentSequence = null;

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
            g.setAttribute('class', 'iso-row');

            // Invisible background rect for hover highlight and click hit-area
            const bg = makeRect(0, rowTop, VIRTUAL_WIDTH, ROW_HEIGHT + LABEL_HEIGHT, 'transparent', 0);
            bg.setAttribute('class', 'iso-row-bg');
            g.appendChild(bg);

            // Label above the track
            const label = makeSvgEl('text');
            label.setAttribute('x', PAD_LEFT);
            label.setAttribute('y', rowTop + LABEL_HEIGHT - 2);
            label.setAttribute('font-size', '11');
            label.setAttribute('fill', COLOR_LABEL);
            label.textContent = iso.id;
            g.appendChild(label);

            // Backbone — full isoform span
            const xL = Math.min(toX(iso.start), toX(iso.end));
            const xR = Math.max(toX(iso.start), toX(iso.end));
            g.appendChild(makeLine(xL, xR, cy, cy, COLOR_BACKBONE, 1.5));

            // Sort exons by genomic coordinate to derive introns
            const genomicExons = [...iso.exons].sort((a, b) => a.start - b.start);

            // Intron click-target rects (invisible, cover gap between consecutive exons)
            // Appended before exons so exons are on top and receive clicks in exon areas.
            for (let j = 0; j < genomicExons.length - 1; j++) {
                const intronStart = genomicExons[j].end + 1;
                const intronEnd   = genomicExons[j + 1].start - 1;
                if (intronEnd < intronStart) continue;
                const ix1 = Math.min(toX(intronStart), toX(intronEnd));
                const iw  = Math.max(4, Math.abs(toX(intronEnd) - toX(intronStart)));
                const ir  = makeRect(ix1, cy - EXON_H / 2, iw, EXON_H, 'transparent', 0);
                ir.setAttribute('class', 'region-intron');
                ir.style.cursor = 'pointer';
                addRegionTitle(ir, `Intron  ${intronStart.toLocaleString()}–${intronEnd.toLocaleString()}`);
                ir.addEventListener('click', e => {
                    e.stopPropagation();
                    showSequenceModal({
                        type: 'Intron', isoform: iso.id,
                        start: intronStart, end: intronEnd,
                        seqname: gene.seqname, strand: gene.strand,
                    });
                });
                g.appendChild(ir);
            }

            // Exon boxes (orange — UTR / exon background)
            const exons = [...iso.exons].sort((a, b) => toX(a.start) - toX(b.start));
            exons.forEach(ex => {
                const x1   = Math.min(toX(ex.start), toX(ex.end));
                const w    = Math.max(2, Math.abs(toX(ex.end) - toX(ex.start)));
                const rect = makeRect(x1, cy - EXON_H / 2, w, EXON_H, COLOR_EXON, 2);
                rect.setAttribute('class', 'region-exon');
                rect.style.cursor = 'pointer';
                addRegionTitle(rect, `Exon  ${ex.start.toLocaleString()}–${ex.end.toLocaleString()}`);
                rect.addEventListener('click', e => {
                    e.stopPropagation();
                    showSequenceModal({
                        type: 'Exon', isoform: iso.id,
                        start: ex.start, end: ex.end,
                        seqname: gene.seqname, strand: gene.strand,
                    });
                });
                g.appendChild(rect);
            });

            // CDS boxes (blue, taller — drawn on top of exon boxes)
            const cdsList = [...iso.cds].sort((a, b) => toX(a.start) - toX(b.start));
            cdsList.forEach(cds => {
                const x1   = Math.min(toX(cds.start), toX(cds.end));
                const w    = Math.max(2, Math.abs(toX(cds.end) - toX(cds.start)));
                const rect = makeRect(x1, cy - CDS_H / 2, w, CDS_H, COLOR_CDS, 2);
                rect.setAttribute('class', 'region-cds');
                rect.style.cursor = 'pointer';
                addRegionTitle(rect, `CDS  ${cds.start.toLocaleString()}–${cds.end.toLocaleString()}`);
                rect.addEventListener('click', e => {
                    e.stopPropagation();
                    showSequenceModal({
                        type: 'CDS', isoform: iso.id,
                        start: cds.start, end: cds.end,
                        seqname: gene.seqname, strand: gene.strand,
                    });
                });
                g.appendChild(rect);
            });

            // Tooltip on the row background (navigate hint)
            const title = document.createElementNS(NS, 'title');
            title.textContent = iso.anchor ? iso.id + ' — click to view annotations' : iso.id;
            g.appendChild(title);

            // Row click (label / backbone / bg area) → scroll to annotation section
            if (iso.anchor) {
                g.style.cursor = 'pointer';
                g.addEventListener('click', () => {
                    const target = document.getElementById(iso.anchor);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            }

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

    // -------------------------------------------------------------------------
    // Sequence modal
    // -------------------------------------------------------------------------

    function ensureModal() {
        if (document.getElementById('seq-region-modal')) return;
        const html = `
<div class="modal fade" id="seq-region-modal" tabindex="-1" aria-labelledby="seq-region-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-semibold" id="seq-region-modal-label">Region Sequence</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="seq-region-loading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
        </div>
        <div id="seq-region-content" style="display:none;">
          <dl class="row mb-3 small" id="seq-region-meta"></dl>
          <pre id="seq-region-sequence" class="seq-region-pre"></pre>
          <div class="d-flex gap-2 mt-2">
            <button class="btn btn-sm btn-outline-secondary" id="seq-region-copy">
              <i class="fas fa-copy me-1"></i>Copy sequence
            </button>
            <button class="btn btn-sm btn-outline-success" id="seq-region-download">
              <i class="fas fa-download me-1"></i>Download FASTA
            </button>
          </div>
        </div>
        <div id="seq-region-error" class="alert alert-danger mb-0" style="display:none;"></div>
      </div>
    </div>
  </div>
</div>`;
        document.body.insertAdjacentHTML('beforeend', html);

        document.getElementById('seq-region-copy').addEventListener('click', () => {
            const raw = (document.getElementById('seq-region-sequence').textContent || '')
                .replace(/^\s*\d+\s+/gm, '')   // strip position numbers
                .replace(/\s/g, '');
            const btn = document.getElementById('seq-region-copy');
            const success = () => {
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy sequence'; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(raw).then(success).catch(() => execCopy(raw, success));
            } else {
                execCopy(raw, success);
            }
        });

        document.getElementById('seq-region-download').addEventListener('click', () => {
            if (!currentSequence || !currentRegion) return;
            const r   = currentRegion;
            const seq = currentSequence.replace(/\s/g, '');
            const header = `>${r.isoform} ${r.type} ${r.seqname}:${r.start}-${r.end}(${r.strand})`;
            const body   = seq.match(/.{1,60}/g).join('\n');
            const blob   = new Blob([header + '\n' + body + '\n'], { type: 'text/plain' });
            const url    = URL.createObjectURL(blob);
            const a      = document.createElement('a');
            a.href       = url;
            a.download   = `${r.isoform}_${r.type}_${r.seqname}-${r.start}-${r.end}.fa`;
            a.click();
            URL.revokeObjectURL(url);
        });
    }

    function showSequenceModal(region) {
        ensureModal();

        const modalEl = document.getElementById('seq-region-modal');
        const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

        // Reset UI
        document.getElementById('seq-region-loading').style.display = 'block';
        document.getElementById('seq-region-content').style.display  = 'none';
        document.getElementById('seq-region-error').style.display    = 'none';
        document.getElementById('seq-region-modal-label').textContent =
            `${region.type} — ${region.isoform}`;

        modal.show();

        const cacheKey = `${region.seqname}:${region.start}:${region.end}:${region.strand}`;
        if (seqCache.has(cacheKey)) {
            renderSequenceResult(seqCache.get(cacheKey), region);
            return;
        }

        const site = (typeof moopSite !== 'undefined') ? moopSite : '/moop';
        const params = new URLSearchParams({
            organism: moopOrganism,
            assembly: moopAssembly,
            seqname:  region.seqname,
            start:    region.start,
            end:      region.end,
            strand:   region.strand,
        });

        fetch(`${site}/api/get_sequence.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                seqCache.set(cacheKey, data);
                renderSequenceResult(data, region);
            })
            .catch(err => {
                document.getElementById('seq-region-loading').style.display = 'none';
                const el = document.getElementById('seq-region-error');
                el.textContent = err.message;
                el.style.display = 'block';
            });
    }

    function renderSequenceResult(data, region) {
        currentRegion   = region;
        currentSequence = data.sequence;

        // Metadata
        const strandLabel = region.strand === '-' ? '− (reverse complement shown)' : '+ (forward)';
        document.getElementById('seq-region-meta').innerHTML = `
            <dt class="col-sm-3">Isoform</dt><dd class="col-sm-9">${region.isoform}</dd>
            <dt class="col-sm-3">Region</dt><dd class="col-sm-9">${region.seqname}:${region.start.toLocaleString()}–${region.end.toLocaleString()}</dd>
            <dt class="col-sm-3">Strand</dt><dd class="col-sm-9">${strandLabel}</dd>
            <dt class="col-sm-3">Length</dt><dd class="col-sm-9">${data.length.toLocaleString()} bp</dd>`;

        // Sequence formatted 60 bp per line with 1-based position labels
        const seq     = data.sequence;
        const lineLen = 60;
        let formatted = '';
        for (let i = 0; i < seq.length; i += lineLen) {
            const pos   = region.start + i;
            const chunk = seq.slice(i, i + lineLen);
            formatted  += `${String(pos).padStart(9)}  ${chunk}\n`;
        }
        document.getElementById('seq-region-sequence').textContent = formatted.trimEnd();

        document.getElementById('seq-region-loading').style.display = 'none';
        document.getElementById('seq-region-content').style.display  = 'block';
    }

    // -------------------------------------------------------------------------
    // SVG element helpers
    // -------------------------------------------------------------------------

    // Clipboard fallback for non-HTTPS contexts.
    // Must append inside an open Bootstrap modal (if any) to avoid the focus trap
    // blocking selection on elements outside the modal.
    function execCopy(text, onSuccess) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:absolute;top:-9999px;left:-9999px;opacity:0;width:1px;height:1px';
        const container = document.querySelector('.modal.show') || document.body;
        container.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, text.length);
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) {}
        container.removeChild(ta);
        if (ok) onSuccess();
    }

    function makeSvgEl(tag) { return document.createElementNS(NS, tag); }

    function addRegionTitle(el, text) {
        const t = document.createElementNS(NS, 'title');
        t.textContent = text + ' — click to view sequence';
        el.appendChild(t);
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
        el.setAttribute('x', x);     el.setAttribute('y', y);
        el.setAttribute('width', w);  el.setAttribute('height', h);
        el.setAttribute('fill', fill);
        if (rx) el.setAttribute('rx', rx);
        return el;
    }

    document.addEventListener('DOMContentLoaded', init);
})();


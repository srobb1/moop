/**
 * Gene Model Viewer
 * Renders an SVG gene structure diagram (isoforms, exons, CDS, intron direction)
 * Data comes from window.geneModelData injected inline by parent.php
 *
 * Display is always 5′→3′ left-to-right:
 *   - Forward (+) strand: genomic left = 5′
 *   - Reverse (−) strand: coordinates are flipped so 5′ (high coord) appears on the left
 *
 * Clickable regions (SVG image-map style, only when genomeSequenceAvailable === true):
 *   - Gene row [> seq] button → multi-FASTA: full gene locus + each isoform span
 *   - Exon rect   → fetch & show that exon's sequence
 *   - CDS rect    → fetch & show that CDS's sequence
 *   - Intron gap  → fetch & show that intron's sequence
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

    // Fetch cache: key = "seqname:start:end:strand" → API response object
    const seqCache = new Map();
    let   currentRegion   = null;
    let   currentSequence = null;
    let   currentFasta    = null;   // ready-to-download FASTA string (single or multi)

    function init() {
        if (typeof geneModelData === 'undefined' || !geneModelData) return;
        const svg = document.getElementById('gene-model-svg');
        if (!svg) return;
        render(geneModelData, svg);
    }

    function render(data, svg) {
        const { gene, isoforms } = data;
        if (!isoforms || isoforms.length === 0) return;

        const canFetchSeq = (typeof genomeSequenceAvailable !== 'undefined') && genomeSequenceAvailable;

        const trackW   = VIRTUAL_WIDTH - PAD_LEFT - PAD_RIGHT;
        const GENE_ROW = ROW_HEIGHT + LABEL_HEIGHT;   // height of the gene backbone row
        const totalH   = PAD_TOP + GENE_ROW + isoforms.length * (ROW_HEIGHT + LABEL_HEIGHT) + PAD_BOTTOM;
        const flip     = gene.strand === '-';

        svg.setAttribute('viewBox', `0 0 ${VIRTUAL_WIDTH} ${totalH}`);
        svg.setAttribute('height', totalH);
        if (canFetchSeq) svg.classList.add('seq-enabled');

        // Map a genomic position to a screen x, always placing 5′ on the left.
        function toX(pos) {
            const frac = flip
                ? (gene.end - pos) / (gene.end - gene.start)
                : (pos - gene.start) / (gene.end - gene.start);
            return PAD_LEFT + frac * trackW;
        }

        // -----------------------------------------------------------------
        // Gene backbone row — full locus span + [> seq] button
        // -----------------------------------------------------------------
        {
            const gRowTop = PAD_TOP;
            const gCy     = gRowTop + LABEL_HEIGHT + ROW_HEIGHT / 2;
            const gG      = makeSvgEl('g');

            const gLabel = makeSvgEl('text');
            gLabel.setAttribute('x', PAD_LEFT);
            gLabel.setAttribute('y', gRowTop + LABEL_HEIGHT - 2);
            gLabel.setAttribute('font-size', '11');
            gLabel.setAttribute('fill', COLOR_LABEL);
            gLabel.setAttribute('font-weight', 'bold');
            gLabel.textContent = gene.id || 'Gene';
            gG.appendChild(gLabel);

            const BTN_W   = canFetchSeq ? 40 : 0;
            const BTN_GAP = canFetchSeq ? 6  : 0;
            gG.appendChild(makeLine(PAD_LEFT, VIRTUAL_WIDTH - PAD_RIGHT - BTN_W - BTN_GAP, gCy, gCy, '#999', 1.5));

            if (canFetchSeq) {
                const BTN_H = 14;
                const bx    = VIRTUAL_WIDTH - PAD_RIGHT - BTN_W;
                const by    = gCy - BTN_H / 2;

                const btnRect = makeRect(bx, by, BTN_W, BTN_H, '#e8f0fe', 3);
                btnRect.setAttribute('stroke', '#5b8dee');
                btnRect.setAttribute('stroke-width', '1');
                gG.appendChild(btnRect);

                const btnLabel = makeSvgEl('text');
                btnLabel.setAttribute('x', bx + BTN_W / 2);
                btnLabel.setAttribute('y', gCy + 4);
                btnLabel.setAttribute('text-anchor', 'middle');
                btnLabel.setAttribute('font-size', '10');
                btnLabel.setAttribute('fill', '#2c5cc5');
                btnLabel.setAttribute('font-weight', 'bold');
                btnLabel.textContent = '> seq';
                gG.appendChild(btnLabel);

                const gTitle = document.createElementNS(NS, 'title');
                gTitle.textContent = 'Fetch full genomic sequence — gene locus + each isoform span';
                gG.appendChild(gTitle);

                gG.style.cursor = 'pointer';
                gG.addEventListener('click', () => showGenomicModal(gene, isoforms));
            }

            svg.appendChild(gG);
        }

        // -----------------------------------------------------------------
        // Isoform rows
        // -----------------------------------------------------------------
        isoforms.forEach((iso, i) => {
            const rowTop = PAD_TOP + GENE_ROW + i * (ROW_HEIGHT + LABEL_HEIGHT);
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
                if (canFetchSeq) {
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
                }
                g.appendChild(ir);
            }

            // Exon boxes (orange — UTR / exon background)
            const exons = [...iso.exons].sort((a, b) => toX(a.start) - toX(b.start));
            exons.forEach(ex => {
                const x1   = Math.min(toX(ex.start), toX(ex.end));
                const w    = Math.max(2, Math.abs(toX(ex.end) - toX(ex.start)));
                const rect = makeRect(x1, cy - EXON_H / 2, w, EXON_H, COLOR_EXON, 2);
                rect.setAttribute('class', 'region-exon');
                if (canFetchSeq) {
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
                }
                g.appendChild(rect);
            });

            // CDS boxes (blue, taller — drawn on top of exon boxes)
            const cdsList = [...iso.cds].sort((a, b) => toX(a.start) - toX(b.start));
            cdsList.forEach(cds => {
                const x1   = Math.min(toX(cds.start), toX(cds.end));
                const w    = Math.max(2, Math.abs(toX(cds.end) - toX(cds.start)));
                const rect = makeRect(x1, cy - CDS_H / 2, w, CDS_H, COLOR_CDS, 2);
                rect.setAttribute('class', 'region-cds');
                if (canFetchSeq) {
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
                }
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
    // Sequence fetch helper (shared by single-region and genomic modals)
    // -------------------------------------------------------------------------

    function fetchCachedSeq(seqname, start, end, strand) {
        const cacheKey = `${seqname}:${start}:${end}:${strand}`;
        if (seqCache.has(cacheKey)) return Promise.resolve(seqCache.get(cacheKey));
        const site   = (typeof moopSite !== 'undefined') ? moopSite : '/moop';
        const params = new URLSearchParams({ organism: moopOrganism, assembly: moopAssembly, seqname, start, end, strand });
        return fetch(`${site}/api/get_sequence.php?${params}`)
            .then(r => r.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                seqCache.set(cacheKey, data);
                return data;
            });
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
              <i class="fas fa-copy me-1"></i>Copy
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
                .replace(/[^\S\n]+/g, '')   // strip spaces/tabs, keep newlines for FASTA format
                .trimEnd();
            const btn = document.getElementById('seq-region-copy');
            const success = () => {
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
                setTimeout(() => { btn.innerHTML = '<i class="fas fa-copy me-1"></i>Copy'; }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(raw).then(success).catch(() => execCopy(raw, success));
            } else {
                execCopy(raw, success);
            }
        });

        document.getElementById('seq-region-download').addEventListener('click', () => {
            if (!currentFasta || !currentRegion) return;
            const r        = currentRegion;
            const filename = r.type === 'Genomic'
                ? `${r.id || r.seqname}_${r.start}-${r.end}_genomic.fa`
                : `${r.isoform}_${r.type}_${r.seqname}-${r.start}-${r.end}.fa`;
            const blob = new Blob([currentFasta + '\n'], { type: 'text/plain' });
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href = url; a.download = filename; a.click();
            URL.revokeObjectURL(url);
        });
    }

    function showSequenceModal(region) {
        ensureModal();

        const modalEl = document.getElementById('seq-region-modal');
        const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.getElementById('seq-region-loading').style.display = 'block';
        document.getElementById('seq-region-content').style.display  = 'none';
        document.getElementById('seq-region-error').style.display    = 'none';
        document.getElementById('seq-region-modal-label').textContent =
            `${region.type} — ${region.isoform}`;

        modal.show();

        fetchCachedSeq(region.seqname, region.start, region.end, region.strand)
            .then(data => renderSequenceResult(data, region))
            .catch(err => {
                document.getElementById('seq-region-loading').style.display = 'none';
                const el = document.getElementById('seq-region-error');
                el.textContent = err.message;
                el.style.display = 'block';
            });
    }

    function showGenomicModal(gene, isoforms) {
        ensureModal();

        const modalEl = document.getElementById('seq-region-modal');
        const modal   = bootstrap.Modal.getOrCreateInstance(modalEl);

        document.getElementById('seq-region-modal-label').textContent = 'Genomic Sequences';
        document.getElementById('seq-region-loading').style.display = 'block';
        document.getElementById('seq-region-content').style.display  = 'none';
        document.getElementById('seq-region-error').style.display    = 'none';

        modal.show();

        // One entry for the full gene locus, then one per isoform span (includes introns)
        const regions = [
            { label: gene.id || gene.seqname, type: 'Gene',    start: gene.start, end: gene.end },
            ...isoforms.map(iso => ({ label: iso.id,           type: 'Isoform',   start: iso.start, end: iso.end })),
        ];

        Promise.all(regions.map(r => fetchCachedSeq(gene.seqname, r.start, r.end, gene.strand)))
            .then(results => {
                let fasta = '';
                results.forEach((data, idx) => {
                    const r    = regions[idx];
                    const hdr  = `>${r.label} ${r.type} genomic ${gene.seqname}:${r.start}-${r.end}(${gene.strand})`;
                    const body = data.sequence.match(/.{1,60}/g)?.join('\n') ?? data.sequence;
                    fasta += hdr + '\n' + body + '\n\n';
                });
                fasta = fasta.trimEnd();

                const strandLabel = gene.strand === '-' ? '− (reverse complement shown)' : '+ (forward)';
                document.getElementById('seq-region-meta').innerHTML = `
                    <dt class="col-sm-3">Type</dt><dd class="col-sm-9"><strong>Genomic</strong></dd>
                    <dt class="col-sm-3">Gene locus</dt><dd class="col-sm-9">${gene.seqname}:${gene.start.toLocaleString()}–${gene.end.toLocaleString()}</dd>
                    <dt class="col-sm-3">Strand</dt><dd class="col-sm-9">${strandLabel}</dd>
                    <dt class="col-sm-3">Sequences</dt><dd class="col-sm-9">${results.length} (gene locus + ${isoforms.length} isoform${isoforms.length !== 1 ? 's' : ''})</dd>`;

                document.getElementById('seq-region-sequence').textContent = fasta;

                currentRegion   = { type: 'Genomic', id: gene.id, seqname: gene.seqname, start: gene.start, end: gene.end, strand: gene.strand };
                currentSequence = null;
                currentFasta    = fasta;

                document.getElementById('seq-region-loading').style.display = 'none';
                document.getElementById('seq-region-content').style.display  = 'block';
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

        const strandLabel = region.strand === '-' ? '− (reverse complement shown)' : '+ (forward)';
        document.getElementById('seq-region-meta').innerHTML = `
            <dt class="col-sm-3">Type</dt><dd class="col-sm-9"><strong>${region.type}</strong></dd>
            <dt class="col-sm-3">Isoform</dt><dd class="col-sm-9">${region.isoform}</dd>
            <dt class="col-sm-3">Region</dt><dd class="col-sm-9">${region.seqname}:${region.start.toLocaleString()}–${region.end.toLocaleString()}</dd>
            <dt class="col-sm-3">Strand</dt><dd class="col-sm-9">${strandLabel}</dd>
            <dt class="col-sm-3">Length</dt><dd class="col-sm-9">${data.length.toLocaleString()} bp</dd>`;

        // Sequence as FASTA — matches the downloaded file exactly
        const seq    = data.sequence;
        const header = `>${region.isoform} ${region.type} ${region.seqname}:${region.start}-${region.end}(${region.strand})`;
        const body   = seq.match(/.{1,60}/g)?.join('\n') ?? seq;
        currentFasta = header + '\n' + body;
        document.getElementById('seq-region-sequence').textContent = currentFasta;

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

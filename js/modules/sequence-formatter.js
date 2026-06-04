/**
 * Sequence Formatter
 *
 * Adds a "Format Sequence" button to the gene model card header.
 * Opens a modal showing the isoform's genomic sequence (5'→3') with
 * each feature type highlighted in a colour consistent with the gene
 * diagram. Where two highlighted types physically overlap (e.g. an
 * "exon" interval that contains a "CDS" interval), the highlight
 * colours are blended by RGB average to produce an intuitive third.
 *
 * Controls per type: Highlight on/off, Case (As-is / UPPER / lower),
 * Bold, Italic, Underline.
 *
 * Copy buttons:
 *   "Copy (rich text)"  — clipboard text/html; highlight + bold/italic
 *                         survive paste into Word / Google Docs.
 *   "Copy (plain text)" — textContent only; case transforms survive,
 *                         styling is stripped.
 *
 * Dependencies: geneModelData, genomeSequenceAvailable, moopOrganism,
 *               moopAssembly, moopSite  (all injected inline by parent.php)
 */
(function () {
    'use strict';

    // ── Type metadata ─────────────────────────────────────────────────────────
    // hl      = pastel highlight colour for the sequence background
    // swatch  = diagram colour shown in the controls row label
    const TMETA = {
        CDS:             { label: 'CDS',    hl: '#bbdefb', swatch: '#2171b5' },
        exon:            { label: 'Exon',   hl: '#ffd8a8', swatch: '#e8833a' },
        five_prime_utr:  { label: "5' UTR", hl: '#ffd8a8', swatch: '#e8833a' },
        three_prime_utr: { label: "3' UTR", hl: '#ffd8a8', swatch: '#e8833a' },
        utr:             { label: 'UTR',    hl: '#ffd8a8', swatch: '#e8833a' },
        intron:          { label: 'Intron', hl: '#e8eaed', swatch: '#aaaaaa' },
    };
    function tmeta(t) { return TMETA[t] || { label: t, hl: '#f0f0f0', swatch: '#999' }; }

    // Preferred display order
    const TYPE_ORDER = ['CDS', 'five_prime_utr', 'three_prime_utr', 'utr', 'exon', 'intron'];

    // ── Colour blend ──────────────────────────────────────────────────────────
    function blendHex(colors) {
        if (!colors.length) return null;
        const [r, g, b] = colors
            .map(c => { const n = parseInt(c.slice(1), 16); return [(n >> 16) & 0xff, (n >> 8) & 0xff, n & 0xff]; })
            .reduce(([ar, ag, ab], [cr, cg, cb]) => [ar + cr, ag + cg, ab + cb], [0, 0, 0])
            .map(v => v / colors.length);
        return '#' + [r, g, b].map(v => Math.round(v).toString(16).padStart(2, '0')).join('');
    }

    // ── Local sequence fetch (own cache — independent of gene-model-viewer) ───
    const _seqCache = new Map();
    function fetchSeq(seqname, start, end, strand) {
        const key = `${seqname}:${start}:${end}:${strand}`;
        if (_seqCache.has(key)) return Promise.resolve(_seqCache.get(key));
        const site   = (typeof moopSite !== 'undefined') ? moopSite : '/moop';
        const params = new URLSearchParams({ organism: moopOrganism, assembly: moopAssembly, seqname, start, end, strand });
        return fetch(`${site}/api/get_sequence.php?${params}`)
            .then(r => r.json())
            .then(data => { if (data.error) throw new Error(data.error); _seqCache.set(key, data); return data; });
    }

    // ── Interval helpers ──────────────────────────────────────────────────────
    /**
     * Convert genomic intervals from an isoform into 0-based sequence-space
     * intervals, accounting for strand (minus strand = reverse complement).
     * Also derives intron intervals as gaps between merged exon+CDS blocks.
     *
     * Returns [{s, e, type}] where s/e are 0-based inclusive in sequence space.
     */
    function buildIntervals(isoform) {
        const flip = isoform.strand === '-';
        const base = flip ? isoform.end : isoform.start;
        const out  = [];

        const push = (gStart, gEnd, type) => {
            const s = flip ? (base - gEnd)   : (gStart - base);
            const e = flip ? (base - gStart) : (gEnd   - base);
            out.push({ s, e, type });
        };

        for (const c of (isoform.cds   || [])) push(c.start, c.end, 'CDS');
        for (const x of (isoform.exons || [])) push(x.start, x.end, (x.type || 'exon').toLowerCase());

        // Derive introns: gaps between merged exon + CDS intervals
        const raw = [...(isoform.cds || []), ...(isoform.exons || [])]
            .map(x => [x.start, x.end])
            .sort((a, b) => a[0] - b[0]);
        if (raw.length > 1) {
            const m = [raw[0].slice()];
            for (let i = 1; i < raw.length; i++) {
                const last = m[m.length - 1];
                if (raw[i][0] <= last[1] + 1) last[1] = Math.max(last[1], raw[i][1]);
                else m.push(raw[i].slice());
            }
            for (let i = 1; i < m.length; i++) {
                push(m[i - 1][1] + 1, m[i][0] - 1, 'intron');
            }
        }

        return out;
    }

    /**
     * Compute segments: contiguous runs of sequence positions that share
     * an identical set of active interval types.  Uses a boundary-scan
     * rather than per-character iteration, so it is O(k²) in the number
     * of intervals — efficient even for genes with many exons.
     *
     * Returns [{start, end, types: Set<string>}] — 0-based inclusive.
     */
    function computeSegments(intervals, seqLen) {
        const bounds = new Set([0, seqLen]);
        for (const { s, e } of intervals) {
            const cs = Math.max(0, s), ce = Math.min(seqLen - 1, e);
            if (cs <= ce) { bounds.add(cs); bounds.add(ce + 1); }
        }
        const sorted = [...bounds].sort((a, b) => a - b);
        const segs = [];
        for (let i = 0; i < sorted.length - 1; i++) {
            const start = sorted[i], end = sorted[i + 1] - 1;
            const types = new Set();
            for (const { s, e, type } of intervals) {
                if (Math.max(0, s) <= start && end <= Math.min(seqLen - 1, e)) types.add(type);
            }
            segs.push({ start, end, types });
        }
        return segs;
    }

    // ── Module state ──────────────────────────────────────────────────────────
    let activeIso  = null;
    let seqStr     = null;
    let segs       = null;
    let present    = [];   // ordered list of type names in this isoform
    let cfg        = {};   // type → {hl, caseMode, bold, italic, underline}

    // ── Controls ──────────────────────────────────────────────────────────────
    function defaultCfg(types) {
        const c = {};
        for (const t of types) {
            c[t] = {
                hl:       true,                  // all types highlighted by default
                caseMode: t === 'intron' ? 'lower' : 'normal',
                bold:     false,
                italic:   false,
                underline:false,
            };
        }
        return c;
    }

    function buildControls() {
        const tbody = document.getElementById('sf-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';
        for (const t of present) {
            const m = tmeta(t), s = cfg[t];
            const tr = document.createElement('tr');
            tr.innerHTML =
                `<td class="ps-2">` +
                  `<span style="display:inline-block;width:10px;height:10px;background:${m.swatch};border-radius:2px;margin-right:5px;vertical-align:middle;border:1px solid rgba(0,0,0,.15)"></span>` +
                  `<span class="small fw-semibold">${m.label}</span>` +
                `</td>` +
                `<td class="text-center"><input type="checkbox" class="form-check-input sf-ctrl" data-t="${t}" data-p="hl" ${s.hl ? 'checked' : ''}></td>` +
                `<td><select class="form-select form-select-sm sf-ctrl" data-t="${t}" data-p="case" style="min-width:80px">` +
                  `<option value="normal"${s.caseMode === 'normal' ? ' selected' : ''}>As-is</option>` +
                  `<option value="upper"${s.caseMode === 'upper'   ? ' selected' : ''}>UPPER</option>` +
                  `<option value="lower"${s.caseMode === 'lower'   ? ' selected' : ''}>lower</option>` +
                `</select></td>` +
                btnTd(t, 'bold',      'B', 'font-weight:bold')      +
                btnTd(t, 'italic',    'I', 'font-style:italic')     +
                btnTd(t, 'underline', 'U', 'text-decoration:underline');
            tbody.appendChild(tr);
        }
    }

    function btnTd(t, prop, label, style) {
        const active = cfg[t][prop];
        return `<td class="text-center">` +
            `<button type="button" style="${style};min-width:26px" ` +
            `class="btn btn-xs sf-ctrl sf-tog ${active ? 'btn-secondary' : 'btn-outline-secondary'}" ` +
            `data-t="${t}" data-p="${prop}">${label}</button></td>`;
    }

    function wireControls() {
        const tbody = document.getElementById('sf-tbody');
        if (!tbody) return;
        tbody.addEventListener('change', onCtrl);
        tbody.addEventListener('click',  onCtrl);
    }

    function onCtrl(e) {
        const el = e.target.closest('.sf-ctrl');
        if (!el) return;
        const t = el.dataset.t, p = el.dataset.p;
        if (p === 'hl'   && e.type === 'change') { cfg[t].hl = el.checked; render(); return; }
        if (p === 'case' && e.type === 'change') { cfg[t].caseMode = el.value; render(); return; }
        if (['bold', 'italic', 'underline'].includes(p) && e.type === 'click') {
            cfg[t][p] = !cfg[t][p];
            el.classList.toggle('btn-secondary',         cfg[t][p]);
            el.classList.toggle('btn-outline-secondary', !cfg[t][p]);
            render();
        }
    }

    // ── Renderer ──────────────────────────────────────────────────────────────
    const WRAP = 60;

    function render() {
        const el = document.getElementById('sf-seq');
        if (!el || !seqStr || !segs) return;

        let html = '';
        let col  = 0;

        function emitChunk(text, style) {
            while (text.length) {
                const space = WRAP - col;
                const chunk = text.slice(0, space);
                html += style ? `<span style="${style}">${chunk}</span>` : chunk;
                col  += chunk.length;
                text  = text.slice(chunk.length);
                if (col >= WRAP) { html += '\n'; col = 0; }
            }
        }

        for (const seg of segs) {
            const hlColors = [];
            let bold = false, italic = false, underline = false, caseMode = 'normal';

            for (const t of seg.types) {
                const s = cfg[t];
                if (!s) continue;
                if (s.hl) hlColors.push(tmeta(t).hl);
                if (s.bold)      bold      = true;
                if (s.italic)    italic    = true;
                if (s.underline) underline = true;
                if (s.caseMode === 'upper') caseMode = 'upper';
                else if (s.caseMode === 'lower' && caseMode !== 'upper') caseMode = 'lower';
            }

            const bg = blendHex(hlColors);
            let style = '';
            if (bg)        style += `background:${bg};`;
            if (bold)      style += 'font-weight:bold;';
            if (italic)    style += 'font-style:italic;';
            if (underline) style += 'text-decoration:underline;';

            let text = seqStr.slice(seg.start, seg.end + 1);
            if (caseMode === 'upper')      text = text.toUpperCase();
            else if (caseMode === 'lower') text = text.toLowerCase();

            emitChunk(text, style);
        }

        el.innerHTML = html;
    }

    // ── Copy ──────────────────────────────────────────────────────────────────
    function copyFeedback(btn, label) {
        const orig = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check me-1"></i>Copied!';
        setTimeout(() => { btn.innerHTML = orig; }, 2000);
    }

    async function copyRich() {
        const el  = document.getElementById('sf-seq');
        const btn = document.getElementById('sf-copy-rich');
        const htmlContent = `<div style="font-family:monospace;white-space:pre;">${el.innerHTML}</div>`;
        try {
            await navigator.clipboard.write([
                new ClipboardItem({ 'text/html': new Blob([htmlContent], { type: 'text/html' }) })
            ]);
            copyFeedback(btn, btn.innerHTML);
        } catch {
            // Fallback: select element content and execCommand
            const sel   = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(el);
            sel.removeAllRanges();
            sel.addRange(range);
            document.execCommand('copy');
            sel.removeAllRanges();
            copyFeedback(btn, btn.innerHTML);
        }
    }

    async function copyPlain() {
        const el  = document.getElementById('sf-seq');
        const btn = document.getElementById('sf-copy-plain');
        const text = (el.textContent || '').trimEnd();
        try {
            await navigator.clipboard.writeText(text);
        } catch {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            ta.remove();
        }
        copyFeedback(btn, btn.innerHTML);
    }

    // ── Modal ─────────────────────────────────────────────────────────────────
    function ensureModal() {
        if (document.getElementById('sf-modal')) return;
        document.body.insertAdjacentHTML('beforeend', `
<div class="modal fade" id="sf-modal" tabindex="-1" aria-labelledby="sf-modal-label" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2">
        <div>
          <h6 class="modal-title mb-0 fw-semibold" id="sf-modal-label">
            <i class="fas fa-palette me-1"></i>Format Sequence
          </h6>
          <small class="text-muted" id="sf-isoform-label"></small>
        </div>
        <button type="button" class="btn-close ms-3" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="sf-loading" class="text-center py-4">
          <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading…</span></div>
          <p class="text-muted mt-2 mb-0 small">Fetching sequence…</p>
        </div>
        <div id="sf-error" class="alert alert-danger mb-0" style="display:none;"></div>
        <div id="sf-content" style="display:none;">
          <table class="table table-sm table-bordered mb-3" style="width:auto;">
            <thead class="table-light">
              <tr>
                <th class="ps-2">Type</th>
                <th class="text-center">Highlight</th>
                <th>Case</th>
                <th class="text-center" style="min-width:32px">B</th>
                <th class="text-center" style="min-width:32px">I</th>
                <th class="text-center" style="min-width:32px">U</th>
              </tr>
            </thead>
            <tbody id="sf-tbody"></tbody>
          </table>
          <pre id="sf-seq" style="font-family:monospace;font-size:0.85em;line-height:1.8;white-space:pre;overflow-x:auto;background:#f8f9fa;padding:14px;border-radius:4px;border:1px solid #dee2e6;margin-bottom:8px;"></pre>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm btn-outline-secondary" id="sf-copy-rich">
              <i class="fas fa-paint-brush me-1"></i>Copy (rich text)
            </button>
            <button class="btn btn-sm btn-outline-secondary" id="sf-copy-plain">
              <i class="fas fa-copy me-1"></i>Copy (plain text)
            </button>
            <small class="text-muted ms-1">Rich text preserves highlight and style when pasting into Word or Google&nbsp;Docs.</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>`);

        document.getElementById('sf-copy-rich').addEventListener('click',  copyRich);
        document.getElementById('sf-copy-plain').addEventListener('click', copyPlain);
    }

    function showModal() {
        ensureModal();
        bootstrap.Modal.getOrCreate(document.getElementById('sf-modal')).show();
    }

    function setLoading(on) {
        document.getElementById('sf-loading').style.display = on  ? '' : 'none';
        document.getElementById('sf-content').style.display = on  ? 'none' : '';
        document.getElementById('sf-error').style.display   = 'none';
    }

    function showError(msg) {
        document.getElementById('sf-loading').style.display = 'none';
        document.getElementById('sf-content').style.display = 'none';
        const el = document.getElementById('sf-error');
        el.style.display = '';
        el.textContent = msg;
    }

    // ── Open formatter for a given isoform ────────────────────────────────────
    function openFormatter(isoform) {
        activeIso = isoform;
        showModal();
        setLoading(true);

        document.getElementById('sf-isoform-label').textContent = isoform.id || '';

        const gene = geneModelData.gene;
        fetchSeq(gene.seqname, isoform.start, isoform.end, isoform.strand)
            .then(data => {
                seqStr = data.sequence || '';
                const ivals = buildIntervals(isoform);
                segs = computeSegments(ivals, seqStr.length);

                // Collect present types in preferred order
                const found = new Set();
                for (const seg of segs) { for (const t of seg.types) found.add(t); }
                present = TYPE_ORDER.filter(t => found.has(t));
                // Append any unexpected types not in the canonical order
                for (const t of found) { if (!present.includes(t)) present.push(t); }

                cfg = defaultCfg(present);

                setLoading(false);
                buildControls();
                wireControls();
                render();
            })
            .catch(err => showError('Could not fetch sequence: ' + err.message));
    }

    // ── Button init ───────────────────────────────────────────────────────────
    function init() {
        if (typeof geneModelData === 'undefined' || !geneModelData) return;
        if (typeof genomeSequenceAvailable === 'undefined' || !genomeSequenceAvailable) return;

        const btn      = document.getElementById('gene-model-fmt-btn');
        if (!btn) return;
        const isoforms = geneModelData.isoforms || [];
        if (!isoforms.length) { btn.style.display = 'none'; return; }

        if (isoforms.length === 1) {
            btn.addEventListener('click', () => openFormatter(isoforms[0]));
        } else {
            // Convert plain button to a Bootstrap dropdown
            const wrap = document.createElement('div');
            wrap.className = 'dropdown';
            btn.parentNode.replaceChild(wrap, btn);
            btn.setAttribute('data-bs-toggle', 'dropdown');
            btn.classList.add('dropdown-toggle');
            wrap.appendChild(btn);

            const menu = document.createElement('ul');
            menu.className = 'dropdown-menu dropdown-menu-end';
            isoforms.forEach((iso, i) => {
                const li = document.createElement('li');
                const a  = document.createElement('a');
                a.className = 'dropdown-item small';
                a.href = '#';
                a.textContent = iso.id || `Isoform ${i + 1}`;
                a.addEventListener('click', e => { e.preventDefault(); openFormatter(iso); });
                li.appendChild(a);
                menu.appendChild(li);
            });
            wrap.appendChild(menu);
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();

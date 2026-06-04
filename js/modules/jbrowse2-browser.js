/**
 * Genome Browser — JBrowse2 selector and launcher
 *
 * Handles organism/assembly selection UI and iframe launch.
 * For deep-links (jbDlOrganism + jbDlAssembly set), launches immediately.
 *
 * Depends on inline vars injected by jbrowse2.php:
 *   jbDlOrganism, jbDlAssembly, jbDlLoc,
 *   jbGeneTracks, jbSessionTracks, jbSessionTrackId
 */
(function () {
    'use strict';

    const site = (typeof moopSite !== 'undefined') ? moopSite : '/moop';

    // ── URL builder ───────────────────────────────────────────────────────────
    function buildIframeUrl(organism, assembly, loc) {
        const configUrl = `${site}/api/jbrowse2/config.php`
            + `?organism=${encodeURIComponent(organism)}`
            + `&assembly=${encodeURIComponent(assembly)}`;

        const assemblyName = `${organism}_${assembly}`;
        const meta = (typeof jbAssemblyMeta !== 'undefined' && jbAssemblyMeta[assemblyName])
                      ? jbAssemblyMeta[assemblyName] : {};

        // Prefer explicit loc, fall back to first scaffold from .fai
        const effectiveLoc = loc || meta.firstLoc || '';

        // Prefer deep-link gene tracks (already set by PHP), fall back to meta
        const geneTracks = [
            ...(Array.isArray(jbGeneTracks) && jbGeneTracks.length
                ? jbGeneTracks
                : (meta.geneTracks || [])),
            ...(jbSessionTrackId ? [jbSessionTrackId] : []),
        ];

        let url = `${site}/jbrowse2/index.html?config=${encodeURIComponent(configUrl)}`
                + `&assembly=${encodeURIComponent(assemblyName)}`
                + `&tracklist=true`;

        if (effectiveLoc) url += `&loc=${encodeURIComponent(effectiveLoc)}`;
        if (geneTracks.length) url += `&tracks=${encodeURIComponent(geneTracks.join(','))}`;
        if (jbSessionTracks)   url += `&sessionTracks=${encodeURIComponent(jbSessionTracks)}`;

        return url;
    }

    // ── Resize iframe to fill remaining viewport ──────────────────────────────
    function resizeViewer() {
        const wrap = document.getElementById('jb-iframe-wrap');
        if (!wrap || wrap.closest('#jb-viewer')?.style.display === 'none') return;
        const top = wrap.getBoundingClientRect().top + window.scrollY;
        wrap.style.height = (window.innerHeight - top - 8) + 'px';
    }

    // ── Launch iframe ─────────────────────────────────────────────────────────
    function launch(organism, assembly, loc) {
        const selector = document.getElementById('jb-selector-card');
        const viewer   = document.getElementById('jb-viewer');
        const iframe   = document.getElementById('jb-iframe');
        const label    = document.getElementById('jb-viewer-label');
        const footer   = document.querySelector('footer');

        if (selector) selector.style.display = 'none';
        if (viewer)   viewer.style.display   = '';
        if (footer)   footer.style.display   = 'none';
        if (label)    label.textContent       = `${organism.replace(/_/g, ' ')} — ${assembly}`;
        if (iframe)   iframe.src              = buildIframeUrl(organism, assembly, loc);

        resizeViewer();
        window.addEventListener('resize', resizeViewer);
    }

    // ── Organism / assembly selector ──────────────────────────────────────────
    let selectedOrg = null;
    let selectedAsm = null;

    function selectOrganism(row) {
        // Deselect previous
        document.querySelectorAll('.jb-org-row.jb-selected').forEach(r => {
            r.classList.remove('jb-selected');
            r.style.background = '';
        });

        row.classList.add('jb-selected');
        row.style.background = 'rgba(8,145,178,0.08)';
        selectedOrg = row.dataset.org;
        selectedAsm = null;

        const assemblies = JSON.parse(row.dataset.assemblies || '[]');
        const label      = document.getElementById('jb-asm-label');
        const list       = document.getElementById('jb-asm-list');
        const launchBtn  = document.getElementById('jb-launch-btn');
        const selLabel   = document.getElementById('jb-selection-label');

        if (label) label.textContent = `Assemblies for ${selectedOrg.replace(/_/g, ' ')}`;
        if (launchBtn) launchBtn.disabled = true;
        if (selLabel)  selLabel.textContent = 'No assembly selected';

        if (!list) return;

        if (!assemblies.length) {
            list.innerHTML = '<p class="text-muted small p-3">No assemblies available.</p>';
            return;
        }

        list.innerHTML = assemblies.map(asm => `
            <div class="jb-asm-row px-3 py-2 border-bottom d-flex align-items-center gap-2"
                 style="cursor:pointer;" data-asm="${asm}">
              <i class="fas fa-circle-dot text-muted small jb-asm-radio"></i>
              <span class="small font-monospace">${asm}</span>
            </div>`
        ).join('');

        // Auto-select if only one
        if (assemblies.length === 1) {
            selectAssembly(list.querySelector('.jb-asm-row'), assemblies[0]);
        } else {
            list.querySelectorAll('.jb-asm-row').forEach(r => {
                r.addEventListener('click', () => selectAssembly(r, r.dataset.asm));
            });
        }
    }

    function selectAssembly(row, asm) {
        document.querySelectorAll('.jb-asm-row.jb-selected').forEach(r => {
            r.classList.remove('jb-selected');
            r.style.background = '';
            r.querySelector('.jb-asm-radio')?.setAttribute('class', 'fas fa-circle-dot text-muted small jb-asm-radio');
        });

        row.classList.add('jb-selected');
        row.style.background = 'rgba(8,145,178,0.08)';
        row.querySelector('.jb-asm-radio')?.setAttribute('class', 'fas fa-circle-dot small jb-asm-radio');
        row.querySelector('.jb-asm-radio').style.color = '#0891b2';

        selectedAsm = asm;

        const launchBtn = document.getElementById('jb-launch-btn');
        const selLabel  = document.getElementById('jb-selection-label');
        if (launchBtn) launchBtn.disabled = false;
        if (selLabel)  selLabel.textContent = `${selectedOrg?.replace(/_/g, ' ')} — ${asm}`;
    }

    // ── Filter ────────────────────────────────────────────────────────────────
    function initFilter() {
        const input = document.getElementById('jb-org-filter');
        if (!input) return;
        input.addEventListener('input', function () {
            const term = this.value.toLowerCase();
            document.querySelectorAll('.jb-org-row').forEach(row => {
                row.style.display = row.dataset.search.includes(term) ? '' : 'none';
            });
        });
    }

    // ── Fullscreen / new window ───────────────────────────────────────────────
    function initViewerControls() {
        const fsBtn  = document.getElementById('jb-fullscreen-btn');
        const nwBtn  = document.getElementById('jb-newwindow-btn');
        const back   = document.getElementById('jb-back-btn');
        const wrap   = document.getElementById('jb-iframe-wrap');
        const iframe = document.getElementById('jb-iframe');

        if (fsBtn && wrap) {
            fsBtn.addEventListener('click', () => {
                const full = wrap.classList.toggle('jb-overlay-full');
                if (full) {
                    Object.assign(wrap.style, { position:'fixed', top:'0', left:'0',
                        width:'100vw', height:'100vh', zIndex:'9999', border:'none', borderRadius:'0' });
                } else {
                    Object.assign(wrap.style, { position:'', top:'', left:'',
                        width:'', zIndex:'', border:'', borderRadius:'' });
                    resizeViewer();
                }
                fsBtn.querySelector('i').className = full ? 'fas fa-compress' : 'fas fa-expand';
            });
        }

        if (nwBtn && iframe) {
            nwBtn.addEventListener('click', () => {
                if (iframe.src) window.open(iframe.src, '_blank');
            });
        }

        if (back) {
            back.addEventListener('click', () => {
                const selector = document.getElementById('jb-selector-card');
                const viewer   = document.getElementById('jb-viewer');
                const footer   = document.querySelector('footer');
                if (selector) selector.style.display = '';
                if (viewer)   viewer.style.display   = 'none';
                if (footer)   footer.style.display   = '';
                if (iframe)   iframe.src              = '';
                window.removeEventListener('resize', resizeViewer);
            });
        }
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        // Deep-link: launch immediately
        if (typeof jbDlOrganism === 'string' && jbDlOrganism &&
            typeof jbDlAssembly === 'string' && jbDlAssembly) {
            initViewerControls();
            launch(jbDlOrganism, jbDlAssembly, jbDlLoc || '');
            return;
        }

        // Selector mode
        initFilter();
        initViewerControls();

        document.querySelectorAll('.jb-org-row').forEach(row => {
            row.addEventListener('click', () => selectOrganism(row));
            row.addEventListener('mouseenter', () => {
                if (!row.classList.contains('jb-selected')) row.style.background = '#f8f9fa';
            });
            row.addEventListener('mouseleave', () => {
                if (!row.classList.contains('jb-selected')) row.style.background = '';
            });
        });

        const launchBtn = document.getElementById('jb-launch-btn');
        if (launchBtn) {
            launchBtn.addEventListener('click', () => {
                if (selectedOrg && selectedAsm) launch(selectedOrg, selectedAsm, '');
            });
        }
    });
})();

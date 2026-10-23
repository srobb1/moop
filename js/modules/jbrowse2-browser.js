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

    const site = (typeof moopSite !== 'undefined') ? moopSite : window.sitePath;

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
            // May be a comma-separated list: a linkout can add several session
            // tracks that all need opening, not just the first.
            ...(jbSessionTrackId ? String(jbSessionTrackId).split(',').filter(Boolean) : []),
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

        const nameMap = (typeof jbAssemblyNames !== 'undefined' && jbAssemblyNames[selectedOrg]) ? jbAssemblyNames[selectedOrg] : {};
        list.innerHTML = assemblies.map(asm => {
            const name = nameMap[asm];
            const label = name
                ? `<span class="small">${name}</span> <span class="text-muted small font-monospace">(${asm})</span>`
                : `<span class="small font-monospace">${asm}</span>`;
            return `<div class="jb-asm-row px-3 py-2 border-bottom d-flex align-items-center gap-2"
                 style="cursor:pointer;" data-asm="${asm}">
              <i class="fas fa-dot-circle text-muted small jb-asm-radio"></i>
              <div>${label}</div>
            </div>`;
        }).join('');

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
            r.querySelector('.jb-asm-radio')?.setAttribute('class', 'fas fa-dot-circle text-muted small jb-asm-radio');
        });

        row.classList.add('jb-selected');
        row.style.background = 'rgba(8,145,178,0.08)';
        row.querySelector('.jb-asm-radio')?.setAttribute('class', 'fas fa-dot-circle small jb-asm-radio');
        row.querySelector('.jb-asm-radio').style.color = '#0891b2';

        selectedAsm = asm;

        const launchBtn = document.getElementById('jb-launch-btn');
        const selLabel  = document.getElementById('jb-selection-label');
        if (launchBtn) launchBtn.disabled = false;
        if (selLabel)  selLabel.textContent = `${selectedOrg?.replace(/_/g, ' ')} — ${asm}`;
    }

    // ── Filter ────────────────────────────────────────────────────────────────
    let activeGroupFilter = null;

    function applyFilters() {
        const term = (document.getElementById('jb-org-filter')?.value || '').trim().toLowerCase();
        document.querySelectorAll('.jb-org-row').forEach(row => {
            const matchesText  = !term || (row.dataset.search || '').includes(term);
            const rowGroups    = JSON.parse(row.dataset.groups || '[]');
            const matchesGroup = !activeGroupFilter || rowGroups.includes(activeGroupFilter);
            row.classList.toggle('jb-hidden', !(matchesText && matchesGroup));
        });
    }

    function initFilter() {
        const input = document.getElementById('jb-org-filter');
        if (input) input.addEventListener('input', applyFilters);

        document.querySelectorAll('.jb-group-chip').forEach(chip => {
            chip.addEventListener('click', function () {
                const g = this.dataset.group;
                if (activeGroupFilter === g) {
                    activeGroupFilter = null;
                    document.querySelectorAll('.jb-group-chip').forEach(c => c.style.opacity = '0.55');
                } else {
                    activeGroupFilter = g;
                    document.querySelectorAll('.jb-group-chip').forEach(c => {
                        c.style.opacity = c.dataset.group === g ? '1' : '0.35';
                    });
                }
                applyFilters();
            });
        });
    }

    // ── "Find tracks": open JBrowse's faceted track selector ─────────────────
    //
    // JBrowse 4.3 has no setting or URL parameter that opens the faceted selector: it is
    // local state behind one menu item (track list -> menu -> "Open faceted track selector").
    // JBrowse runs in a same-origin iframe, so this does those clicks for the user.
    //
    // Found by what JBrowse SAYS, never by position: the "Open track selector" button's title,
    // the "Filter tracks" box (its menu button sits in the same row -- the button has no label
    // of its own), the menu item's text, the dialog's heading. If a JBrowse upgrade renames
    // any of them, the button says how to do it by hand instead of silently doing nothing.
    const FACET_HINT = 'In the track list, use the \u2630 menu \u2192 "Open faceted track selector".';

    function waitFor(fn, ms) {
        return new Promise(resolve => {
            const t0 = Date.now();
            (function tick() {
                let v = null;
                try { v = fn(); } catch (e) { v = null; }
                if (v) return resolve(v);
                if (Date.now() - t0 > ms) return resolve(null);
                setTimeout(tick, 150);
            })();
        });
    }

    async function openFacetedSelector(iframe) {
        const doc = iframe && iframe.contentDocument;
        if (!doc) return false;
        const byText = (sel, re) => [...doc.querySelectorAll(sel)].find(e => re.test((e.textContent || '').trim()));
        const facetOpen = () => byText('[role=dialog] h2, [role=dialog] h6, [role=dialog] div', /^Faceted track selector$/);
        if (facetOpen()) return true;

        const filterLabel = () => byText('label', /^Filter tracks$/);
        if (!filterLabel()) {
            const open = await waitFor(() => doc.querySelector('button[title="Open track selector"]'), 15000);
            if (!open) return false;
            open.click();
            if (!await waitFor(filterLabel, 5000)) return false;
        }
        // The menu button shares the "Filter tracks" row. Walk up from the box to the first
        // ancestor holding a button that is NOT part of the box itself -- the box carries its
        // own clear (x) button, which is the first one found otherwise.
        const field = filterLabel().parentElement;
        let menuBtn = null;
        for (let row = field.parentElement; row && !menuBtn; row = row.parentElement) {
            menuBtn = [...row.querySelectorAll('button')].find(b => !field.contains(b)) || null;
        }
        if (!menuBtn) return false;
        menuBtn.click();
        const item = await waitFor(() => byText('[role=menuitem], li', /^Open faceted track selector$/), 3000);
        if (!item) return false;
        item.click();
        return !!await waitFor(facetOpen, 4000);
    }

    function initFacetButton(iframe) {
        const btn  = document.getElementById('jb-facet-btn');
        const note = document.getElementById('jb-facet-note');
        if (!btn || !iframe) return;
        btn.addEventListener('click', async () => {
            btn.disabled = true;
            if (note) note.classList.add('d-none');
            let ok = false;
            try { ok = await openFacetedSelector(iframe); } catch (e) { ok = false; }
            btn.disabled = false;
            if (!ok && note) {
                note.textContent = 'Could not open it automatically. ' + FACET_HINT;
                note.classList.remove('d-none');
            }
        });
    }

    // ── Fullscreen / new window ───────────────────────────────────────────────
    function initViewerControls() {
        const fsBtn  = document.getElementById('jb-fullscreen-btn');
        const nwBtn  = document.getElementById('jb-newwindow-btn');
        const back   = document.getElementById('jb-back-btn');
        const wrap   = document.getElementById('jb-iframe-wrap');
        const iframe = document.getElementById('jb-iframe');

        initFacetButton(iframe);

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

// Index page — organism selection
// Global state shared by all four tabs and the taxonomy tree

let selectedOrganisms = new Set();
let orgDataMap        = {};   // organism key → data object
let phyloTree         = null;

const GROUP_COLORS = [
    '#3498db','#e74c3c','#2ecc71','#f39c12','#9b59b6',
    '#1abc9c','#e67e22','#e91e63','#00bcd4','#795548','#607d8b'
];

function groupColor(name) {
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffffffff;
    return GROUP_COLORS[Math.abs(h) % GROUP_COLORS.length];
}

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ─── Organism / Taxon lists ────────────────────────────────────────────────

function renderOrganismList() {
    const list = document.getElementById('organism-select-list');
    if (!list) return;
    list.innerHTML = organismData.map(o => {
        const chips = o.groups.map(g =>
            `<span class="org-group-chip" style="background:${groupColor(g)}">${escHtml(g)}</span>`
        ).join('');
        const common = o.common_name
            ? `<span class="org-common text-muted">· ${escHtml(o.common_name)}</span>`
            : '';
        const searchText = [o.display_name, o.common_name, ...o.groups].join(' ').toLowerCase();
        return `<div class="org-select-row" data-org="${escHtml(o.organism)}" data-search="${escHtml(searchText)}">
            <span class="org-groups">${chips}</span>
            <span class="org-name"><em>${escHtml(o.display_name)}</em></span>
            ${common}
            <span class="org-check ms-auto"><i class="fas fa-check text-success"></i></span>
        </div>`;
    }).join('');

    list.addEventListener('click', e => {
        const row = e.target.closest('.org-select-row');
        if (row) toggleOrganism(row.dataset.org);
    });
}

function renderTaxonList() {
    const list = document.getElementById('taxon-select-list');
    if (!list) return;
    list.innerHTML = organismData.map(o => {
        const chain      = o.taxon_chain || [];
        const chainStr   = chain.join(' › ');
        const common     = o.common_name
            ? ` <span class="text-muted">· ${escHtml(o.common_name)}</span>` : '';
        const searchText = [o.display_name, o.common_name, ...chain].join(' ').toLowerCase();
        return `<div class="org-select-row taxon-row" data-org="${escHtml(o.organism)}" data-search="${escHtml(searchText)}">
            <div class="taxon-row-inner">
                <div class="taxon-path">${escHtml(chainStr)}</div>
                <div class="taxon-row-name">
                    <em>${escHtml(o.display_name)}</em>${common}
                    <span class="org-check"><i class="fas fa-check text-success"></i></span>
                </div>
            </div>
        </div>`;
    }).join('');

    list.addEventListener('click', e => {
        const row = e.target.closest('.org-select-row');
        if (row) toggleOrganism(row.dataset.org);
    });
}

function filterList(inputId, listId) {
    const q    = document.getElementById(inputId)?.value.toLowerCase() ?? '';
    const rows = document.querySelectorAll(`#${listId} .org-select-row`);
    rows.forEach(row => {
        row.classList.toggle('hidden', q !== '' && !row.dataset.search.includes(q));
    });
}

// ─── Selection state ───────────────────────────────────────────────────────

function toggleOrganism(org) {
    if (selectedOrganisms.has(org)) {
        selectedOrganisms.delete(org);
    } else {
        selectedOrganisms.add(org);
    }
    updateSelectedList();
    refreshAllHighlights();
    if (phyloTree) phyloTree.updateUI();
}

function removeOrganism(org) {
    selectedOrganisms.delete(org);
    updateSelectedList();
    refreshAllHighlights();
    if (phyloTree) phyloTree.updateUI();
}

function refreshAllHighlights() {
    document.querySelectorAll('.org-select-row').forEach(row => {
        row.classList.toggle('selected', selectedOrganisms.has(row.dataset.org));
    });
}

function updateSelectedList() {
    const listEl  = document.getElementById('selected-organisms-list');
    const countEl = document.getElementById('selected-count');
    if (!listEl) return;

    countEl.textContent = selectedOrganisms.size;

    // Enable/disable tool buttons and show/hide hint
    const hasSelection = selectedOrganisms.size > 0;
    document.querySelectorAll('[id^="tool-btn-"]').forEach(btn => {
        btn.disabled = !hasSelection;
    });
    const hint = document.getElementById('tool-select-hint');
    if (hint) hint.style.display = hasSelection ? 'none' : '';
    const wrapper = document.getElementById('tools-card-wrapper');
    if (wrapper) wrapper.classList.toggle('tools-locked', !hasSelection);
    const clearBtn = document.getElementById('clear-all-organisms');
    if (clearBtn) clearBtn.style.display = hasSelection ? '' : 'none';

    if (selectedOrganisms.size === 0) {
        listEl.innerHTML = '<div class="text-muted fst-italic small px-1">No organisms selected</div>';
        return;
    }

    const site = typeof sitePath !== 'undefined' ? sitePath.replace(/^\//, '').split('/')[0] : 'moop';
    listEl.innerHTML = Array.from(selectedOrganisms).map(org => {
        const info    = orgDataMap[org];
        const name    = info ? `<em>${escHtml(info.display_name)}</em>` : escHtml(org.replace(/_/g, ' '));
        const common  = info?.common_name
            ? ` <span class="text-muted">· ${escHtml(info.common_name)}</span>` : '';
        const pageUrl = `/${site}/tools/organism.php?organism=${encodeURIComponent(org)}`;
        return `<div class="selected-org-item d-flex align-items-center justify-content-between">
            <span class="selected-org-name">${name}${common}</span>
            <span class="selected-org-actions ms-2 flex-shrink-0 text-nowrap">
                <a href="${pageUrl}" target="_blank" class="btn btn-link btn-sm p-0 me-1" title="Open organism page">
                    <i class="fas fa-external-link-alt text-info"></i>
                </a>
                <button class="btn btn-link btn-sm p-0 text-danger remove-org-btn"
                        data-org="${escHtml(org)}" title="Remove">
                    <i class="fas fa-times"></i>
                </button>
            </span>
        </div>`;
    }).join('');
}

// ─── Tool submission ───────────────────────────────────────────────────────

function handleToolClick(toolId) {
    if (selectedOrganisms.size === 0) {
        alert('Please select at least one organism');
        return;
    }
    const btn = document.getElementById(`tool-btn-${toolId}`);
    if (!btn) return;
    const toolPath = btn.getAttribute('data-tool-path');
    if (!toolPath) return;

    const site = typeof sitePath !== 'undefined' ? sitePath.replace(/^\//,'').split('/')[0] : 'moop';
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/${site}${toolPath}`;
    form.target = '_blank';

    Array.from(selectedOrganisms).forEach(org => {
        const inp = document.createElement('input');
        inp.type  = 'hidden';
        inp.name  = 'organisms[]';
        inp.value = org;
        form.appendChild(inp);
    });

    document.body.appendChild(form);
    form.submit();
    document.body.removeChild(form);
}

// ─── Tree tab filter (reused from old code) ────────────────────────────────

function filterTaxonomyTree(filterText) {
    document.querySelectorAll('.phylo-node').forEach(node => {
        const matches = filterText === '' || node.textContent.toLowerCase().includes(filterText);
        node.style.display = matches ? '' : 'none';
        if (matches) {
            let parent = node.closest('.phylo-children')?.parentElement;
            while (parent?.classList.contains('phylo-node')) {
                parent.style.display = '';
                parent = parent.closest('.phylo-children')?.parentElement;
            }
        }
    });
}

// ─── Quick search ─────────────────────────────────────────────────────────

function initQuickSearch() {
    const input    = document.getElementById('qs-input');
    const dropdown = document.getElementById('qs-dropdown');
    const goBtn    = document.getElementById('qs-go');
    if (!input || !dropdown || typeof quickSearchData === 'undefined') return;

    const TYPE_LABEL = { organism: 'Organism', group: 'Group', assembly: 'Assembly', geneset: 'Gene Set' };
    let activeIdx = -1;

    function items() { return dropdown.querySelectorAll('.qs-item'); }

    function setActive(idx) {
        items().forEach((el, i) => el.classList.toggle('active', i === idx));
        activeIdx = idx;
    }

    function matchResults(q) {
        if (q.length < 2) return [];
        const words = q.toLowerCase().split(/\s+/).filter(Boolean);
        return quickSearchData
            .filter(d => words.every(w => d.search.includes(w)))
            .slice(0, 10);
    }

    function renderDropdown(q) {
        activeIdx = -1;
        const results = matchResults(q.trim());
        if (!q || q.trim().length < 2) {
            dropdown.classList.remove('open');
            dropdown.innerHTML = '';
            return;
        }
        if (results.length === 0) {
            dropdown.innerHTML = '<div class="qs-no-results">No matches found</div>';
            dropdown.classList.add('open');
            return;
        }
        dropdown.innerHTML = results.map((d, i) => {
            const sec = d.secondary ? `<span class="qs-secondary">${escHtml(d.secondary)}</span>` : '';
            return `<a class="qs-item" href="${escHtml(d.url)}" data-idx="${i}">
                <span class="qs-type qs-type-${escHtml(d.type)}">${TYPE_LABEL[d.type] || d.type}</span>
                <span class="qs-label">${escHtml(d.label)}</span>
                ${sec}
            </a>`;
        }).join('');
        dropdown.classList.add('open');
    }

    function navigate() {
        const all = items();
        const target = activeIdx >= 0 && all[activeIdx] ? all[activeIdx] : all[0];
        if (target) window.location.href = target.href;
    }

    input.addEventListener('input', () => renderDropdown(input.value));

    input.addEventListener('keydown', e => {
        const all = items();
        if (e.key === 'ArrowDown')  { e.preventDefault(); setActive(Math.min(activeIdx + 1, all.length - 1)); }
        else if (e.key === 'ArrowUp')   { e.preventDefault(); setActive(Math.max(activeIdx - 1, 0)); }
        else if (e.key === 'Enter')     { e.preventDefault(); navigate(); }
        else if (e.key === 'Escape')    { dropdown.classList.remove('open'); activeIdx = -1; }
    });

    goBtn.addEventListener('click', navigate);

    document.addEventListener('click', e => {
        if (!e.target.closest('.qs-wrap')) { dropdown.classList.remove('open'); activeIdx = -1; }
    });

    document.getElementById('qs-advanced-link')?.addEventListener('click', e => {
        e.preventDefault();
        document.getElementById('organism-tabs-anchor')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
}

// ─── Init ──────────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    // Quick search
    initQuickSearch();

    // Build lookup map
    organismData.forEach(o => { orgDataMap[o.organism] = o; });

    // Render both searchable lists
    renderOrganismList();
    renderTaxonList();

    // Set initial tool button state (nothing selected yet)
    updateSelectedList();

    // Filter inputs
    document.getElementById('organism-select-filter')
        ?.addEventListener('input', () => filterList('organism-select-filter', 'organism-select-list'));
    document.getElementById('taxon-select-filter')
        ?.addEventListener('input', () => filterList('taxon-select-filter', 'taxon-select-list'));
    document.getElementById('taxonomy-filter')
        ?.addEventListener('input', function () { filterTaxonomyTree(this.value.toLowerCase()); });

    // Init tree on first visit to Tree Select tab
    // shown.bs.tab fires on the button, not the pane — listen on the tab bar
    document.getElementById('organism-tabs')?.addEventListener('shown.bs.tab', e => {
        if (e.target.getAttribute('data-bs-target') === '#tab-tree-select') {
            if (!phyloTree) {
                phyloTree = new PhyloTree('taxonomy-tree-container', treeData, userAccess);
            }
            phyloTree.updateUI();
        }
    });

    // Remove-organism delegation on the selected panel
    document.getElementById('selected-organisms-list')
        ?.addEventListener('click', e => {
            const btn = e.target.closest('.remove-org-btn');
            if (btn) removeOrganism(btn.dataset.org);
        });

    // Clear all
    document.getElementById('clear-all-organisms')
        ?.addEventListener('click', () => {
            selectedOrganisms.clear();
            updateSelectedList();
            refreshAllHighlights();
            if (phyloTree) phyloTree.updateUI();
        });
});

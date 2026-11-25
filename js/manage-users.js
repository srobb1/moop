/**
 * Manage Users (admin/manage_users.php -> admin/pages/manage_users.php): the users table, the create /
 * edit forms with their organism / assembly access pickers, and the stale-reference confirmations.
 * Data from PHP via inline_scripts: allOrganisms, allOrganismsByGroup, allUsers (no passwords).
 * Moved out of the template 2026-11-25 (CLAUDE.md §7: no JavaScript logic in PHP files; fewer inline
 * scripts towards dropping 'unsafe-inline' from the CSP).
 */
(function () {
  'use strict';

  // ── State ─────────────────────────────────────────────────────────────
  let createSelectedAccess = {};
  let editSelectedAccess   = {};

  // ── Colour helpers ────────────────────────────────────────────────────
  const COLORS = ['#007bff','#28a745','#17a2b8','#ffc107','#dc3545','#6f42c1','#fd7e14','#20c997','#e83e8c','#6610f2'];
  const colorMap = {};
  let colorIdx = 0;
  function orgColor(org) {
    if (!colorMap[org]) colorMap[org] = COLORS[colorIdx++ % COLORS.length];
    return colorMap[org];
  }

  // ── Build one org row (chips + per-organism checkbox + count) ─────────
  // assembliesDict: {assembly: [gene_sets]} — e.g. {HIv3: ['v1'], GCA_1: ['v1','v2'], SmedSxl_v3.1: ['blast_only']}
  // selectedAccess: {org: {assembly: ['*'] | [gene_sets]}}
  // asmRefreshRegistry: optional map keyed 'org::asm' → refreshFn, filled here
  function buildOrgSection(organism, assembliesDict, selectedAccess, onGroupRefresh, onUpdate, asmRefreshRegistry) {
    const assemblies = Object.keys(assembliesDict);

    const orgSection = document.createElement('div');
    orgSection.className = 'org-section';
    orgSection.dataset.org = organism;

    const orgHeader = document.createElement('div');
    orgHeader.className = 'org-header d-flex align-items-center gap-2';

    const orgCb = document.createElement('input');
    orgCb.type = 'checkbox';
    orgCb.className = 'form-check-input flex-shrink-0';
    orgCb.style.cursor = 'pointer';

    const orgChevron = document.createElement('i');
    orgChevron.className = 'fa fa-chevron-right fa-fw text-muted';

    const orgLabel = document.createElement('span');
    orgLabel.className = 'flex-grow-1 fst-italic';
    orgLabel.style.fontSize = '12px';
    orgLabel.textContent = organism;

    const orgCount = document.createElement('span');
    orgCount.style.fontSize = '11px';

    const assemblyWrap = document.createElement('div');
    assemblyWrap.className = 'assembly-wrap pb-1';
    assemblyWrap.style.cssText = 'display:none; padding-left:44px;';

    function refreshOrg() {
      const sel   = assemblies.filter(a => selectedAccess[organism]?.[a] !== undefined).length;
      const total = assemblies.length;
      orgCount.textContent = `${sel}/${total}`;
      orgCount.className = sel === 0     ? 'badge bg-secondary'
                         : sel === total ? 'badge bg-success'
                         :                 'badge bg-warning text-dark';
      orgCb.checked       = sel === total && total > 0;
      orgCb.indeterminate = sel > 0 && sel < total;
      // Refresh each assembly chip (updates gene set badges too)
      assemblies.forEach(a => asmRefreshRegistry?.[`${organism}::${a}`]?.());
    }

    assemblies.forEach(assembly => {
      const geneSets  = assembliesDict[assembly] || [];
      const hasGeneSets = geneSets.length >= 1;
      const c             = orgColor(organism);

      // Outer wrapper: chip row on top, gene set panel below (hidden by default)
      const asmEntry = document.createElement('div');
      asmEntry.style.cssText = 'display:inline-flex; flex-direction:column; vertical-align:top; margin:2px;';

      const chipRow = document.createElement('div');
      chipRow.className = 'd-flex align-items-center gap-1';

      const chip = document.createElement('span');
      chip.className = 'tag-chip-selector';
      chip.dataset.organism = organism;
      chip.dataset.assembly = assembly;
      chip.style.cssText = `font-size:11px; padding:2px 8px; cursor:pointer; background:${c}; border-color:${c}; color:white; border:2px solid ${c};`;
      chip.textContent = assembly;

      // Badge showing gene set selection state (only visible when >1 gs)
      const gsBadge = document.createElement('span');
      gsBadge.style.cssText = 'font-size:10px;';

      // Gene set chips panel (expands when customize is clicked)
      const gsPanel = document.createElement('div');
      gsPanel.style.cssText = 'display:none; padding-top:3px;';

      function getCurrentGs() { return selectedAccess[organism]?.[assembly]; }

      function refreshAsmChip() {
        const gs    = getCurrentGs();
        const isSel = gs !== undefined;
        chip.style.opacity = isSel ? '1' : '0.35';
        chip.classList.toggle('selected', isSel);

        if (!isSel) {
          gsBadge.textContent = ''; gsBadge.className = '';
        } else if (gs[0] === '*') {
          gsBadge.textContent = 'all';
          gsBadge.className   = 'badge bg-success ms-1';
        } else {
          gsBadge.textContent = `${gs.length}/${geneSets.length}`;
          gsBadge.className   = 'badge bg-warning text-dark ms-1';
        }

        // Keep gs chip visuals in sync if panel is open
        if (gsPanel.style.display !== 'none') {
          gsPanel.querySelectorAll('.tag-chip-gs').forEach(gsChip => {
            const gsName = gsChip.dataset.gs;
            const sel    = gs === undefined ? false : (gs[0] === '*' ? true : gs.includes(gsName));
            gsChip.style.opacity    = sel ? '1' : '0.45';
            gsChip.style.background = sel ? c : 'white';
            gsChip.style.color      = sel ? 'white' : c;
            gsChip.classList.toggle('selected', sel);
          });
        }
      }

      if (asmRefreshRegistry) asmRefreshRegistry[`${organism}::${assembly}`] = refreshAsmChip;

      // Assembly chip click → toggle all gene sets (or upgrade partial → all)
      chip.addEventListener('click', function () {
        const gs = getCurrentGs();
        if (gs === undefined) {
          if (!selectedAccess[organism]) selectedAccess[organism] = {};
          selectedAccess[organism][assembly] = ['*'];
        } else if (gs[0] === '*') {
          // Already all — deselect
          delete selectedAccess[organism][assembly];
          if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
        } else {
          // Partial → upgrade to all
          selectedAccess[organism][assembly] = ['*'];
        }
        refreshAsmChip();
        refreshOrg();
        onGroupRefresh();
        onUpdate();
      });

      chipRow.appendChild(chip);
      chipRow.appendChild(gsBadge);

      // Customize button + individual gene set chips (only when assembly has >1 gene set)
      if (hasGeneSets) {
        const customBtn = document.createElement('button');
        customBtn.type  = 'button';
        customBtn.title = 'Customize gene sets';
        customBtn.style.cssText = 'background:none; border:none; padding:0 2px; cursor:pointer; font-size:11px; color:#6c757d; line-height:1;';
        customBtn.innerHTML = '<i class="fa fa-sliders-h"></i>';

        customBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          const isOpen = gsPanel.style.display !== 'none';
          gsPanel.style.display = isOpen ? 'none' : 'block';
          this.innerHTML = isOpen
            ? '<i class="fa fa-sliders-h"></i>'
            : '<i class="fa fa-times-circle" style="color:#dc3545;"></i>';
          if (!isOpen) refreshAsmChip(); // sync gs chip state when opening
        });

        chipRow.appendChild(customBtn);

        geneSets.forEach(gs => {
          const gsChip = document.createElement('span');
          gsChip.className     = 'tag-chip-gs me-1';
          gsChip.dataset.gs    = gs;
          gsChip.style.borderColor = c;

          const initGs  = getCurrentGs();
          const initSel = initGs === undefined ? false : (initGs[0] === '*' ? true : initGs.includes(gs));
          gsChip.style.background = initSel ? c : 'white';
          gsChip.style.color      = initSel ? 'white' : c;
          gsChip.style.opacity    = initSel ? '1' : '0.45';
          gsChip.classList.toggle('selected', initSel);
          gsChip.textContent = gs === 'blast_only' ? 'genome only (BLAST)' : gs;

          gsChip.addEventListener('click', function (e) {
            e.stopPropagation();
            let current = getCurrentGs();

            if (current === undefined) {
              // Assembly not yet selected — select just this gs
              if (!selectedAccess[organism]) selectedAccess[organism] = {};
              selectedAccess[organism][assembly] = [gs];
            } else if (current[0] === '*') {
              // All selected — remove this one
              const remaining = geneSets.filter(g => g !== gs);
              if (remaining.length === 0) {
                delete selectedAccess[organism][assembly];
                if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
              } else {
                selectedAccess[organism][assembly] = remaining;
              }
            } else {
              // Specific list — toggle
              if (current.includes(gs)) {
                const remaining = current.filter(g => g !== gs);
                if (remaining.length === 0) {
                  delete selectedAccess[organism][assembly];
                  if (!Object.keys(selectedAccess[organism]).length) delete selectedAccess[organism];
                } else {
                  selectedAccess[organism][assembly] = remaining;
                }
              } else {
                const newList = [...current, gs];
                // Upgrade to ['*'] if all gene sets are now selected
                selectedAccess[organism][assembly] = newList.length === geneSets.length ? ['*'] : newList;
              }
            }

            refreshAsmChip();
            refreshOrg();
            onGroupRefresh();
            onUpdate();
          });

          gsPanel.appendChild(gsChip);
        });
      }

      asmEntry.appendChild(chipRow);
      if (hasGeneSets) asmEntry.appendChild(gsPanel);
      assemblyWrap.appendChild(asmEntry);
      refreshAsmChip();
    });

    orgCb.addEventListener('click', function (e) {
      e.stopPropagation();
      if (this.checked) {
        selectedAccess[organism] = {};
        assemblies.forEach(a => { selectedAccess[organism][a] = ['*']; });
      } else {
        delete selectedAccess[organism];
      }
      if (this.checked && assemblyWrap.style.display === 'none') {
        assemblyWrap.style.display = 'block';
        orgChevron.className = 'fa fa-chevron-down fa-fw text-muted';
      }
      refreshOrg(); // refreshOrg calls asmRefreshRegistry for each asm
      onGroupRefresh();
      onUpdate();
    });

    orgHeader.addEventListener('click', function (e) {
      if (e.target === orgCb) return;
      const hidden = assemblyWrap.style.display === 'none';
      assemblyWrap.style.display = hidden ? 'block' : 'none';
      orgChevron.className = hidden ? 'fa fa-chevron-down fa-fw text-muted' : 'fa fa-chevron-right fa-fw text-muted';
    });

    orgHeader.appendChild(orgCb);
    orgHeader.appendChild(orgChevron);
    orgHeader.appendChild(orgLabel);
    orgHeader.appendChild(orgCount);
    orgSection.appendChild(orgHeader);
    orgSection.appendChild(assemblyWrap);

    refreshOrg();
    return orgSection;
  }

  // ── Render full selector grouped by group ─────────────────────────────
  function renderAssemblySelector(containerEl, selectedAccess, onUpdate) {
    containerEl.innerHTML = '';
    const asmRefreshRegistry = {}; // 'org::asm' → refreshFn, populated by buildOrgSection

    Object.keys(allOrganismsByGroup).sort().forEach(group => {
      const groupOrgs = allOrganismsByGroup[group];

      const groupSection = document.createElement('div');
      groupSection.className = 'group-section';
      groupSection.dataset.group = group;

      const groupHeader = document.createElement('div');
      groupHeader.className = 'group-header d-flex align-items-center gap-2';

      const groupCb = document.createElement('input');
      groupCb.type = 'checkbox';
      groupCb.className = 'form-check-input flex-shrink-0';
      groupCb.style.cursor = 'pointer';

      const groupChevron = document.createElement('i');
      groupChevron.className = 'fa fa-chevron-right fa-fw';

      const groupLabel = document.createElement('span');
      groupLabel.className = 'fw-bold flex-grow-1';
      groupLabel.textContent = group;

      const groupCount = document.createElement('span');

      const groupBody = document.createElement('div');
      groupBody.className = 'group-body';
      groupBody.style.display = 'none';

      function refreshGroup() {
        let total = 0, sel = 0;
        // groupOrgs is {org: {assembly: [gene_sets]}}
        Object.entries(groupOrgs).forEach(([org, asmsDict]) => {
          Object.keys(asmsDict).forEach(a => {
            total++;
            if (selectedAccess[org]?.[a] !== undefined) sel++;
          });
        });
        groupCount.textContent = `${sel}/${total}`;
        groupCount.className = sel === 0     ? 'badge bg-secondary ms-1'
                             : sel === total ? 'badge bg-success ms-1'
                             :                 'badge bg-warning text-dark ms-1';
        groupCb.checked       = sel === total && total > 0;
        groupCb.indeterminate = sel > 0 && sel < total;
      }

      groupCb.addEventListener('click', function (e) {
        e.stopPropagation();
        const selectAll = this.checked;
        Object.entries(groupOrgs).forEach(([org, asmsDict]) => {
          if (selectAll) {
            if (!selectedAccess[org]) selectedAccess[org] = {};
            Object.keys(asmsDict).forEach(a => { selectedAccess[org][a] = ['*']; });
          } else {
            if (selectedAccess[org]) {
              Object.keys(asmsDict).forEach(a => { delete selectedAccess[org][a]; });
              if (!Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
            }
          }
        });
        if (selectAll && groupBody.style.display === 'none') {
          groupBody.style.display = 'block';
          groupChevron.className = 'fa fa-chevron-down fa-fw';
        }
        // Update org rows via registry (handles gene set badges too)
        groupBody.querySelectorAll('.org-section').forEach(orgSection => {
          const org      = orgSection.dataset.org;
          const asmsDict = groupOrgs[org] || {};
          const asmKeys  = Object.keys(asmsDict);
          const orgCbEl  = orgSection.querySelector('input[type=checkbox]');
          const orgCntEl = orgSection.querySelector('.badge');
          const sel   = asmKeys.filter(a => selectedAccess[org]?.[a] !== undefined).length;
          const total = asmKeys.length;
          if (orgCntEl) {
            orgCntEl.textContent = `${sel}/${total}`;
            orgCntEl.className = sel === 0     ? 'badge bg-secondary'
                               : sel === total ? 'badge bg-success'
                               :                 'badge bg-warning text-dark';
          }
          if (orgCbEl) { orgCbEl.checked = sel === total && total > 0; orgCbEl.indeterminate = sel > 0 && sel < total; }
          asmKeys.forEach(a => asmRefreshRegistry[`${org}::${a}`]?.());
        });
        refreshGroup();
        onUpdate();
      });

      groupHeader.addEventListener('click', function (e) {
        if (e.target === groupCb) return;
        const hidden = groupBody.style.display === 'none';
        groupBody.style.display = hidden ? 'block' : 'none';
        groupChevron.className = hidden ? 'fa fa-chevron-down fa-fw' : 'fa fa-chevron-right fa-fw';
      });

      Object.keys(groupOrgs).sort().forEach(org => {
        // groupOrgs[org] is {assembly: [gene_sets]}
        groupBody.appendChild(buildOrgSection(org, groupOrgs[org], selectedAccess, refreshGroup, onUpdate, asmRefreshRegistry));
      });

      groupHeader.appendChild(groupCb);
      groupHeader.appendChild(groupChevron);
      groupHeader.appendChild(groupLabel);
      groupHeader.appendChild(groupCount);
      groupSection.appendChild(groupHeader);
      groupSection.appendChild(groupBody);
      containerEl.appendChild(groupSection);

      refreshGroup();
    });
  }

  // ── Sync hidden inputs + preview badges ───────────────────────────────
  function syncSelection(hiddenId, previewId, selectedAccess) {
    const hiddenEl  = document.getElementById(hiddenId);
    const previewEl = document.getElementById(previewId);
    if (!hiddenEl || !previewEl) return;

    hiddenEl.innerHTML  = '';
    previewEl.innerHTML = '';
    let total = 0;

    // selectedAccess: {org: {assembly: [gene_sets]}}
    Object.keys(selectedAccess).sort().forEach(org => {
      Object.keys(selectedAccess[org] || {}).sort().forEach(asm => {
        const geneSets = selectedAccess[org][asm] || ['*'];
        total++;

        // Emit one hidden input per gene_set value
        geneSets.forEach(gs => {
          const inp = document.createElement('input');
          inp.type = 'hidden'; inp.name = `access[${org}][${asm}][]`; inp.value = gs;
          hiddenEl.appendChild(inp);
        });

        // Resolve ['*'] to actual gene set names for display
        const displayGs = (geneSets.length === 1 && geneSets[0] === '*')
          ? (allOrganisms[org]?.[asm] || [])
          : geneSets;

        const row = document.createElement('div');
        row.className = 'd-flex align-items-start gap-1 mb-1';

        const badge = document.createElement('span');
        badge.className = 'tag-chip flex-shrink-0';
        badge.style.background  = orgColor(org);
        badge.style.borderColor = orgColor(org);
        badge.textContent = `${org}: ${asm}`;

        const rm = document.createElement('i');
        rm.className = 'fa fa-times ms-2';
        rm.style.cssText = 'cursor:pointer;opacity:0.8;';
        rm.addEventListener('click', function (e) {
          e.stopPropagation();
          delete selectedAccess[org]?.[asm];
          if (selectedAccess[org] && !Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
          syncSelection(hiddenId, previewId, selectedAccess);
          const containerEl = document.getElementById(
            hiddenId === 'create-selected-assemblies-hidden' ? 'create-access-container' : 'modal-access-container'
          );
          if (containerEl) renderAssemblySelector(containerEl, selectedAccess, () => syncSelection(hiddenId, previewId, selectedAccess));
        });
        badge.appendChild(rm);

        const gsWrap = document.createElement('span');
        gsWrap.className = 'd-flex flex-wrap gap-1 align-items-center';
        displayGs.forEach(gs => {
          const allGs = allOrganisms[org]?.[asm] || [];
          const gsChip = document.createElement('span');
          gsChip.className = 'd-inline-flex align-items-center gap-1 badge rounded-pill';
          gsChip.style.cssText = `background:${orgColor(org)}; opacity:0.82; font-size:10px; font-weight:normal; padding:3px 7px;`;
          const gsLabel = document.createElement('span');
          gsLabel.textContent = gs === 'blast_only' ? 'genome only (BLAST)' : gs;
          const gsRm = document.createElement('i');
          gsRm.className = 'fa fa-times';
          gsRm.style.cssText = 'cursor:pointer; opacity:0.7; font-size:9px;';
          gsRm.title = `Remove ${gs}`;
          gsRm.addEventListener('click', function (e) {
            e.stopPropagation();
            const current = selectedAccess[org]?.[asm];
            const fullList = (current && current.length === 1 && current[0] === '*') ? allGs : (current || allGs);
            const remaining = fullList.filter(g => g !== gs);
            if (remaining.length === 0) {
              delete selectedAccess[org][asm];
              if (!Object.keys(selectedAccess[org]).length) delete selectedAccess[org];
            } else {
              if (!selectedAccess[org]) selectedAccess[org] = {};
              selectedAccess[org][asm] = remaining.length === allGs.length ? ['*'] : remaining;
            }
            syncSelection(hiddenId, previewId, selectedAccess);
            const containerEl = document.getElementById(
              hiddenId === 'create-selected-assemblies-hidden' ? 'create-access-container' : 'modal-access-container'
            );
            if (containerEl) renderAssemblySelector(containerEl, selectedAccess, () => syncSelection(hiddenId, previewId, selectedAccess));
          });
          gsChip.appendChild(gsLabel);
          gsChip.appendChild(gsRm);
          gsWrap.appendChild(gsChip);
        });

        row.appendChild(badge);
        row.appendChild(gsWrap);
        previewEl.appendChild(row);
      });
    });

    if (total === 0) previewEl.innerHTML = '<span class="text-muted small">No assemblies selected</span>';
  }

  // ── Password match ────────────────────────────────────────────────────
  function watchPasswordMatch(pwId, confirmId, msgId) {
    const pw = document.getElementById(pwId), pw2 = document.getElementById(confirmId), msg = document.getElementById(msgId);
    if (!pw || !pw2 || !msg) return;
    function check() {
      if (!pw.value || !pw2.value) { msg.style.display = 'none'; return; }
      if (pw.value === pw2.value) { msg.textContent = '✓ Passwords match'; msg.style.color = '#198754'; msg.style.display = ''; }
      else { msg.textContent = '✗ Passwords do not match'; msg.style.color = '#dc3545'; msg.style.display = ''; }
    }
    pw.addEventListener('input', check);
    pw2.addEventListener('input', check);
  }

  // ── Admin toggle ──────────────────────────────────────────────────────
  function toggleAccess(checkboxId, sectionId, requiredId) {
    const cb = document.getElementById(checkboxId), sec = document.getElementById(sectionId), req = document.getElementById(requiredId);
    if (!cb || !sec) return;
    sec.classList.toggle('access-disabled', cb.checked);
    if (req) req.style.display = cb.checked ? 'none' : '';
  }

  // ── Expand / Collapse All ─────────────────────────────────────────────
  function setupExpandAll(btnId, containerId, stateRef) {
    const btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      stateRef.expanded = !stateRef.expanded;
      const wrap = document.getElementById(containerId);
      if (!wrap) return;
      wrap.querySelectorAll('.group-body, .assembly-wrap').forEach(el => {
        el.style.display = stateRef.expanded ? 'block' : 'none';
      });
      wrap.querySelectorAll('.group-header i.fa, .org-header i.fa').forEach(i => {
        i.className = i.className.replace('chevron-right', 'chevron-down').replace('chevron-down', stateRef.expanded ? 'chevron-down' : 'chevron-right');
      });
      btn.innerHTML = stateRef.expanded ? '<i class="fa fa-minus"></i> Collapse All' : '<i class="fa fa-plus"></i> Expand All';
    });
  }

  // ── Select All / Clear All ────────────────────────────────────────────
  function setupSelectAll(selectBtnId, clearBtnId, getAccess, containerEl, onUpdate) {
    document.getElementById(selectBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      // allOrganisms: {org: {assembly: [gene_sets]}}
      Object.keys(allOrganisms).forEach(org => {
        acc[org] = {};
        Object.keys(allOrganisms[org]).forEach(asm => { acc[org][asm] = ['*']; });
      });
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
    document.getElementById(clearBtnId)?.addEventListener('click', function () {
      const acc = getAccess();
      Object.keys(acc).forEach(k => delete acc[k]);
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
    });
  }

  // ── Filter (groups + organisms) ───────────────────────────────────────
  function setupFilter(filterId, containerId) {
    const inp = document.getElementById(filterId);
    if (!inp) return;
    inp.addEventListener('input', function () {
      const q = this.value.toLowerCase().trim();
      const container = document.getElementById(containerId);
      if (!container) return;
      container.querySelectorAll('.group-section').forEach(groupSection => {
        const groupName = (groupSection.dataset.group || '').toLowerCase();
        const groupMatchesQuery = !q || groupName.includes(q);
        let anyOrgVisible = false;

        groupSection.querySelectorAll('.org-section').forEach(orgSection => {
          const orgName = (orgSection.dataset.org || '').toLowerCase();
          const show = groupMatchesQuery || orgName.includes(q);
          orgSection.style.display = show ? '' : 'none';
          if (show) anyOrgVisible = true;
        });

        groupSection.style.display = anyOrgVisible ? '' : 'none';
        if (anyOrgVisible && q) {
          const body = groupSection.querySelector('.group-body');
          const chev = groupSection.querySelector('.group-header i.fa');
          if (body) body.style.display = 'block';
          if (chev) chev.className = chev.className.replace('chevron-right', 'chevron-down');
        }
      });
    });
  }

  // ── Copy from user ────────────────────────────────────────────────────
  function setupCopyFromUser(selectId, excludeUser, getAccess, containerEl, onUpdate) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    Object.keys(allUsers).sort().forEach(uname => {
      if (uname === excludeUser) return;
      const u = allUsers[uname];
      if (!u.access || !Object.keys(u.access).length) return;
      const opt = document.createElement('option');
      opt.value = uname;
      opt.textContent = uname + (u.email ? ` (${u.email})` : '');
      sel.appendChild(opt);
    });
    sel.addEventListener('change', function () {
      if (!this.value) return;
      const src = allUsers[this.value];
      if (!src?.access) return;
      const acc = getAccess();
      Object.keys(acc).forEach(k => delete acc[k]);
      // Handle both old {org: [asms]} and new {org: {asm: [gene_sets]}} formats
      Object.keys(src.access).forEach(org => {
        const d = src.access[org];
        if (Array.isArray(d)) {
          // Old format — convert
          acc[org] = {};
          d.forEach(asm => { acc[org][asm] = ['*']; });
        } else if (d && typeof d === 'object') {
          acc[org] = { ...d };
        }
      });
      renderAssemblySelector(containerEl, acc, onUpdate);
      onUpdate();
      this.value = '';
    });
  }

  // ── CREATE FORM ───────────────────────────────────────────────────────
  function initCreateForm() {
    const containerEl = document.getElementById('create-access-container');
    if (!containerEl) return;

    function onUpdate() { syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess); }
    renderAssemblySelector(containerEl, createSelectedAccess, onUpdate);

    watchPasswordMatch('create-password', 'create-password-confirm', 'create-pw-match');

    document.getElementById('create-isAdmin')?.addEventListener('change', function () {
      toggleAccess('create-isAdmin', 'create-access-section', 'create-access-required');
      if (this.checked) {
        createSelectedAccess = {};
        syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
        renderAssemblySelector(containerEl, createSelectedAccess, onUpdate);
      }
    });

    setupExpandAll('create-toggle-all-btn', 'create-access-container', { expanded: false });
    setupFilter('create-organism-filter', 'create-access-container');
    setupSelectAll('create-select-all-btn', 'create-clear-all-btn', () => createSelectedAccess, containerEl, onUpdate);
    setupCopyFromUser('create-copy-from-user', null, () => createSelectedAccess, containerEl, onUpdate);

    document.getElementById('createUserForm')?.addEventListener('submit', function (e) {
      const pw = document.getElementById('create-password').value;
      const pw2 = document.getElementById('create-password-confirm').value;
      const adm = document.getElementById('create-isAdmin').checked;
      const hasAsm = Object.keys(createSelectedAccess).some(org => Object.keys(createSelectedAccess[org] || {}).length > 0);
      if (!pw) { e.preventDefault(); alert('Password is required for new users.'); document.getElementById('create-password').focus(); return; }
      if (pw !== pw2) { e.preventDefault(); alert('Passwords do not match.'); document.getElementById('create-password-confirm').focus(); return; }
      if (!adm && !hasAsm) { e.preventDefault(); alert('Select at least one assembly, or check Admin for full access.'); }
    });
  }

  // ── EDIT MODAL ────────────────────────────────────────────────────────
  function initEditModal() {
    const containerEl = document.getElementById('modal-access-container');
    if (!containerEl) return;

    let currentEditUser = null;

    function onUpdate() { syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess); }

    watchPasswordMatch('modal-password', 'modal-password-confirm', 'modal-pw-match');

    document.getElementById('modal-isAdmin')?.addEventListener('change', function () {
      toggleAccess('modal-isAdmin', 'modal-access-section', 'modal-access-required');
      if (this.checked) {
        editSelectedAccess = {};
        syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);
        renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
      }
    });

    setupExpandAll('modal-toggle-all-btn', 'modal-access-container', { expanded: false });
    setupFilter('modal-organism-filter', 'modal-access-container');
    setupSelectAll('modal-select-all-btn', 'modal-clear-all-btn', () => editSelectedAccess, containerEl, onUpdate);

    // Copy-from-user is re-populated each time the modal opens to exclude the current user
    const copyFromSel = document.getElementById('modal-copy-from-user');

    document.addEventListener('click', function (event) {
      const btn = event.target.closest('.edit-user-btn');
      if (!btn) return;
      const username = btn.getAttribute('data-username');
      if (!username) return;
      const userData = allUsers[username];
      if (!userData) return;

      currentEditUser = username;

      document.getElementById('modal-username-display').textContent = username;
      document.getElementById('modal-original-username').value = username;
      document.getElementById('modal-username').value      = username;
      document.getElementById('modal-email').value         = userData.email        || '';
      document.getElementById('modal-first-name').value   = userData.first_name   || '';
      document.getElementById('modal-last-name').value    = userData.last_name    || '';
      document.getElementById('modal-account-host').value = userData.account_host || '';
      document.getElementById('modal-password').value         = '';
      document.getElementById('modal-password-confirm').value = '';
      document.getElementById('modal-pw-match').style.display = 'none';

      const isAdmin = (userData.role === 'admin');
      document.getElementById('modal-isAdmin').checked = isAdmin;
      toggleAccess('modal-isAdmin', 'modal-access-section', 'modal-access-required');

      editSelectedAccess = {};
      if (userData.access && typeof userData.access === 'object') {
        Object.keys(userData.access).forEach(org => {
          const d = userData.access[org];
          if (Array.isArray(d)) {
            // Old format — convert to new
            editSelectedAccess[org] = {};
            d.forEach(asm => { editSelectedAccess[org][asm] = ['*']; });
          } else if (d && typeof d === 'object') {
            editSelectedAccess[org] = { ...d };
          }
        });
      }

      renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
      syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);

      // Rebuild copy-from-user options excluding this user
      if (copyFromSel) {
        copyFromSel.innerHTML = '<option value="">— select user —</option>';
        Object.keys(allUsers).sort().forEach(uname => {
          if (uname === username) return;
          const u = allUsers[uname];
          if (!u.access || !Object.keys(u.access).length) return;
          const opt = document.createElement('option');
          opt.value = uname;
          opt.textContent = uname + (u.email ? ` (${u.email})` : '');
          copyFromSel.appendChild(opt);
        });
      }

      // Stale assemblies
      const staleAlert = document.getElementById('modal-stale-alert');
      const staleItems = document.getElementById('modal-stale-items');
      staleItems.innerHTML = '';
      const staleList = [];
      Object.keys(userData.access || {}).forEach(org => {
        const d = userData.access[org];
        const asmKeys = Array.isArray(d) ? d : Object.keys(d || {});
        asmKeys.forEach(asm => {
          if (!Object.prototype.hasOwnProperty.call(allOrganisms[org] || {}, asm)) staleList.push({ org, asm });
        });
      });
      if (staleList.length > 0) {
        staleAlert.classList.remove('d-none');
        staleList.forEach(({ org, asm }) => {
          const chip = document.createElement('span');
          chip.className = 'tag-chip tag-chip-stale me-1';
          chip.textContent = `${org}: ${asm}`;
          const rm = document.createElement('i');
          rm.className = 'fa fa-times ms-1'; rm.style.cursor = 'pointer';
          rm.addEventListener('click', function () {
            delete editSelectedAccess[org]?.[asm];
            if (editSelectedAccess[org] && !Object.keys(editSelectedAccess[org]).length) delete editSelectedAccess[org];
            chip.remove();
            if (!staleItems.children.length) staleAlert.classList.add('d-none');
            renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
            syncSelection('modal-selected-assemblies-hidden', 'modal-selected-preview', editSelectedAccess);
          });
          chip.appendChild(rm);
          staleItems.appendChild(chip);
        });
      } else {
        staleAlert.classList.add('d-none');
      }
    });

    if (copyFromSel) {
      copyFromSel.addEventListener('change', function () {
        if (!this.value) return;
        const src = allUsers[this.value];
        if (!src?.access) return;
        Object.keys(editSelectedAccess).forEach(k => delete editSelectedAccess[k]);
        Object.keys(src.access).forEach(org => {
          const d = src.access[org];
          if (Array.isArray(d)) {
            editSelectedAccess[org] = {};
            d.forEach(asm => { editSelectedAccess[org][asm] = ['*']; });
          } else if (d && typeof d === 'object') {
            editSelectedAccess[org] = { ...d };
          }
        });
        renderAssemblySelector(containerEl, editSelectedAccess, onUpdate);
        onUpdate();
        this.value = '';
      });
    }

    document.getElementById('editUserForm')?.addEventListener('submit', function (e) {
      const pw = document.getElementById('modal-password').value;
      const pw2 = document.getElementById('modal-password-confirm').value;
      const adm = document.getElementById('modal-isAdmin').checked;
      const hasAsm = Object.keys(editSelectedAccess).some(org => Object.keys(editSelectedAccess[org] || {}).length > 0);
      if (pw && pw !== pw2) { e.preventDefault(); alert('Passwords do not match.'); document.getElementById('modal-password-confirm').focus(); return; }
      if (!adm && !hasAsm) { e.preventDefault(); alert('Select at least one assembly, or check Admin for full access.'); }
    });
  }

  // ── DELETE ────────────────────────────────────────────────────────────
  function initDeleteButtons() {
    document.querySelectorAll('.delete-user-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        const username = this.getAttribute('data-username');
        if (!confirm(`Permanently delete user "${username}"? This cannot be undone.`)) return;
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<input type="hidden" name="csrf_token" value="${token}"><input type="hidden" name="delete_user" value="1"><input type="hidden" name="username" value="${username}">`;
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  // ── RESET CREATE FORM ─────────────────────────────────────────────────
  window.resetCreateForm = function () {
    createSelectedAccess = {};
    const containerEl = document.getElementById('create-access-container');
    if (containerEl) renderAssemblySelector(containerEl, createSelectedAccess, () => syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess));
    syncSelection('create-selected-assemblies-hidden', 'create-selected-preview', createSelectedAccess);
    document.getElementById('create-pw-match').style.display = 'none';
    document.getElementById('create-access-section')?.classList.remove('access-disabled');
    const req = document.getElementById('create-access-required');
    if (req) req.style.display = '';
  };

  // ── STALE AUDIT TOGGLE ────────────────────────────────────────────────
  function initStaleAudit() {
    const header = document.getElementById('stale-audit-header');
    const panel  = document.getElementById('stale-audit');
    if (!header || !panel) return;
    function setOpen(open) {
      panel.style.display = open ? 'block' : 'none';
      const ic = header.querySelector('.fa-chevron-down, .fa-chevron-up');
      if (ic) ic.className = open ? 'fa fa-chevron-up float-end mt-1' : 'fa fa-chevron-down float-end mt-1';
    }
    header.addEventListener('click', function () { setOpen(panel.style.display === 'none' || !panel.style.display); });
    // "N stale" (a user's row) and the notice at the top: open the card on the way down to it.
    document.addEventListener('click', function (e) {
      if (e.target.closest('.js-open-stale')) setOpen(true);   // the link's own #anchor does the scrolling
    });
  }

  // ── DataTable ─────────────────────────────────────────────────────────
  function initDataTable() {
    if (typeof $ !== 'undefined' && $.fn?.DataTable) {
      // Paging and "Show N entries" only when there is more than one page; the search box always.
      const many = document.querySelectorAll('#usersTable tbody tr').length > 25;
      $('#usersTable').DataTable({
        pageLength: 25, paging: many, lengthChange: many, info: many,
        order: [[0, 'asc']], columnDefs: [{ targets: -1, orderable: false }],
        language: { search: '', searchPlaceholder: 'Search users' }
      });
    }
  }

  // ── Boot ──────────────────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    Object.keys(allOrganisms).forEach(org => orgColor(org));
    initCreateForm();
    initEditModal();
    initDeleteButtons();
    initStaleAudit();
    initDataTable();
    // The create form's Reset button (was an inline onclick).
    const resetBtn = document.getElementById('resetCreateBtn');
    if (resetBtn) resetBtn.addEventListener('click', function () { window.resetCreateForm(); });
  });

  // A form with data-confirm asks first (the stale-reference removals; were inline onsubmit handlers).
  document.addEventListener('submit', function (e) {
    const form = e.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
  });

})();

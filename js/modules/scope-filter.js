/**
 * Scope Filter Modal
 * Hierarchical organism → assembly → gene set checkbox tree for narrowing search scope.
 *
 * Usage:
 *   const filter = new ScopeFilter({
 *     sitePath: '/moop',
 *     organisms: ['Org_name', ...],
 *     selectedScope: { Org_name: { 'GCA_xxx': { v1: true, v2: false } } } | null,
 *     onApply: (selectedScope) => { ... }
 *   });
 *   filter.show();
 *
 * selectedScope format:
 *   { [organism]: { [assembly_accession]: { [gene_set_name]: boolean } } }
 *   null means "all selected" (no filter).
 */

class ScopeFilter {
    constructor(config) {
        this.sitePath      = config.sitePath || window.sitePath;
        this.organisms     = config.organisms || [];
        this.selectedScope = config.selectedScope ? JSON.parse(JSON.stringify(config.selectedScope)) : null;
        this.hierarchy     = null;
        this.onApply       = config.onApply || (() => {});
    }

    show() {
        if (this.hierarchy) {
            this.initSelectedScope();
            this.showModal();
        } else {
            this.fetchHierarchy();
        }
    }

    fetchHierarchy() {
        $.ajax({
            url: this.sitePath + '/tools/get_organism_hierarchy.php',
            data: { organisms: this.organisms.join(',') },
            method: 'GET',
            dataType: 'json',
            success: (data) => {
                this.hierarchy = data;
                this.initSelectedScope();
                this.showModal();
            },
            error: () => {
                alert('Error loading organism scope data. Please try again.');
            }
        });
    }

    // Initialise selectedScope from hierarchy if not already set (or if new orgs appeared).
    initSelectedScope() {
        if (!this.selectedScope) {
            this.selectedScope = {};
        }
        for (const orgEntry of this.hierarchy) {
            const org = orgEntry.organism;
            if (!this.selectedScope[org]) {
                this.selectedScope[org] = {};
            }
            for (const asm of orgEntry.assemblies) {
                if (!this.selectedScope[org][asm.accession]) {
                    this.selectedScope[org][asm.accession] = {};
                }
                for (const gs of asm.gene_sets) {
                    if (!(gs in this.selectedScope[org][asm.accession])) {
                        this.selectedScope[org][asm.accession][gs] = true;
                    }
                }
            }
        }
    }

    // ── State helpers ──────────────────────────────────────────────────────────

    getAsmState(org, accession) {
        const gsMap = this.selectedScope[org]?.[accession] || {};
        const vals = Object.values(gsMap);
        if (vals.length === 0) return 'all';
        const trueCount = vals.filter(Boolean).length;
        if (trueCount === vals.length) return 'all';
        if (trueCount === 0) return 'none';
        return 'mixed';
    }

    getOrgState(org) {
        const orgMap = this.selectedScope[org] || {};
        let total = 0, trueCount = 0;
        for (const accession in orgMap) {
            for (const gs in orgMap[accession]) {
                total++;
                if (orgMap[accession][gs]) trueCount++;
            }
        }
        if (total === 0) return 'all';
        if (trueCount === total) return 'all';
        if (trueCount === 0) return 'none';
        return 'mixed';
    }

    // ── DOM helpers ────────────────────────────────────────────────────────────

    sanitizeId(str) {
        return str.replace(/[^a-zA-Z0-9-_]/g, '_');
    }

    updateAsmCheckboxDom(org, accession) {
        const id  = 'scope-asm-' + this.sanitizeId(org + '_' + accession);
        const $cb = $('#' + id);
        if (!$cb.length) return;
        const state = this.getAsmState(org, accession);
        if (state === 'all') {
            $cb.prop('checked', true).prop('indeterminate', false);
        } else if (state === 'none') {
            $cb.prop('checked', false).prop('indeterminate', false);
        } else {
            $cb.prop('checked', false).prop('indeterminate', true);
        }
    }

    updateOrgCheckboxDom(org) {
        const id  = 'scope-org-' + this.sanitizeId(org);
        const $cb = $('#' + id);
        if (!$cb.length) return;
        const state = this.getOrgState(org);
        if (state === 'all') {
            $cb.prop('checked', true).prop('indeterminate', false);
        } else if (state === 'none') {
            $cb.prop('checked', false).prop('indeterminate', false);
        } else {
            $cb.prop('checked', false).prop('indeterminate', true);
        }
    }

    refreshAllIndeterminate() {
        if (!this.hierarchy) return;
        for (const orgEntry of this.hierarchy) {
            const org = orgEntry.organism;
            for (const asm of orgEntry.assemblies) {
                this.updateAsmCheckboxDom(org, asm.accession);
            }
            this.updateOrgCheckboxDom(org);
        }
    }

    // ── Build HTML ─────────────────────────────────────────────────────────────

    buildModalHtml() {
        let treeHtml = '';

        for (const orgEntry of this.hierarchy) {
            const org        = orgEntry.organism;
            const orgDisplay = org.replace(/_/g, ' ');
            const orgId      = this.sanitizeId(org);
            const orgState   = this.getOrgState(org);
            const orgChecked = orgState === 'all' ? 'checked' : '';

            let asmHtml = '';
            for (const asm of orgEntry.assemblies) {
                const { accession, name: asmName, gene_sets } = asm;
                const nameLabel = asmName !== accession
                    ? `${escapeHtml(asmName)} <span class="text-muted small">(${escapeHtml(accession)})</span>`
                    : escapeHtml(accession);
                const asmId      = this.sanitizeId(org + '_' + accession);
                const asmState   = this.getAsmState(org, accession);
                const asmChecked = asmState === 'all' ? 'checked' : '';

                let gsHtml = '';
                for (const gs of gene_sets) {
                    const gsId      = this.sanitizeId(org + '_' + accession + '_' + gs);
                    const gsChecked = this.selectedScope[org]?.[accession]?.[gs] ? 'checked' : '';
                    gsHtml += `
                        <div class="form-check scope-gs-row ms-4 mt-1">
                            <input class="form-check-input scope-gs-checkbox" type="checkbox"
                                   id="scope-gs-${gsId}"
                                   data-org="${escapeHtml(org)}"
                                   data-accession="${escapeHtml(accession)}"
                                   data-geneset="${escapeHtml(gs)}"
                                   ${gsChecked}>
                            <label class="form-check-label small" for="scope-gs-${gsId}">
                                <span class="badge bg-secondary me-1">gene set</span>${escapeHtml(gs)}
                            </label>
                        </div>`;
                }

                asmHtml += `
                    <div class="scope-asm-group ms-3 mt-1"
                         data-org="${escapeHtml(org)}"
                         data-accession="${escapeHtml(accession)}">
                        <div class="form-check">
                            <input class="form-check-input scope-asm-checkbox" type="checkbox"
                                   id="scope-asm-${asmId}"
                                   data-org="${escapeHtml(org)}"
                                   data-accession="${escapeHtml(accession)}"
                                   ${asmChecked}>
                            <label class="form-check-label" for="scope-asm-${asmId}">
                                ${nameLabel}
                            </label>
                        </div>
                        ${gsHtml}
                    </div>`;
            }

            treeHtml += `
                <div class="scope-org-group mb-3" data-org="${escapeHtml(org)}">
                    <div class="form-check">
                        <input class="form-check-input scope-org-checkbox" type="checkbox"
                               id="scope-org-${orgId}"
                               data-org="${escapeHtml(org)}"
                               ${orgChecked}>
                        <label class="form-check-label fw-semibold" for="scope-org-${orgId}">
                            <em>${escapeHtml(orgDisplay)}</em>
                        </label>
                    </div>
                    ${asmHtml}
                </div>`;
        }

        return `
            <div class="modal fade" id="scopeFilterModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fa fa-sitemap me-2"></i>Scope Filter
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small mb-3">
                                Select which organisms, assemblies, and gene sets to include in your
                                search. Unchecking a parent deselects all of its children.
                            </p>
                            <div class="mb-3 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-primary scope-select-all-btn">
                                    Select All
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary scope-deselect-all-btn">
                                    Deselect All
                                </button>
                            </div>
                            <hr class="my-2">
                            <div class="scope-tree-container">
                                ${treeHtml}
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                Cancel
                            </button>
                            <button type="button" class="btn btn-primary scope-apply-btn">
                                Apply
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;
    }

    // ── Modal lifecycle ────────────────────────────────────────────────────────

    showModal() {
        $('#scopeFilterModal').remove();
        $('body').append(this.buildModalHtml());
        this.attachEventHandlers();
        this.refreshAllIndeterminate();

        const el = document.getElementById('scopeFilterModal');
        const modal = new bootstrap.Modal(el, { backdrop: 'static', keyboard: true });
        modal.show();
    }

    attachEventHandlers() {
        $(document)
            .off('click.scopeFilter', '.scope-select-all-btn')
            .on('click.scopeFilter', '.scope-select-all-btn', () => this.setAll(true));

        $(document)
            .off('click.scopeFilter', '.scope-deselect-all-btn')
            .on('click.scopeFilter', '.scope-deselect-all-btn', () => this.setAll(false));

        $(document)
            .off('click.scopeFilter', '.scope-apply-btn')
            .on('click.scopeFilter', '.scope-apply-btn', () => this.apply());

        // Gene-set checkbox
        $(document)
            .off('change.scopeFilter', '.scope-gs-checkbox')
            .on('change.scopeFilter', '.scope-gs-checkbox', (e) => {
                const $el      = $(e.target);
                const org      = $el.data('org');
                const accession = $el.data('accession');
                const gs       = $el.data('geneset');
                this.selectedScope[org][accession][gs] = e.target.checked;
                this.updateAsmCheckboxDom(org, accession);
                this.updateOrgCheckboxDom(org);
            });

        // Assembly checkbox
        $(document)
            .off('change.scopeFilter', '.scope-asm-checkbox')
            .on('change.scopeFilter', '.scope-asm-checkbox', (e) => {
                const $el       = $(e.target);
                const org       = $el.data('org');
                const accession = $el.data('accession');
                const checked   = e.target.checked;
                for (const gs in this.selectedScope[org][accession]) {
                    this.selectedScope[org][accession][gs] = checked;
                }
                $(`.scope-gs-checkbox[data-org="${org}"][data-accession="${accession}"]`)
                    .prop('checked', checked);
                this.updateOrgCheckboxDom(org);
            });

        // Organism checkbox
        $(document)
            .off('change.scopeFilter', '.scope-org-checkbox')
            .on('change.scopeFilter', '.scope-org-checkbox', (e) => {
                const org     = $(e.target).data('org');
                const checked = e.target.checked;
                for (const accession in this.selectedScope[org]) {
                    for (const gs in this.selectedScope[org][accession]) {
                        this.selectedScope[org][accession][gs] = checked;
                    }
                }
                $(`.scope-asm-checkbox[data-org="${org}"]`)
                    .prop('checked', checked).prop('indeterminate', false);
                $(`.scope-gs-checkbox[data-org="${org}"]`)
                    .prop('checked', checked);
            });
    }

    setAll(checked) {
        for (const org in this.selectedScope) {
            for (const accession in this.selectedScope[org]) {
                for (const gs in this.selectedScope[org][accession]) {
                    this.selectedScope[org][accession][gs] = checked;
                }
            }
        }
        $('.scope-gs-checkbox, .scope-asm-checkbox, .scope-org-checkbox')
            .prop('checked', checked).prop('indeterminate', false);
    }

    apply() {
        const el = document.getElementById('scopeFilterModal');
        const modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
        this.onApply(JSON.parse(JSON.stringify(this.selectedScope)));
    }
}

window.ScopeFilter = ScopeFilter;

/**
 * Downloads Tool
 * Handles cascading checkboxes, organism filter, expand/collapse, and batch download.
 */

$(document).ready(function () {

    // ── Cascading checkboxes ──────────────────────────────────────────────────

    // Org checkbox → all assemblies + files beneath it
    $(document).on('change', '.org-checkbox', function () {
        const orgId  = $(this).data('org-id');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-org-id="' + orgId + '"].asm-checkbox').prop({ checked, indeterminate: false });
        $('[data-org-id="' + orgId + '"].file-checkbox').prop('checked', checked);
        updateSelectedCount();
    });

    // Assembly checkbox → all files beneath; then sync org state
    $(document).on('change', '.asm-checkbox', function () {
        const asmId  = $(this).data('asm-id');
        const orgId  = $(this).data('org-id');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-asm-id="' + asmId + '"].file-checkbox').prop('checked', checked);
        syncAsmCheckbox(asmId);
        syncOrgCheckbox(orgId);
        updateSelectedCount();
    });

    // Individual file → sync assembly then org
    $(document).on('change', '.file-checkbox', function () {
        const asmId = $(this).data('asm-id');
        const orgId = $(this).data('org-id');
        syncAsmCheckbox(asmId);
        syncOrgCheckbox(orgId);
        updateSelectedCount();
    });

    function syncAsmCheckbox(asmId) {
        const files   = $('[data-asm-id="' + asmId + '"].file-checkbox');
        const total   = files.length;
        const checked = files.filter(':checked').length;
        const cb = document.getElementById('cb-' + asmId);
        if (!cb) return;
        cb.checked       = (total > 0 && checked === total);
        cb.indeterminate = (checked > 0 && checked < total);
    }

    function syncOrgCheckbox(orgId) {
        const files   = $('[data-org-id="' + orgId + '"].file-checkbox');
        const total   = files.length;
        const checked = files.filter(':checked').length;
        const cb = document.getElementById('cb-' + orgId);
        if (!cb) return;
        cb.checked       = (total > 0 && checked === total);
        cb.indeterminate = (checked > 0 && checked < total);
    }

    function updateSelectedCount() {
        const count = $('.file-checkbox:checked').length;
        $('#selected-count').text(count);
        $('#download-selected-btn').prop('disabled', count === 0);
    }

    // ── Expand / Collapse All ────────────────────────────────────────────────

    $('#expand-all-btn').on('click', function () {
        $('#download-tree .collapse').addClass('show');
        updateAllToggleIcons();
    });

    $('#collapse-all-btn').on('click', function () {
        $('#download-tree .collapse').removeClass('show');
        updateAllToggleIcons();
    });

    // Keep chevron icons in sync with Bootstrap collapse state
    $(document).on('shown.bs.collapse hidden.bs.collapse', '#download-tree .collapse', function () {
        const isOpen  = $(this).hasClass('show');
        const trigger = $('[data-bs-target="#' + this.id + '"]');
        trigger.find('.toggle-icon')
            .toggleClass('fa-chevron-down', isOpen)
            .toggleClass('fa-chevron-right', !isOpen);
    });

    function updateAllToggleIcons() {
        $('#download-tree .collapse').each(function () {
            const isOpen  = $(this).hasClass('show');
            const trigger = $('[data-bs-target="#' + this.id + '"]');
            trigger.find('.toggle-icon')
                .toggleClass('fa-chevron-down', isOpen)
                .toggleClass('fa-chevron-right', !isOpen);
        });
    }

    // ── Select All / Deselect All ────────────────────────────────────────────

    $('#select-all-btn').on('click', function () {
        // Only affect visible (non-filtered) organisms
        $('#download-tree .organism-block:visible .file-checkbox').prop('checked', true);
        $('#download-tree .organism-block:visible .asm-checkbox').prop({ checked: true, indeterminate: false });
        $('#download-tree .organism-block:visible .org-checkbox').prop({ checked: true, indeterminate: false });
        updateSelectedCount();
    });

    $('#deselect-all-btn').on('click', function () {
        $('.file-checkbox, .asm-checkbox, .org-checkbox').prop({ checked: false, indeterminate: false });
        updateSelectedCount();
    });

    // ── Organism filter ──────────────────────────────────────────────────────

    $('#organism-filter').on('input', function () {
        const query = $(this).val().toLowerCase().trim();
        $('.organism-block').each(function () {
            const name = String($(this).data('organism-name') || '');
            $(this).toggle(query === '' || name.includes(query));
        });
    });

    // ── Download Selected ────────────────────────────────────────────────────

    $('#download-selected-btn').on('click', function () {
        const urls = [];
        $('.file-checkbox:checked').each(function () {
            const url = $(this).data('download-url');
            if (url) urls.push(url);
        });

        if (urls.length === 0) return;

        // Trigger downloads sequentially; small delay avoids browser blocking
        urls.forEach(function (url, i) {
            setTimeout(function () {
                const a = document.createElement('a');
                a.href     = url;
                a.download = '';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            }, i * 400);
        });
    });

    // ── Init ─────────────────────────────────────────────────────────────────

    updateSelectedCount();
});

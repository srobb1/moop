/**
 * Downloads Tool
 * Handles cascading checkboxes, organism filter, expand/collapse, and batch download.
 */

$(document).ready(function () {

    // ── Cascading checkboxes ──────────────────────────────────────────────────

    // Org checkbox → all assemblies + gene sets + files beneath it; auto-expand when checking
    $(document).on('change', '.org-checkbox', function () {
        const orgId  = $(this).data('org-id');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-org-id="' + orgId + '"].asm-checkbox').prop({ checked, indeterminate: false });
        $('[data-org-id="' + orgId + '"].gs-checkbox').prop({ checked, indeterminate: false });
        $('[data-org-id="' + orgId + '"].file-checkbox').prop('checked', checked);
        if (checked) {
            $('#' + orgId).collapse('show');
            $('#' + orgId + ' .collapse').collapse('show');
        }
        updateSelectedCount();
    });

    // Assembly checkbox → all gene sets + files beneath it
    $(document).on('change', '.asm-checkbox', function () {
        const asmId  = $(this).data('asm-id');
        const orgId  = $(this).data('org-id');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-asm-id="' + asmId + '"].gs-checkbox').prop({ checked, indeterminate: false });
        $('[data-asm-id="' + asmId + '"].file-checkbox').prop('checked', checked);
        syncAsmCheckbox(asmId);
        syncOrgCheckbox(orgId);
        updateSelectedCount();
    });

    // Gene-set checkbox → all files beneath; then sync asm + org state
    $(document).on('change', '.gs-checkbox', function () {
        const gsId   = $(this).data('gs-id');
        const asmId  = $(this).data('asm-id');
        const orgId  = $(this).data('org-id');
        const checked = this.checked;
        this.indeterminate = false;
        $('[data-gs-id="' + gsId + '"].file-checkbox').prop('checked', checked);
        syncGsCheckbox(gsId);
        syncAsmCheckbox(asmId);
        syncOrgCheckbox(orgId);
        updateSelectedCount();
    });

    // Individual file → sync gene set, assembly, then org
    $(document).on('change', '.file-checkbox', function () {
        const gsId  = $(this).data('gs-id');
        const asmId = $(this).data('asm-id');
        const orgId = $(this).data('org-id');
        if (gsId) syncGsCheckbox(gsId);
        syncAsmCheckbox(asmId);
        syncOrgCheckbox(orgId);
        updateSelectedCount();
    });

    function syncGsCheckbox(gsId) {
        const files   = $('[data-gs-id="' + gsId + '"].file-checkbox');
        const total   = files.length;
        const checked = files.filter(':checked').length;
        const cb = document.getElementById('cb-' + gsId);
        if (!cb) return;
        cb.checked       = (total > 0 && checked === total);
        cb.indeterminate = (checked > 0 && checked < total);
    }

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

    function formatBytes(bytes) {
        if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
        if (bytes >= 1048576)    return (bytes / 1048576).toFixed(1)    + ' MB';
        if (bytes >= 1024)       return (bytes / 1024).toFixed(1)       + ' KB';
        return bytes + ' B';
    }

    function updateSelectedCount() {
        const checked = $('.file-checkbox:checked');
        const count   = checked.length;
        let totalBytes = 0;
        checked.each(function () {
            totalBytes += parseInt($(this).data('size') || 0, 10);
        });

        $('#selected-count').text(count);

        if (count > 0 && totalBytes > 0) {
            $('#selected-size-label').text(' · ' + formatBytes(totalBytes));
        } else {
            $('#selected-size-label').text('');
        }

        $('#download-selected-btn').prop('disabled', count === 0);
    }

    // Gene-set header collapse toggle (manual — avoids Bootstrap eating checkbox clicks)
    $(document).on('click', '.gs-header', function (e) {
        if ($(e.target).closest('input, label').length) return;
        const targetId = $(this).data('collapse-target');
        if (targetId) $(targetId).collapse('toggle');
    });

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
        $('#download-tree .organism-block:visible .gs-checkbox').prop({ checked: true, indeterminate: false });
        $('#download-tree .organism-block:visible .asm-checkbox').prop({ checked: true, indeterminate: false });
        $('#download-tree .organism-block:visible .org-checkbox').prop({ checked: true, indeterminate: false });
        updateSelectedCount();
    });

    $('#deselect-all-btn').on('click', function () {
        $('.file-checkbox, .gs-checkbox, .asm-checkbox, .org-checkbox').prop({ checked: false, indeterminate: false });
        updateSelectedCount();
    });

    // ── Organism filter ──────────────────────────────────────────────────────

    $('#organism-filter').on('input', function () {
        const query = $(this).val().toLowerCase().trim();
        $('.organism-block').each(function () {
            const name   = String($(this).data('organism-name') || '');
            const common = String($(this).data('common-name') || '');
            $(this).toggle(query === '' || name.includes(query) || common.includes(query));
        });
    });

    // ── Download Selected ────────────────────────────────────────────────────

    $('#download-selected-btn').on('click', function () {
        const checked = $('.file-checkbox:checked');
        if (checked.length === 0) return;

        if (checked.length === 1) {
            // Single file: direct download link, no need for server-side archive
            const url = checked.first().data('download-url');
            if (!url) return;
            const a = document.createElement('a');
            a.href     = url;
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            return;
        }

        // Multiple files: POST to download_zip.php which streams a tar.gz
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = sitePath + '/api/download_zip.php';

        checked.each(function (i) {
            const fields = {
                organism: $(this).data('organism'),
                assembly: $(this).data('assembly'),
                gene_set: $(this).data('gene-set'),
                filename: $(this).data('filename')
            };
            Object.entries(fields).forEach(function ([key, val]) {
                const input   = document.createElement('input');
                input.type    = 'hidden';
                input.name    = 'files[' + i + '][' + key + ']';
                input.value   = val || '';
                form.appendChild(input);
            });
        });

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    });

    // ── Init ─────────────────────────────────────────────────────────────────

    updateSelectedCount();
    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => new bootstrap.Popover(el, { sanitize: false }));
});

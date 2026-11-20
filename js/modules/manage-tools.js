/**
 * Manage Tools (admin/pages/manage_tools.php): reorder the Tools menu and the toolbox.
 *
 * Each row carries a hidden *_order[] input, so the order the rows are in when the form is
 * sent IS the saved order -- moving a row is all these buttons do. Dividers and headings exist
 * only in the menu list. A heading row sends '#h<n>' in menu_order[] and its text as
 * menu_heading[h<n>]; the server matches the two by that key (admin/manage_tools.php).
 */
(function () {
    'use strict';

    function move(row, up) {
        const other = up ? row.previousElementSibling : row.nextElementSibling;
        if (!other) return;
        if (up) row.parentNode.insertBefore(row, other);
        else row.parentNode.insertBefore(other, row);
        row.querySelector(up ? '.mt-up' : '.mt-down')?.focus();
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.mt-up, .mt-down, .mt-remove');
        if (!btn) return;
        const row = btn.closest('tr');
        if (btn.classList.contains('mt-remove')) row.remove();
        else move(row, btn.classList.contains('mt-up'));
    });

    const add = document.getElementById('mt-add-divider');
    if (add) {
        add.addEventListener('click', function () {
            const body = document.querySelector('.mt-list[data-list="menu"] tbody');
            const template = body.querySelector('tr.mt-divider');
            let row;
            if (template) {
                row = template.cloneNode(true);
            } else {
                row = document.createElement('tr');
                row.className = 'mt-divider table-light';
                row.innerHTML = '<td class="ps-3"><div class="btn-group btn-group-sm">'
                    + '<button type="button" class="btn btn-outline-secondary mt-up" title="Move up" aria-label="Move up"><i class="fa fa-arrow-up"></i></button>'
                    + '<button type="button" class="btn btn-outline-secondary mt-down" title="Move down" aria-label="Move down"><i class="fa fa-arrow-down"></i></button></div>'
                    + '<input type="hidden" name="menu_order[]" value="|"></td>'
                    + '<td colspan="2"><span class="text-muted small"><i class="fa fa-minus me-1"></i>divider line</span></td>'
                    + '<td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-danger mt-remove">Remove</button></td>';
            }
            body.insertBefore(row, body.firstChild);
        });
    }
    // New headings get keys from 900 up, clear of the ones the page numbered (h1, h2, ...).
    let headingKey = 900;
    const addHeading = document.getElementById('mt-add-heading');
    if (addHeading) {
        addHeading.addEventListener('click', function () {
            const body = document.querySelector('.mt-list[data-list="menu"] tbody');
            const key = 'h' + (headingKey++);
            const row = document.createElement('tr');
            row.className = 'mt-heading table-light';
            row.innerHTML = '<td class="ps-3"><div class="btn-group btn-group-sm">'
                + '<button type="button" class="btn btn-outline-secondary mt-up" title="Move up" aria-label="Move up"><i class="fa fa-arrow-up"></i></button>'
                + '<button type="button" class="btn btn-outline-secondary mt-down" title="Move down" aria-label="Move down"><i class="fa fa-arrow-down"></i></button></div>'
                + '<input type="hidden" name="menu_order[]" value="#' + key + '"></td>'
                + '<td><span class="small fw-semibold text-uppercase"><i class="fa fa-heading me-1"></i>heading</span></td>'
                + '<td><input type="text" class="form-control form-control-sm fw-semibold" maxlength="40" name="menu_heading[' + key + ']"'
                + ' placeholder="Heading text" aria-label="Heading text"></td>'
                + '<td class="text-end pe-3"><button type="button" class="btn btn-sm btn-outline-danger mt-remove">Remove</button></td>';
            body.insertBefore(row, body.firstChild);
            row.querySelector('input[type="text"]').focus();
        });
    }
})();
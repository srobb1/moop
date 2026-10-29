/**
 * Error Log page (admin/manage_error_log.php): ask before a form that rewrites the log.
 * The question is the form's data-confirm, so the page states it once, next to the button.
 */
document.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-confirm]');
    if (form && !window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
});

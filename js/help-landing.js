/**
 * Help landing page (tools/pages/help/landing.php): the topic filter.
 * Was an inline <script> in the template until 2026-11-25 (CSP: no inline scripts).
 */
/* Filter the topic cards. Reads data-search (title + description, lowercased server-side)
   rather than rendered text, so it never matches icon markup or the count badge.

   Two behaviours the old dashboard.php filter did not have, both needed because the admin
   topics now sit in a COLLAPSED section:
     - a match hidden inside the collapsed "Setting up & maintaining" panel is useless, so
       the panel is opened automatically while a filter is active, and restored when cleared;
     - a section whose cards have all been filtered out is hidden entirely, rather than
       leaving an empty heading and a count badge that now lies. */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('help-filter');
    if (!input) return;
    var msg      = document.getElementById('help-filter-msg');
    var cards    = Array.prototype.slice.call(document.querySelectorAll('.help-topic'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('.help-section'));
    var adminPanel = document.getElementById('adminHelpSection');
    var adminWasOpen = false, filtering = false;

    function apply() {
        var q = input.value.trim().toLowerCase();
        var shown = 0;
        cards.forEach(function (c) {
            var hit = !q || (c.dataset.search || '').indexOf(q) !== -1;
            c.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        // Hide a section that has nothing left in it.
        sections.forEach(function (sec) {
            var any = sec.querySelector('.help-topic:not([style*="display: none"])');
            sec.style.display = (q && !any) ? 'none' : '';
        });
        if (adminPanel) {
            // Open the collapsed admin panel ONLY when a surviving match is actually inside
            // it. Opening on any query would expand a section full of SELinux and permissions
            // topics because someone typed "blast" — the opposite of filtering.
            var hitInAdmin = !!adminPanel.querySelector('.help-topic:not([style*="display: none"])');
            if (q && !filtering) { adminWasOpen = adminPanel.classList.contains('show'); filtering = true; }
            if (q && hitInAdmin) adminPanel.classList.add('show');
            else if (q && !adminWasOpen) adminPanel.classList.remove('show');
            else if (!q && filtering) { if (!adminWasOpen) adminPanel.classList.remove('show'); filtering = false; }
        }
        if (msg) {
            msg.style.display = q ? '' : 'none';
            msg.textContent = shown === 0
                ? 'No topics match \u201c' + input.value.trim() + '\u201d'
                : shown + (shown === 1 ? ' topic' : ' topics') + ' match';
        }
    }
    input.addEventListener('input', apply);
    input.addEventListener('search', apply);   // the clear (x) in a type=search box
});

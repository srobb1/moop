/**
 * BLAST results view -- click behaviour for the HTML built by lib/blast_results_visualizer.php.
 *
 * Was inline onclick attributes plus two <script> blocks printed into the results
 * (jumpToHit / highlightHit / toggleQuerySection) until 2026-11-25; moved here so the page
 * needs no inline JavaScript (CSP 'unsafe-inline'). One delegated listener; the HTML says
 * what to do:
 *   data-blast-toggle="<id>"   show/hide that element and turn the header's chevron
 *   data-blast-goto="<id>"     scroll to that element
 *   data-blast-highlight="1"   ...and flash it (.hit-highlighted, CSS in the visualizer)
 */
(function () {
    'use strict';

    // Flash a hit's alignment block so the eye finds it after the scroll
    function highlight(el) {
        el.classList.remove('hit-highlighted');
        void el.offsetWidth;            // restart the CSS transition if clicked again
        el.classList.add('hit-highlighted');
        setTimeout(function () { el.classList.remove('hit-highlighted'); }, 3500);
    }

    document.addEventListener('click', function (e) {
        // A link or button inside a clickable row does its own job, not the row's
        if (e.target.closest('a, button')) return;

        const toggle = e.target.closest('[data-blast-toggle]');
        if (toggle) {
            const content = document.getElementById(toggle.dataset.blastToggle);
            if (!content) return;
            const hidden = content.style.display === 'none';
            content.style.display = hidden ? 'block' : 'none';
            // The chevron, not the header's first icon (the old code turned the cog / DNA icon)
            const chevron = toggle.querySelector('.fa-chevron-down');
            if (chevron) chevron.style.transform = hidden ? 'rotate(180deg)' : 'rotate(0deg)';
            return;
        }

        const go = e.target.closest('[data-blast-goto]');
        if (go) {
            const target = document.getElementById(go.dataset.blastGoto);
            if (!target) return;
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (go.dataset.blastHighlight) highlight(target);
        }
    });
})();

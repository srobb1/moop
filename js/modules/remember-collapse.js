/**
 * Remember whether a Bootstrap collapse was left open, per browser.
 *
 *   <div class="collapse" id="x" data-remember-collapse="some.key">
 *
 * The element starts as the markup says (folded); if this browser last left it open, it is
 * opened again on load. Storage can be missing or blocked -- then it simply stays folded.
 */
(function () {
    'use strict';
    function read(key) { try { return window.localStorage.getItem(key); } catch (e) { return null; } }
    function write(key, val) { try { window.localStorage.setItem(key, val); } catch (e) { /* not kept */ } }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-remember-collapse]').forEach(function (el) {
            var key = el.getAttribute('data-remember-collapse');
            if (read(key) === 'open' && window.bootstrap) {
                window.bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
            }
            el.addEventListener('shown.bs.collapse', function (e) { if (e.target === el) write(key, 'open'); });
            el.addEventListener('hidden.bs.collapse', function (e) { if (e.target === el) write(key, 'closed'); });
        });
    });
})();

/**
 * Field help popovers — one global, idempotent init for .field-help buttons.
 *
 * Deliberately the same shape as js/modules/glossary.js, for the same reason: a single
 * site-wide init means a field_help() button works on every page without that page
 * remembering to wire anything up. Per-page init is exactly why the older hand-written
 * popovers sit dead on half the site — the popover was never the problem, the init was.
 *
 * Scoped to `.field-help` so it can never collide with a page's own tooltip/popover
 * init, and guarded by getInstance() so re-running after an AJAX inject cannot
 * double-init an element.
 *
 * Note only popover triggers are touched. field_help() and help_modal_trigger() share
 * the .field-help class for one consistent look, but modal triggers open through the
 * Bootstrap data-api and need no JavaScript at all — the selector below requires
 * data-bs-toggle="popover", so modal triggers are simply skipped.
 */
(function () {
  function initFieldHelp(root) {
    if (!window.bootstrap || !bootstrap.Popover) return;
    (root || document)
      .querySelectorAll('.field-help[data-bs-toggle="popover"]')
      .forEach(function (el) {
        if (bootstrap.Popover.getInstance(el)) return;
        new bootstrap.Popover(el);
        // The title attribute is a no-JS fallback written by field_help(). Now that a
        // real popover exists, drop it — otherwise hovering shows the native browser
        // tooltip and clicking shows the popover, i.e. the same text twice in two
        // different places. Removed only after construction succeeds, so a page where
        // Bootstrap failed to load keeps its fallback.
        el.removeAttribute('title');
      });
  }

  if (document.readyState !== 'loading') {
    initFieldHelp();
  } else {
    document.addEventListener('DOMContentLoaded', function () { initFieldHelp(); });
  }

  // Pages that inject field help via AJAX can re-init just the new subtree.
  window.MoopFieldHelp = { init: initFieldHelp };
})();

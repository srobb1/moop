/**
 * Glossary popovers — one global, idempotent init for dashed-underline terms.
 *
 * A single site-wide init, so a .gloss term works on every page without each page
 * remembering to wire it up. That per-page-init requirement is exactly why the
 * older tooltips sit dead on half the site — this avoids repeating that mistake.
 *
 * Scoped to `.gloss` so it never collides with a page's own tooltip/popover init,
 * and guarded by getInstance() so re-running (e.g. after AJAX) never double-inits.
 */
(function () {
  function initGlossary(root) {
    if (!window.bootstrap || !bootstrap.Popover) return;
    (root || document)
      .querySelectorAll('.gloss[data-bs-toggle="popover"]')
      .forEach(function (el) {
        if (!bootstrap.Popover.getInstance(el)) new bootstrap.Popover(el);
      });
  }

  if (document.readyState !== 'loading') {
    initGlossary();
  } else {
    document.addEventListener('DOMContentLoaded', function () { initGlossary(); });
  }

  // Pages that inject .gloss terms via AJAX can re-init just the new subtree.
  window.MoopGlossary = { init: initGlossary };
})();

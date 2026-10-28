/**
 * Glossary popovers — one global, idempotent init for dashed-underline terms.
 *
 * A single site-wide init, so a .gloss term works on every page without each page
 * remembering to wire it up. That per-page-init requirement is exactly why the
 * older tooltips sit dead on half the site — this avoids repeating that mistake.
 *
 * Scoped to `.gloss` so it never collides with a page's own tooltip/popover init,
 * and guarded by getInstance() so re-running (e.g. after AJAX) never double-inits.
 *
 * Opens on hover and keyboard focus (data-bs-trigger="hover focus", from gloss() in
 * lib/glossary.php) — AND on a click or tap. A phone or tablet has no hover, and iOS
 * Safari does not reliably focus a tapped <span tabindex="0">, so a hover/focus-only
 * term may never open on a touch screen. Same family as the Safari (i) bug fixed in
 * js/modules/field-help.js (2026-10-28). A click on a term that hover already opened
 * leaves it open; a press anywhere else closes it — pointerdown, not click, because iOS
 * does not deliver taps on plain text to a document click handler.
 */
(function () {
  var SELECTOR = '.gloss[data-bs-toggle="popover"]';
  var openEl = null;  // the term whose popover is showing, if any

  function initGlossary(root) {
    if (!window.bootstrap || !bootstrap.Popover) return;
    (root || document)
      .querySelectorAll(SELECTOR)
      .forEach(function (el) {
        if (!bootstrap.Popover.getInstance(el)) new bootstrap.Popover(el);
      });
  }

  function hide(el) {
    var pop = window.bootstrap && bootstrap.Popover.getInstance(el);
    if (pop) pop.hide();
    if (openEl === el) openEl = null;
  }

  // Whatever opened it (hover, focus, or a tap below), remember it so a press elsewhere
  // can close it — on a touch screen no mouseleave/blur ever comes to do that.
  document.addEventListener('shown.bs.popover', function (e) {
    if (e.target.matches && e.target.matches(SELECTOR)) {
      if (openEl && openEl !== e.target) hide(openEl);
      openEl = e.target;
    }
  });
  document.addEventListener('hidden.bs.popover', function (e) {
    if (e.target === openEl) openEl = null;
  });

  document.addEventListener('click', function (e) {
    var term = e.target.closest ? e.target.closest(SELECTOR) : null;
    if (!term || !window.bootstrap || !bootstrap.Popover) return;
    // Already showing or on its way (hover got there first — iOS fires a synthetic hover
    // just before the tap's click): leave it. Bootstrap sets aria-describedby the moment
    // show() starts, before the shown event, so this catches both.
    if (term.hasAttribute('aria-describedby')) return;
    bootstrap.Popover.getOrCreateInstance(term).show();
  });

  document.addEventListener('pointerdown', function (e) {
    if (!openEl || !e.target.closest) return;
    if (e.target.closest(SELECTOR) || e.target.closest('.popover')) return;
    hide(openEl);
  });

  if (document.readyState !== 'loading') {
    initGlossary();
  } else {
    document.addEventListener('DOMContentLoaded', function () { initGlossary(); });
  }

  // Pages that inject .gloss terms via AJAX can re-init just the new subtree.
  window.MoopGlossary = { init: initGlossary };
})();

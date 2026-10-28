/**
 * Field help popovers — one global handler for .field-help buttons.
 *
 * Deliberately the same shape as js/modules/glossary.js, for the same reason: a single
 * site-wide handler means a field_help() button works on every page without that page
 * remembering to wire anything up. Per-page init is exactly why the older hand-written
 * popovers sat dead on half the site — the popover was never the problem, the init was.
 *
 * Opened by CLICK, not focus. These used data-bs-trigger="focus", which never fires in
 * Safari (or Firefox on macOS): those browsers do not give a clicked <button> focus, so
 * the popover had nothing to open on. Reported by a Safari tester 2026-10-27. Chromium
 * focuses on click, which is why it only ever looked fine in Chrome. So:
 *   - click the (i)            → opens it (and closes any other open one)
 *   - click the (i) again      → closes it
 *   - press anywhere else      → closes it (presses inside the popover itself do not)
 *   - Escape                   → closes it and returns focus to the (i)
 * Keyboard users still get it: Enter/Space on a <button> fires click.
 *
 * The clicks are handled by delegation on document, not per element, so the handler
 * works no matter who constructed the Popover instance (search-display.js inits every
 * popover on its page) and covers buttons injected later by AJAX without a re-init.
 *
 * Note only popover triggers are touched. field_help() and help_modal_trigger() share
 * the .field-help class for one consistent look, but modal triggers open through the
 * Bootstrap data-api and need no JavaScript at all — the selector below requires
 * data-bs-toggle="popover", so modal triggers are simply skipped.
 */
(function () {
  var SELECTOR = '.field-help[data-bs-toggle="popover"]';
  var openEl = null;  // the (i) whose popover is showing, if any

  function popoverFor(el) {
    if (!window.bootstrap || !bootstrap.Popover) return null;
    var pop = bootstrap.Popover.getOrCreateInstance(el, { trigger: 'manual' });
    // The title attribute is a no-JS fallback written by field_help(). Now that a
    // real popover exists, drop it — otherwise hovering shows the native browser
    // tooltip and clicking shows the popover, i.e. the same text twice in two
    // different places. Removed only after construction succeeds, so a page where
    // Bootstrap failed to load keeps its fallback.
    el.removeAttribute('title');
    return pop;
  }

  function closeOpen() {
    if (!openEl) return;
    var pop = bootstrap.Popover.getInstance(openEl);
    if (pop) pop.hide();
    openEl = null;
  }

  // Build instances up front, so the native-tooltip fallback is removed before anyone
  // hovers. Clicking works without this (popoverFor creates on demand).
  function initFieldHelp(root) {
    (root || document).querySelectorAll(SELECTOR).forEach(popoverFor);
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target.closest ? e.target.closest(SELECTOR) : null;
    if (trigger) {
      e.preventDefault();
      var wasOpen = openEl === trigger;
      closeOpen();
      if (!wasOpen) {
        var pop = popoverFor(trigger);
        if (pop) { pop.show(); openEl = trigger; }
      }
    }
  });

  // Closing on a press elsewhere listens to pointerdown, not click: iOS Safari does not
  // deliver a tap on plain text or empty space to a document click handler (only taps on
  // "clickable" elements), so a click listener left the popover stuck open on iPhone/iPad.
  // A press on the (i) itself is left to the click handler above, which toggles; a press
  // inside the open popover (selecting text, a link) must not close it.
  document.addEventListener('pointerdown', function (e) {
    if (!openEl || !e.target.closest) return;
    if (e.target.closest(SELECTOR) || e.target.closest('.popover')) return;
    closeOpen();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && openEl) {
      var el = openEl;
      closeOpen();
      el.focus();
    }
  });

  if (document.readyState !== 'loading') {
    initFieldHelp();
  } else {
    document.addEventListener('DOMContentLoaded', function () { initFieldHelp(); });
  }

  // Pages that inject field help via AJAX can re-init just the new subtree (only to drop
  // the title fallback early — clicks already work through the delegated handler).
  window.MoopFieldHelp = { init: initFieldHelp };
})();

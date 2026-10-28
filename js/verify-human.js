/**
 * Human check page (verify-human.php): loads Cloudflare Turnstile, renders the widget, and
 * submits the form as soon as the browser passes — usually without the visitor doing anything.
 *
 * The Cloudflare script is added here rather than as a <script src> in the page so that a
 * failure to load it (blocked, offline, Cloudflare down from this visitor's network) can say so
 * instead of leaving an empty box. Passing still needs the SERVER to confirm the token with
 * Cloudflare; nothing here can let a visitor in on its own.
 */
(function () {
  var box = document.getElementById('human-check');
  var form = document.getElementById('human-check-form');
  var status = document.getElementById('human-check-status');
  if (!box || !form) return;

  function say(msg) {
    status.textContent = msg;
    status.classList.remove('d-none');
  }

  window.moopHumanCheckLoaded = function () {
    window.turnstile.render(box, {
      sitekey: box.dataset.sitekey,
      // The widget writes its token into a hidden cf-turnstile-response field inside the form.
      callback: function () {
        // After a rejection the page waits for a click (see verify_human.php): submitting
        // automatically again would loop if the server keeps saying no.
        if (form.dataset.autosubmit === '0') {
          var retry = document.getElementById('human-check-retry');
          if (retry) retry.disabled = false;
        } else {
          form.submit();
        }
      },
      'error-callback': function () {
        say('The check could not run in this browser. Reload the page to try again, or log in.');
      },
      'expired-callback': function () { say('The check expired. Reload the page to try again.'); }
    });
  };

  var s = document.createElement('script');
  s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?onload=moopHumanCheckLoaded&render=explicit';
  s.async = true;
  s.onerror = function () {
    say('The check could not load. Your network may block challenges.cloudflare.com. ' +
        'Reload to try again, or log in.');
  };
  document.head.appendChild(s);
})();

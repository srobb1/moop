<?php
/**
 * Session bootstrap — the ONE place session cookie attributes are set.
 *
 * session_set_cookie_params() only has effect BEFORE session_start(), so every entry
 * point that starts a session must go through moop_session_start() rather than calling
 * session_start() directly. Otherwise that entry point silently issues a cookie with
 * PHP's defaults and the protections below do not apply to it.
 *
 * Attributes and why:
 *
 *   httponly  Always on. Nothing in MOOP's JavaScript reads document.cookie, so this
 *             costs nothing, and it means an XSS cannot read the session cookie. That
 *             matters while the Content-Security-Policy is still Report-Only with
 *             'unsafe-inline' (see CLAUDE.md) — XSS is not yet fully blocked.
 *
 *   samesite  Lax. Blocks the cookie on cross-site POST, which is defence in depth
 *             against CSRF for EVERY endpoint, including any added later that forgets
 *             to verify a token. MOOP has no cross-site POST flows to break. Lax rather
 *             than Strict so ordinary inbound links (email, wiki, JBrowse share URLs)
 *             still arrive logged in.
 *             Note this is a backstop, not a replacement for csrf_protect(): browsers
 *             differ in how they treat an absent/Lax SameSite, and Chrome exempts
 *             recently-set cookies from Lax on top-level POST.
 *
 *   secure    Set only when the request actually arrived over HTTPS. MOOP currently
 *             serves BOTH http:// and https:// with no redirect between them, so
 *             setting this unconditionally would silently break the session of anyone
 *             still using http://. php-fpm sees $_SERVER['HTTPS'] === 'on' directly
 *             from nginx (no proxy header involved), so this test is reliable here.
 *             The better end state is an nginx redirect of http -> https, after which
 *             this can simply become true.
 *
 *   name      DIFFERENT PER SCHEME, and this is not cosmetic — it repairs a lockout.
 *
 *             Both vhosts answer on the same host (server_name _; on 80 and 443), so
 *             one cookie name across both schemes shadows itself. Visit https:// once
 *             and the browser stores PHPSESSID with Secure. Go back to http:// and the
 *             browser will not SEND that cookie (it is Secure) and, by RFC 6265's
 *             "Leave Secure Cookies Alone" rule, an http response may not OVERWRITE it
 *             either. So every http request starts a brand-new session the browser
 *             refuses to keep: three consecutive page loads returned three different
 *             CSRF tokens, every POST failed verification, and the user got an
 *             unthemed "Security Error" page with no way out from inside the browser.
 *             Reloading cannot fix it; only deleting the cookie can, which no user
 *             will think to do. Confirmed 2026-07-29 — incognito worked, the real
 *             browser stayed broken until the cookie was deleted by hand.
 *
 *             Separate names let the two sessions coexist instead of one silently
 *             disabling the other. This is a workaround for serving both schemes at
 *             once; when http:// finally redirects to https:// the split can go away
 *             along with the conditional above.
 */

if (!function_exists('moop_session_start')) {
    /**
     * Start the session with MOOP's cookie attributes. Safe to call more than once.
     */
    function moop_session_start(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;   // already started (or sessions disabled) — params would be ignored
        }

        $is_https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');

        // Must be set BEFORE session_start(), same as the cookie params below.
        session_name($is_https ? 'MOOPSESS_S' : 'MOOPSESS');

        session_set_cookie_params([
            'lifetime' => 0,          // session cookie: cleared when the browser closes
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $is_https,
        ]);

        session_start();
    }
}

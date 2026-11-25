<?php
/**
 * Content Security Policy: a per-request NONCE for the site's own inline <script> blocks.
 *
 * WHY. nginx sends the site's policy (/etc/nginx/conf.d/moop-sites.conf), which still allows
 * 'unsafe-inline' -- any script written into a page runs, including one an attacker manages to
 * get into a page (a crafted search term echoed back, a bug in a template). This header adds a
 * SECOND policy with a strict script-src. A browser enforces every policy it is sent, and a
 * script must pass all of them, so with this one in place an inline script runs only if it
 * carries this request's nonce: the site's own blocks do, injected ones cannot (the value is
 * random per page load). Added 2026-11-25, after the last inline on* handler was removed --
 * handlers cannot carry a nonce, which is why that had to come first (CLAUDE.md §7).
 *
 * nginx cannot do this itself: a nonce has to match what PHP printed into THIS page.
 * Nothing here needs root. Root may later drop 'unsafe-inline' from nginx's copy as a tidy-up;
 * it changes nothing in what a browser runs once this header is sent.
 *
 * Use: <script<?= moop_nonce_attr() ?>> ... </script>   (inline_scripts in layout.php do it for you)
 * Keep in step with nginx's script-src hosts: a host allowed there but not here is blocked.
 */

/** This request's nonce (random, base64). The same value for the whole request. */
function moop_csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) $nonce = base64_encode(random_bytes(18));
    return $nonce;
}

/** ' nonce="…"' for a <script> tag. */
function moop_nonce_attr(): string
{
    return ' nonce="' . moop_csp_nonce() . '"';
}

/**
 * Send the strict script policy (once; never from the command line, never after output).
 * worker-src is repeated here because without it workers fall back to THIS script-src and
 * JBrowse's blob: workers would be refused.
 */
function moop_csp_send(): void
{
    static $sent = false;
    if ($sent || PHP_SAPI === 'cli' || headers_sent()) return;
    $sent = true;
    header("Content-Security-Policy: script-src 'self' 'nonce-" . moop_csp_nonce() . "' 'wasm-unsafe-eval'"
         . " https://challenges.cloudflare.com; worker-src 'self' blob:; object-src 'none'; base-uri 'self'", false);
}

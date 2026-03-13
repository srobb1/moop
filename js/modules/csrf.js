/**
 * CSRF Protection - jQuery AJAX and native fetch()
 *
 * Reads the CSRF token from the <meta name="csrf-token"> tag (written by
 * head-resources.php) and automatically attaches it as an X-CSRF-Token
 * header on every state-changing request (POST/PUT/PATCH/DELETE).
 *
 * Covers two request styles:
 *   1. jQuery $.ajax() / $.post() — via $.ajaxSetup()
 *   2. Native fetch() — via a thin wrapper that replaces window.fetch
 *
 * The token itself is generated server-side in access_control.php and
 * verified by csrf_protect() in each POST handler.
 *
 * Loaded globally by layout.php for all pages.
 */
(function ($) {
    'use strict';

    var meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        // No token available (e.g. public page with no session) - nothing to do
        return;
    }

    var token = meta.getAttribute('content');
    if (!token) {
        return;
    }

    // 1. jQuery: attach the token to all state-changing AJAX requests automatically
    $.ajaxSetup({
        beforeSend: function (xhr, settings) {
            var method = (settings.type || 'GET').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                xhr.setRequestHeader('X-CSRF-Token', token);
            }
        }
    });

    // 2. Native fetch(): wrap window.fetch to inject the header automatically
    var _originalFetch = window.fetch;
    window.fetch = function (resource, options) {
        options = options || {};
        var method = (options.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
            options.headers = options.headers || {};
            // Support both plain objects and Headers instances
            if (options.headers instanceof Headers) {
                if (!options.headers.has('X-CSRF-Token')) {
                    options.headers.set('X-CSRF-Token', token);
                }
            } else {
                if (!options.headers['X-CSRF-Token']) {
                    options.headers['X-CSRF-Token'] = token;
                }
            }
        }
        return _originalFetch.call(this, resource, options);
    };

}(jQuery));

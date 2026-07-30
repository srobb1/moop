<?php
/**
 * Versioned URLs for local JS/CSS: appends ?v=<filemtime>.
 *
 * Without this a browser keeps serving its cached copy after a deploy, so a returning
 * user runs a MIXTURE of old and new files -- which is worse than running all-old,
 * because the halves disagree with each other. Observed on 2026-07-30: scope-filter.js
 * and annotation-search.js were changed ten minutes apart while a page was open, and the
 * result was a page left greyed out by a stranded modal backdrop that could not be
 * reproduced on any fresh load.
 *
 * page_script in layout.php was ALREADY versioned this way. The shared modules, the
 * vendor bundles and every stylesheet were not -- 23 script tags and 13 link tags served
 * bare. The presence of the one versioned case is precisely what made the bare ones look
 * deliberate, which is the pattern CLAUDE.md section 9b asks to be reported on sight.
 *
 * Lives in its own file because head-resources.php is used by both template systems and
 * must not depend on layout.php having been loaded first.
 */

if (!function_exists('moop_asset_url')) {
    /**
     * @param string $app_relative Path under the app root, e.g. 'js/modules/csrf.js'
     * @return string Site-absolute URL with a cache-busting version, e.g.
     *                '/moop/js/modules/csrf.js?v=1774295907'
     */
    function moop_asset_url(string $app_relative): string
    {
        $site = ConfigManager::getInstance()->getString('site');
        $rel  = ltrim($app_relative, '/');
        // Missing file: emit the URL unversioned rather than fail. A 404 on a real asset
        // is the web server's job to report; this helper must never be the thing that
        // breaks a page.
        $ver = @filemtime(dirname(__DIR__) . '/' . $rel);
        return '/' . $site . '/' . htmlspecialchars($rel) . ($ver ? '?v=' . $ver : '');
    }

    /**
     * Version an already site-prefixed URL ('/moop/js/x.js'), as page_script and
     * page_styles supply. Anything not under this site (an absolute URL, or another
     * prefix) is returned untouched -- versioning is only meaningful for files on disk here.
     */
    function moop_asset_url_from_site_path(string $url): string
    {
        $site   = ConfigManager::getInstance()->getString('site');
        $prefix = '/' . $site . '/';
        if (strpos($url, $prefix) !== 0) {
            return htmlspecialchars($url);
        }
        return moop_asset_url(substr($url, strlen($prefix)));
    }
}

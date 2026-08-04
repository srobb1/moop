<?php
/**
 * CACHE PATHS — where the app writes its own generated cache files.
 *
 * These caches used to be written *inside* the organism data tree
 * (organisms/{organism}/{assembly}/{gene_set}/). That had three costs:
 *
 *   1. It forced the entire organism tree — genomes, GFFs, SQLite databases —
 *      to be writable by the web server, purely so it could drop a few small
 *      JSON files in there. See docs/SELINUX_AND_HARDENING.md.
 *   2. It mixed app-generated files in with the data you ship in from elsewhere,
 *      so "what is actually mine?" stopped having a clean answer.
 *   3. The downloads page had to explicitly filter the cache files back out of
 *      its own listing (tools/downloads.php $excluded_filenames).
 *
 * They now live under a single cache root — the 'cache_path' config value —
 * which mirrors the organism directory structure:
 *
 *      organisms/Nvec/GCA_x/NV2/chr_names_cache.json      (before)
 *      {cache_path}/Nvec/GCA_x/NV2/chr_names_cache.json   (after)
 *
 * EVERYTHING under the cache root is regenerable. Deleting the whole tree is
 * safe at any time; caches rebuild on next access. Do not put anything there
 * that you would miss.
 */

/**
 * Root directory for all generated caches.
 *
 * Falls back to the organism data tree if 'cache_path' is unset, which preserves
 * the old behaviour for a deployment that has not configured it.
 */
function moop_cache_root(): string
{
    $config = ConfigManager::getInstance();
    $root   = $config->getPath('cache_path');
    if ($root === '') {
        $root = $config->getPath('organism_data');
    }
    return rtrim($root, '/');
}

/**
 * Create a cache directory if it does not exist.
 *
 * Returns false rather than throwing: a cache we cannot write is a performance
 * problem, never a correctness one, so every caller is expected to carry on
 * without caching rather than fail the request.
 *
 * The chmod after mkdir is NOT redundant. mkdir()'s mode argument is masked by the
 * process umask, so `mkdir($dir, 0775)` under the usual umask 022 produces 0755 — group
 * readable, group NOT writable. When a CLI script (running as the owner) creates the
 * directory first, php-fpm can then never write into it.
 *
 * That is not hypothetical: on 2026-08-03, 281 of 282 directories under cache_path were
 * 2750, so php-fpm could not write a single cache file. Nothing errored, because callers
 * fall back to computing the value live and ignore the failed write — which meant 95 of
 * 96 gene sets re-ran getAnnotatedFeatureTypesInGeneSet() on EVERY gene page load, at
 * 349ms cold each time. A cache that cannot be written is invisible by construction, so
 * the mode has to be forced at creation. lib/permission_check.php now also sweeps this
 * tree, because the same drift can arrive from outside this function.
 */
function moop_ensure_cache_dir(string $dir): bool
{
    if (is_dir($dir)) return true;

    if (@mkdir($dir, 0775, true) || is_dir($dir)) {
        // Walk back up to cache root, fixing every level mkdir() just created: with
        // recursive mkdir the umask applies to the intermediate directories too.
        $root = rtrim(moop_cache_root(), '/');
        $p    = rtrim($dir, '/');
        while ($p !== '' && $p !== '/' && strpos($p, $root) === 0) {
            @chmod($p, 02775);
            if ($p === $root) break;
            $p = dirname($p);
        }
        return is_dir($dir);
    }
    return false;
}

/**
 * Mirror a directory from the organism data tree into the cache tree.
 *
 *      {organism_data}/Nvec/GCA_x/NV2  ->  {cache_root}/Nvec/GCA_x/NV2
 *
 * Returns '' if $data_dir is not inside the organism tree, or if the cache
 * directory cannot be created. Callers must treat '' as "caching unavailable"
 * and compute the value directly.
 */
function moop_cache_dir_for(string $data_dir): string
{
    $organism_data = rtrim(ConfigManager::getInstance()->getPath('organism_data'), '/');
    $data_dir      = rtrim($data_dir, '/');

    if ($organism_data === '' || $data_dir === '') {
        return '';
    }

    if ($data_dir === $organism_data) {
        $relative = '';
    } elseif (str_starts_with($data_dir, $organism_data . '/')) {
        $relative = substr($data_dir, strlen($organism_data) + 1);
    } else {
        // Outside the organism tree — refuse rather than guess where it belongs.
        return '';
    }

    $dir = moop_cache_root() . ($relative === '' ? '' : "/$relative");
    return moop_ensure_cache_dir($dir) ? $dir : '';
}

/**
 * The single site-wide organism cache.
 * Was: organisms/.organism_cache.json — same filename, new location. The name
 * is kept byte-for-byte so that with 'cache_path' unset the fallback resolves to
 * the exact path the app used before, i.e. no behaviour change for a deployment
 * that never configures a cache directory.
 */
function moop_organism_cache_file(): string
{
    $root = moop_cache_root();
    return moop_ensure_cache_dir($root) ? "$root/.organism_cache.json" : '';
}

/**
 * Per-organism annotation-sources cache.
 * Was: organisms/{organism}/annotation_sources_cache.json
 */
function moop_annotation_sources_cache_file(string $organism): string
{
    $dir = moop_cache_root() . '/' . $organism;
    return moop_ensure_cache_dir($dir) ? "$dir/annotation_sources_cache.json" : '';
}

/**
 * Per-organism Wikipedia lookup cache.
 *
 * Sits beside annotation_sources_cache.json, same per-organism subdirectory pattern, so
 * it needs no new entry in the writable allowlist or the SELinux script (CLAUDE.md §11) —
 * the cache root already carries httpd_sys_rw_content_t and this is a directory under it.
 *
 * Exists because the organism page called the Wikipedia API live on every single page
 * load. Measured 2026-08-04: 348 ms of a 428 ms page, all 85 organisms affected (every
 * one lacks both a stored description and a stored image, so nothing took the fast path),
 * and the worst case is far uglier than the average — getWikipediaOrganismData() tries up
 * to three titles and then a search fallback, four sequential calls at a 10 s timeout each.
 */
function moop_wikipedia_cache_file(string $organism): string
{
    $dir = moop_cache_root() . '/' . $organism;
    return moop_ensure_cache_dir($dir) ? "$dir/wikipedia.json" : '';
}

/**
 * Lock file coordinating the background organism-cache refresh. Lives beside the
 * organism cache it guards. Was: organisms/.organism_cache_lock — moved out with
 * the cache so the organisms/ tree can be read-only to the web server.
 */
function moop_organism_cache_lock_file(): string
{
    $root = moop_cache_root();
    return moop_ensure_cache_dir($root) ? "$root/.organism_cache_lock" : '';
}

/**
 * Is a background organism-cache refresh actually running right now?
 *
 * THE ONE definition of "refreshing". Three callers used to answer this question
 * separately -- admin/api/refresh_organism_cache.php (PID liveness), lib/housekeeping.php
 * (PID liveness) and admin/pages/admin.php via admin/admin.php (lock exists AND mtime
 * < 600s). The dashboard's mtime variant is what wedged the UI on 2026-08-04: a refresh
 * completed at 08:17:32 but its background shell never reached the `rm -f` that drops the
 * lock, so the lock sat there holding a fresh mtime. The dashboard read that as "in
 * progress" and rendered the spinner; the status endpoint read the same lock, saw the PID
 * was dead, reported `idle` and the JS jumped the bar to 100%. Banner and bar disagreed
 * until a poll happened to unlink the lock. Two answers to one question, so one of them
 * was always going to be wrong.
 *
 * mtime cannot answer this. It says when the lock was CREATED, not whether the worker
 * lives -- an orphan under 600s reads as running, and a genuine scan past 600s reads as
 * finished. The PID does answer it, so that is the only check here.
 *
 * Side effect by design: a lock whose process is gone is REMOVED. That is what makes the
 * wedge self-healing rather than something an admin has to clear by hand -- whichever
 * caller notices first cleans up for everyone.
 */
function moop_organism_cache_refresh_active(): bool
{
    $lock_file = moop_organism_cache_lock_file();
    if (!$lock_file || !file_exists($lock_file)) return false;

    $pid = (int) trim((string) @file_get_contents($lock_file));
    // A just-created lock holds the placeholder '0' until the child shell overwrites it
    // with its real PID (see the launchers). Treat that as running for a short grace
    // window; otherwise a status poll landing inside the race would delete a lock whose
    // worker is about to start, and a second click could launch a duplicate scan.
    if ($pid <= 0) {
        return (time() - (int) @filemtime($lock_file)) < 30;
    }
    if (file_exists("/proc/$pid")) return true;

    @unlink($lock_file);
    return false;
}

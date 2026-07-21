<?php
/**
 * Registry source files — the single definition of WHICH files each function registry is
 * built from.
 *
 * Why this exists: the answer used to be written down three separate times — once in
 * tools/generate_registry_json.php (PHP registry), once in tools/generate_js_registry_json.php
 * (JS registry, and twice within that file), and a third time inside getRegistryLastUpdate(),
 * which decides whether a registry is stale. The third copy scanned only *.php, for BOTH
 * registries. The JavaScript registry therefore reported "Some JavaScript files are newer than
 * the registry" based purely on PHP timestamps: editing a .js file left it claiming to be up to
 * date, and editing any .php file made it claim JS had changed. Both directions were wrong.
 *
 * A staleness check is only meaningful if it watches exactly the files the generator reads, so
 * that set is defined here once and shared.
 *
 * Deliberately dependency-free (no ConfigManager) — the generators run from the CLI.
 */

if (!function_exists('moop_registry_source_files')) {

/**
 * Every file the named registry is generated from.
 *
 * @param string $type 'php' or 'js'
 * @param string|null $base Site root; defaults to the parent of lib/
 * @return list<string> absolute paths
 */
function moop_registry_source_files(string $type, ?string $base = null): array
{
    $base = $base ?? dirname(__DIR__);

    if ($type === 'js') {
        // Mirrors tools/generate_js_registry_json.php: top-level js/ plus js/modules/, and
        // NOT recursive — which is what keeps js/vendor/ (third-party code we do not
        // document) out of the registry.
        $files = array_merge(
            glob("$base/js/*.js") ?: [],
            glob("$base/js/modules/*.js") ?: []
        );
        $files = array_filter($files, function ($f) {
            return strpos($f, '.min.js') === false && strpos($f, 'unused') === false;
        });
        sort($files);
        return array_values($files);
    }

    // Mirrors tools/generate_registry_json.php: these four directories RECURSIVELY, plus the
    // top-level scripts. Recursive matters — an earlier glob-based version silently skipped
    // lib/jbrowse/, admin/api/ and admin/pages/.
    $scan_dirs = ["$base/lib", "$base/tools", "$base/admin", "$base/includes"];
    $files     = glob("$base/*.php") ?: [];

    foreach ($scan_dirs as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
            RecursiveIteratorIterator::CATCH_GET_CHILD
        );
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
                $files[] = $f->getPathname();
            }
        }
    }

    // Same exclusions the generator applies.
    $exclude = ['.backup', 'generate_registry', 'function_registry'];
    $files = array_filter($files, function ($f) use ($exclude) {
        foreach ($exclude as $pattern) {
            if (strpos($f, $pattern) !== false) return false;
        }
        return true;
    });

    $files = array_values(array_unique($files));
    sort($files);
    return $files;
}

/**
 * Newest modification time across a registry's source files, and which file it was.
 *
 * @return array{time:int,file:string|null,count:int}
 */
function moop_registry_newest_source(string $type, ?string $base = null): array
{
    $newest = 0;
    $newest_file = null;
    $files = moop_registry_source_files($type, $base);
    foreach ($files as $f) {
        $t = @filemtime($f);
        if ($t !== false && $t > $newest) {
            $newest = $t;
            $newest_file = $f;
        }
    }
    return ['time' => $newest, 'file' => $newest_file, 'count' => count($files)];
}

}

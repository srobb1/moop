<?php
/**
 * Third-party library check: what MOOP ships, proven by checksum, and whether OSV
 * (osv.dev, the open vulnerability database behind GitHub's advisories) knows of a
 * vulnerability in any of those exact versions.
 *
 * Nothing here is a hand-kept list of bad versions -- OSV is maintained upstream. The
 * inventory comes from what already exists:
 *   scripts/vendor_assets.txt       front-end files: url, sha256, ecosystem:package@version
 *   vendor/composer/installed.json  PHP packages, as Composer installed them
 * The checksum is what makes the version trustworthy: a file that no longer matches its
 * pinned sha256 is reported as unverified rather than assumed to be the version listed.
 */

/** Rows of scripts/vendor_assets.txt: [path, url, sha256, post, package]. */
function moop_vendor_asset_rows(?string $file = null): array {
    $file = $file ?? dirname(__DIR__) . '/scripts/vendor_assets.txt';
    $rows = [];
    foreach (@file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $f = explode('|', $line);
        if (count($f) >= 5) $rows[] = array_map('trim', array_slice($f, 0, 5));
    }
    return $rows;
}

/**
 * Every third-party package with its version and any problem found locally.
 *
 * @param string $root  the install root (tests pass a temp dir)
 * @return list<array{ecosystem:string, name:string, version:string, files:list<string>, problems:list<string>}>
 */
function moop_library_inventory(string $root, ?array $rows = null): array {
    $rows = $rows ?? moop_vendor_asset_rows("$root/scripts/vendor_assets.txt");
    $pkgs = [];
    foreach ($rows as [$path, $url, $sha, , $spec]) {
        if (!preg_match('/^(\w+):(.+)@([^@]+)$/', $spec, $m)) {
            $pkgs["?$path"] = ['ecosystem' => '', 'name' => $path, 'version' => '', 'files' => [$path],
                               'problems' => ["no package@version recorded for $path"]];
            continue;
        }
        [, $eco, $name, $ver] = $m;
        $key = "$eco:$name@$ver";
        $pkgs[$key] = $pkgs[$key] ?? ['ecosystem' => $eco === 'npm' ? 'npm' : $eco, 'name' => $name,
                                     'version' => $ver, 'files' => [], 'problems' => []];
        $pkgs[$key]['files'][] = $path;
        // The URL names the version it was downloaded as; a bump that changed one column and
        // not the other would make OSV check the wrong release.
        if (strpos($url, $ver) === false) {
            $pkgs[$key]['problems'][] = "$path: the URL does not mention version $ver";
        }
        $file = "$root/$path";
        if (!is_file($file)) {
            $pkgs[$key]['problems'][] = "$path is missing";
        } elseif (hash_file('sha256', $file) !== $sha) {
            $pkgs[$key]['problems'][] = "$path no longer matches its pinned checksum, so its version is unverified";
        }
    }
    // Composer: runtime packages only. Dev tools (require-dev) never ship to the site.
    $installed = json_decode((string)@file_get_contents("$root/vendor/composer/installed.json"), true);
    $dev = array_flip($installed['dev-package-names'] ?? []);
    foreach (($installed['packages'] ?? (is_array($installed) ? $installed : [])) as $p) {
        if (!isset($p['name'], $p['version']) || isset($dev[$p['name']])) continue;
        $ver = ltrim((string)$p['version'], 'v');
        $pkgs["Packagist:{$p['name']}@$ver"] = ['ecosystem' => 'Packagist', 'name' => $p['name'], 'version' => $ver,
                                                'files' => ['vendor/' . $p['name']], 'problems' => []];
    }
    return array_values($pkgs);
}

/**
 * Ask OSV about each package@version. One batch request, then one detail request per
 * vulnerability found (normally none). Returns null when OSV could not be reached, so the
 * caller keeps the last good answer rather than reporting "no vulnerabilities".
 *
 * @return array<int, list<array{id:string, summary:string, severity:string, fixed:list<string>, url:string}>>|null
 *         keyed by the package's index in $pkgs
 */
function moop_osv_check(array $pkgs): ?array {
    $queries = [];
    $index = [];
    foreach ($pkgs as $i => $p) {
        if ($p['ecosystem'] === '' || $p['version'] === '') continue;
        $queries[] = ['package' => ['name' => $p['name'], 'ecosystem' => $p['ecosystem']], 'version' => $p['version']];
        $index[] = $i;
    }
    if (!$queries) return [];
    $batch = moop_osv_request('https://api.osv.dev/v1/querybatch', json_encode(['queries' => $queries]));
    if (!isset($batch['results']) || count($batch['results']) !== count($queries)) return null;

    $out = [];
    foreach ($batch['results'] as $n => $res) {
        foreach ($res['vulns'] ?? [] as $v) {
            $d = moop_osv_request('https://api.osv.dev/v1/vulns/' . rawurlencode($v['id'])) ?? [];
            $fixed = [];
            foreach ($d['affected'] ?? [] as $a) {
                if (($a['package']['name'] ?? '') !== $pkgs[$index[$n]]['name']) continue;
                foreach ($a['ranges'] ?? [] as $r) {
                    foreach ($r['events'] ?? [] as $e) if (isset($e['fixed'])) $fixed[] = $e['fixed'];
                }
            }
            $out[$index[$n]][] = [
                'id'       => $v['id'],
                'summary'  => (string)($d['summary'] ?? ''),
                'severity' => ucfirst(strtolower((string)($d['database_specific']['severity'] ?? 'unknown'))),
                'fixed'    => array_values(array_unique($fixed)),
                'url'      => 'https://osv.dev/vulnerability/' . rawurlencode($v['id']),
            ];
        }
    }
    return $out;
}

/** One OSV call; decoded JSON or null. */
function moop_osv_request(string $url, ?string $post = null): ?array {
    if (!function_exists('curl_init')) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'MOOP-library-check',
    ] + ($post !== null ? [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $post,
                           CURLOPT_HTTPHEADER => ['Content-Type: application/json']] : []));
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = $code === 200 && $body ? json_decode($body, true) : null;
    return is_array($data) ? $data : null;
}
